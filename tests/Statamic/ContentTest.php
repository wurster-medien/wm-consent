<?php

namespace WursterMedien\Consent\Tests\Statamic;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
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
            ['handle' => 'intro', 'field' => ['type' => 'textarea', 'display' => 'Einleitung', 'character_limit' => 300, 'validate' => ['required']]],
            ['handle' => 'image', 'field' => ['type' => 'assets', 'container' => 'assets', 'max_files' => 1, 'instructions' => 'Min. 2600px Breite', 'wm_image_ratio' => '2:1']],
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

        $this->artisan('wm-consent:demo-stand')->expectsOutput('6 Texte und 1 Bilder als Demo-Stand gemerkt.')->assertSuccessful();

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

    public function test_entries_under_an_unpublished_mount_are_left_out(): void
    {
        $mount = Entry::make()->collection('pages')->slug('aktuelles')->data(['title' => 'Aktuelles'])->published(false);
        $mount->save();
        Collection::make('news')->routes('/aktuelles/{slug}')->mount($mount->id())->save();
        Entry::make()->collection('news')->slug('neues-logo')->data(['title' => 'Neues Logo'])->save();
        Entry::make()->collection('pages')->slug('kontakt')->data(['title' => 'Kontakt'])->save();

        $this->assertSame(['Kontakt'], array_column($this->content()->json('pages'), 'title'));

        $mount->published(true)->save();
        $this->assertSame(['Aktuelles', 'Neues Logo', 'Kontakt'], array_column($this->content()->json('pages'), 'title'));
        $this->assertSame(['pages', 'news', 'pages'], array_column($this->content()->json('pages'), 'collection'));

        Collection::find('news')?->delete();
    }

    public function test_needs_from_the_blueprints(): void
    {
        Entry::make()->collection('pages')->slug('leistungen')->data(['title' => 'Leistungen', 'intro' => 'Von der ersten Skizze bis zur letzten Pflanze.', 'image' => ['demo/garten.jpg']])->save();

        Collection::make('team')->routes('/team/{slug}')->titleFormats('{vorname} {nachname}')->save();
        Blueprint::make('mitglied')->setNamespace('collections.team')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'vorname', 'field' => ['type' => 'text', 'display' => 'Vorname']],
            ['handle' => 'foto', 'field' => ['type' => 'assets', 'container' => 'assets', 'display' => 'Foto', 'max_files' => 1, 'wm_image_width' => 1000, 'wm_hint' => 'Hochformat, ruhiger Hintergrund']],
            ['handle' => 'slug', 'field' => ['type' => 'slug']],
            ['handle' => 'intern', 'field' => ['type' => 'text', 'wm_need' => false]],
        ]]]]]])->save();
        Entry::make()->collection('team')->slug('erika')->data(['vorname' => 'Erika', 'foto' => ['demo/erika.jpg']])->save();
        /* Ein Musterbild außerhalb von demo/ gilt ab 1.4.1 auch als Platzhalter, solange es bleibt. */
        Entry::make()->collection('team')->slug('lena')->data(['vorname' => 'Lena', 'foto' => ['team/lena.jpg']])->save();

        $set = GlobalSet::make('impressum')->title('Impressum');
        $set->save();
        Blueprint::make('impressum')->setNamespace('globals')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'firmierung', 'field' => ['type' => 'textarea', 'display' => 'Firmierung']],
            ['handle' => 'register', 'field' => ['type' => 'text', 'display' => 'Register']],
            ['handle' => 'gericht', 'field' => ['type' => 'text', 'display' => 'Gericht']],
        ]]]]]])->save();
        $set->inDefaultSite()->data(['firmierung' => 'Musterfirma GmbH', 'gericht' => 'Amtsgericht Stuttgart'])->save();

        $this->artisan('wm-consent:demo-stand --globals')->assertSuccessful();
        Entry::make()->collection('team')->slug('max')->data(['vorname' => 'Max', 'foto' => ['team/max.jpg']])->save();
        GlobalSet::find('impressum')->inDefaultSite()->set('gericht', 'Amtsgericht Freiburg')->save();

        $data = $this->content()->assertOk()->json();
        $spots = collect(collect($data['pages'])->firstWhere('title', 'Leistungen')['spots'])->keyBy('path');

        $this->assertSame(['required' => true, 'max_chars' => 300], $spots['intro']['need']);
        $this->assertSame(46, $spots['intro']['length']);
        $this->assertSame(['max_files' => 1, 'width' => 2600, 'ratio' => '2:1'], $spots['image.0']['need']);

        $team = collect($data['needs']['collections'])->firstWhere('handle', 'team');
        $this->assertSame(3, $team['entries']);
        $this->assertSame(2, $team['demo_entries']);
        $this->assertSame(['Vorname', 'Foto'], array_column($team['fields'], 'label'));
        $this->assertSame(['max_files' => 1, 'width' => 1000, 'hint' => 'Hochformat, ruhiger Hintergrund'], $team['fields'][1]['need']);
        $this->assertNotNull(collect($data['needs']['collections'])->firstWhere('handle', 'pages'));

        $impressum = collect($data['needs']['globals'])->firstWhere('handle', 'impressum');
        $this->assertSame(['demo', 'empty', 'filled'], array_column($impressum['fields'], 'state'));
        $this->assertSame(['Musterfirma GmbH', '', 'Amtsgericht Freiburg'], array_column($impressum['fields'], 'text'));

        /* Seitenbäume stehen schon über die Seiten. */
        Collection::find('pages')->structureContents(['root' => true])->save();
        $this->assertNull(collect($this->content()->json('needs.collections'))->firstWhere('handle', 'pages'));

        Collection::find('team')?->delete();
        GlobalSet::find('impressum')?->delete();
    }

    public function test_repeatable_fields_with_count_and_limit(): void
    {
        Blueprint::make('stelle')->setNamespace('collections.pages')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'aufgaben', 'field' => ['type' => 'list', 'display' => 'Aufgaben']],
            ['handle' => 'erwartungen', 'field' => ['type' => 'list', 'display' => 'Erwartungen']],
            ['handle' => 'zahlen', 'field' => ['type' => 'grid', 'display' => 'Zahlen', 'max_rows' => 4, 'fields' => [
                ['handle' => 'zahl', 'field' => ['type' => 'text', 'display' => 'Zahl']],
                ['handle' => 'bild', 'field' => ['type' => 'assets', 'container' => 'assets', 'max_files' => 1]],
                ['handle' => 'farbe', 'field' => ['type' => 'color']],
            ]]],
            ['handle' => 'galerie', 'field' => ['type' => 'assets', 'container' => 'assets', 'display' => 'Galerie', 'max_files' => 6]],
            ['handle' => 'intern', 'field' => ['type' => 'list', 'wm_need' => false]],
        ]]]]]])->save();
        Entry::make()->collection('pages')->blueprint('stelle')->slug('gaertner')->data([
            'title' => 'Gärtner',
            'aufgaben' => ['Aufgabe 1', 'Aufgabe 2'],
            'zahlen' => [['zahl' => '25'], ['zahl' => '300']],
        ])->save();

        $lists = collect($this->content()->assertOk()->json('pages.0.lists'))->keyBy('path');

        $this->assertSame(['aufgaben', 'erwartungen', 'zahlen', 'galerie'], $lists->keys()->all());
        $this->assertSame(['type' => 'list', 'count' => 2], array_intersect_key($lists['aufgaben'], ['type' => 1, 'count' => 1]));
        $this->assertSame(0, $lists['erwartungen']['count']);
        $this->assertSame('Aufgaben', $lists['aufgaben']['field']);
        $this->assertSame(['grid', 2, 4], [$lists['zahlen']['type'], $lists['zahlen']['count'], $lists['zahlen']['max']]);
        $this->assertSame([['zahl', 'text'], ['bild', 'image']], array_map(fn (array $field): array => [$field['handle'], $field['kind']], $lists['zahlen']['fields']));
        $this->assertSame(['assets', 0, 6], [$lists['galerie']['type'], $lists['galerie']['count'], $lists['galerie']['max']]);
    }

    public function test_pages_follow_the_navigation(): void
    {
        Collection::make('nav')->routes('/{slug}')->structureContents(['root' => false])->save();
        $zebra = Entry::make()->collection('nav')->slug('zebra')->data(['title' => 'Zebra']);
        $zebra->save();
        $alpha = Entry::make()->collection('nav')->slug('alpha')->data(['title' => 'Alpha']);
        $alpha->save();
        Collection::find('nav')->structure()->in('default')->tree([['entry' => $zebra->id()], ['entry' => $alpha->id()]])->save();
        Collection::make('news')->routes('/zebra/{slug}')->mount($zebra->id())->save();
        Entry::make()->collection('news')->slug('neu')->data(['title' => 'Neu'])->save();

        $this->assertSame(['Zebra', 'Neu', 'Alpha'], array_column($this->content()->json('pages'), 'title'));

        Collection::find('news')?->delete();
        Collection::find('nav')?->delete();
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
