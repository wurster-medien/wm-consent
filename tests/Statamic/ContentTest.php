<?php

namespace WursterMedien\Consent\Tests\Statamic;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Testing\AddonTestCase;
use WursterMedien\Consent\Http\Controllers\StatusController;
use WursterMedien\Consent\ServiceProvider;

/**
 * „Demo-Stand merken“ und die Inhalte für „Website prüfen“ im Kundenbereich (Simon, 3.11.2026): unveränderte Texte und
 * Bilder aus demo/ sind Platzhalter, jede Stelle, jeder Bereich und jede Seite hat einen Fingerabdruck.
 */
class ContentTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.url', 'https://kunde.test');
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.system.license_key', 'lizenz-123');
        $app['config']->set('wm-consent.demo_path', dirname(__DIR__).'/__fixtures__/storage/demo.json');
        $app['config']->set('wm-consent.defaults_url', null);
        $app['config']->set('wm-consent.styles_url', null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        AssetContainer::make('assets')->disk('local')->save();
        Collection::make('pages')->routes('/{slug}')->save();
        Blueprint::make('page')->setNamespace('collections.pages')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'h1', 'field' => ['type' => 'list', 'display' => 'Überschrift']],
            ['handle' => 'intro', 'field' => ['type' => 'textarea', 'display' => 'Einleitung']],
            ['handle' => 'image', 'field' => ['type' => 'assets', 'container' => 'assets', 'max_files' => 1]],
            ['handle' => 'blocks', 'field' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => [
                'text' => ['display' => 'Text und Bild', 'fields' => [['handle' => 'body', 'field' => ['type' => 'markdown']]]],
            ]]]]],
        ]]]]]])->save();
    }

    protected function tearDown(): void
    {
        Entry::all()->each->delete();
        Collection::find('pages')?->delete();
        AssetContainer::find('assets')?->delete();
        @unlink((string) config('wm-consent.demo_path'));

        parent::tearDown();
    }

    public function test_unchanged_texts_and_demo_images_are_placeholders(): void
    {
        Entry::make()->collection('pages')->slug('leistungen')->data([
            'title' => 'Leistungen',
            'h1' => ['**Unsere Leistungen**', 'Alles für Ihren Garten.'],
            'intro' => 'Von der ersten Skizze bis zur letzten Pflanze.',
            'image' => ['demo/garten.jpg'],
            'blocks' => [
                ['type' => 'text', 'body' => 'Gemeinsam entwickeln wir einen Plan.'],
                ['type' => 'text', 'body' => '**Naturstein**, Holz oder Feinsteinzeug.'],
            ],
        ])->save();

        $this->artisan('wm-consent:demo-stand')->expectsOutput('6 Texte als Demo-Stand gemerkt.')->assertSuccessful();

        $entry = Entry::query()->where('slug', 'leistungen')->first();
        $entry->set('intro', 'Unser eigener Text.')->save();

        $data = $this->content()->assertOk()->json();
        $page = $data['pages'][0];
        $spots = collect($page['spots'])->keyBy('path');

        $this->assertNotNull($data['demo_stand']);
        $this->assertStringEndsWith('/leistungen', $page['url']);
        $this->assertFalse($spots['intro']['demo']);
        $this->assertSame('Unser eigener Text.', $spots['intro']['text']);
        $this->assertSame('demo/garten.jpg', $spots['image.0']['asset']);
        $this->assertSame('Naturstein, Holz oder Feinsteinzeug.', $spots['blocks.1.body']['text']);
        $this->assertSame('Unsere Leistungen', $spots['h1.0']['text']);
        $this->assertSame('Alles für Ihren Garten.', $spots['h1.1']['text']);
        $this->assertTrue($spots['h1.1']['demo']);
        $this->assertTrue($spots['blocks.0.body']['demo']);
        $this->assertSame('Gemeinsam entwickeln wir einen Plan.', $spots['blocks.0.body']['text']);
        $this->assertSame('blocks.0', $spots['blocks.0.body']['section']);
        $this->assertTrue($spots['image.0']['demo']);
        $this->assertSame('image', $spots['image.0']['kind']);
        $this->assertSame(['blocks.0', 'blocks.1'], array_column($page['sections'], 'key'));
        $this->assertSame('Text und Bild', $page['sections'][0]['label']);

        $before = $page['fingerprint'];
        $entry->set('blocks', [['type' => 'text', 'body' => 'Neu.'], ['type' => 'text', 'body' => '**Naturstein**, Holz oder Feinsteinzeug.']])->save();
        $after = $this->content()->json('pages.0');

        $this->assertNotSame($before, $after['fingerprint']);
        $this->assertNotSame($page['sections'][0]['fingerprint'], $after['sections'][0]['fingerprint']);
        $this->assertSame($page['sections'][1]['fingerprint'], $after['sections'][1]['fingerprint']);
    }

    public function test_only_signed_requests_get_the_content(): void
    {
        $time = time();

        $this->post('/wm-consent/inhalte')->assertNotFound();
        $this->post('/wm-consent/inhalte', [], ['X-WM-Timestamp' => (string) $time, 'X-WM-Signature' => StatusController::signature('lizenz-123', $time)])->assertNotFound();
        $this->content()->assertOk()->assertJsonPath('demo_stand', null);
    }

    private function content(): \Illuminate\Testing\TestResponse
    {
        $time = time();

        return $this->post('/wm-consent/inhalte', [], ['X-WM-Timestamp' => (string) $time, 'X-WM-Signature' => StatusController::signature('lizenz-123', $time, 'wm-consent-content')]);
    }
}
