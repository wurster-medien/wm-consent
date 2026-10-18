<?php

namespace WursterMedien\Consent\Tests\Statamic;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use WursterMedien\Consent\ServiceProvider;
use WursterMedien\Consent\Settings;

class AddonTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.url', 'https://kunde.test');
        $app['config']->set('wm-consent.colors.light.highlight', '#c2410c');
        $app['config']->set('wm-consent.settings_path', dirname(__DIR__).'/__fixtures__/content/wm-consent.yaml');
        $app['config']->set('statamic.editions.pro', true);
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory(self::views());
        $this->app['files']->delete(config('wm-consent.settings_path'));

        parent::tearDown();
    }

    public function test_the_head_tag_renders_banner_and_settings(): void
    {
        $html = $this->render('{{ wm_consent }}');

        $this->assertStringContainsString('<script src="/wm-consent/wm-consent.js?v=', $html);
        $this->assertStringContainsString('"highlight":"#c2410c"', $html);
        $this->assertStringContainsString('data-wm-consent>', $html);
        $this->assertStringContainsString('"name":"XSRF-TOKEN","description":{"de":"Schützt Formulare', $html);
        $this->assertStringContainsString('{"lang":"en",', $html);
        $this->assertStringContainsString('"name":"'.config('session.cookie').'"', $html);
    }

    public function test_parameters_replace_settings_per_call(): void
    {
        $html = $this->render('{{ wm_consent google_analytics_id="G-FOTO1234" services="youtube" }}');

        $this->assertStringContainsString('gtag/js?id=G-FOTO1234', $html);
        $this->assertStringContainsString('"services":["youtube","google_analytics"]', $html);
    }

    public function test_link_and_script_pairs(): void
    {
        $this->assertSame('<a href="#cookie-einstellungen" data-wm-consent-open class="footer-link">Cookies</a>', $this->render('{{ wm_consent:link class="footer-link" }}Cookies{{ /wm_consent:link }}'));
        $this->assertSame('<script type="text/plain" data-category="marketing" data-service="linkedin" async src="x.js"></script>', $this->render('{{ wm_consent:script service="linkedin" }}<script type="text/javascript" async src="x.js"></script>{{ /wm_consent:script }}'));
    }

    public function test_pages_are_filtered_and_the_scripts_are_served(): void
    {
        $head = $this->render('{{ wm_consent }}');
        Route::middleware('statamic.web')->get('/test-seite', fn () => response('<html><head>'.$head.'</head><body><iframe src="https://www.youtube.com/embed/abc"></iframe></body></html>'));
        $this->moveCatchAllLast();

        $this->get('/test-seite')
            ->assertOk()
            ->assertSee('<div data-service="youtube" data-id="https://www.youtube-nocookie.com/embed/abc" data-autoscale></div>', false)
            ->assertSee('"detected":["youtube"]', false);

        $this->get('/wm-consent/wm-consent.js')->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->get('/wm-consent/geheim.php')->assertNotFound();
    }

    public function test_switched_off_means_no_banner_and_no_filter(): void
    {
        config(['wm-consent.enabled' => false]);
        Route::middleware('statamic.web')->get('/aus', fn () => response('<iframe src="https://www.youtube.com/embed/abc"></iframe>'));
        $this->moveCatchAllLast();

        $this->assertSame('', $this->render('{{ wm_consent }}'));
        $this->get('/aus')->assertSee('<iframe src="https://www.youtube.com/embed/abc"></iframe>', false);
    }

    public function test_the_control_panel_page_saves_settings_that_reach_the_banner(): void
    {
        $this->actingAs(User::make()->email('agentur@kunde.test')->makeSuper()->save());

        $this->get(cp_route('wm-consent.edit'))->assertOk();

        $form = app(Settings::class)->formValues();
        $this->assertSame('#c2410c', $form['light_highlight']);

        $this->patchJson(cp_route('wm-consent.update'), [
            ...$form,
            'mode' => 'dark',
            'position' => 'middle center',
            'dark_background' => '#1e293b',
            'overlay_opacity' => 70,
            'text_en_title' => 'Cookies bei uns',
            'services' => ['youtube'],
            'cookies' => [['name' => 'warenkorb', 'type' => 'local', 'category' => 'necessary', 'duration' => 'bis zum Löschen', 'description' => 'Auswahl']],
            'reconsent' => true,
        ])->assertOk();

        $html = $this->render('{{ wm_consent }}');
        $this->assertStringContainsString('"mode":"dark","position":"middle center"', $html);
        $this->assertStringContainsString('"background":"#1e293b"', $html);
        $this->assertStringContainsString('"overlayOpacity":70', $html);
        $this->assertStringContainsString('"highlight":"#c2410c"', $html);
        $this->assertStringContainsString('"texts":{"en":{"title":"Cookies bei uns"}}', $html);
        $this->assertStringContainsString('"revision":1', $html);
        $this->assertStringContainsString('"name":"warenkorb"', $html);
        $this->assertSame(['youtube'], app(Settings::class)->get('services'));
    }

    public function test_several_sites_get_their_own_links_analytics_and_texts_per_language(): void
    {
        Site::setSites([
            'site1_de' => ['name' => 'Site 1', 'url' => '/', 'locale' => 'de_DE'],
            'site1_en' => ['name' => 'Site 1 English', 'url' => '/en/', 'locale' => 'en_GB'],
            'site2_fr' => ['name' => 'Site 2', 'url' => 'https://site2.test/', 'locale' => 'fr_FR'],
        ]);
        $this->actingAs(User::make()->email('agentur@kunde.test')->makeSuper()->save());

        $this->get(cp_route('wm-consent.edit'))->assertOk();
        $this->assertSame(['de', 'en', 'fr'], Settings::languages());
        $this->assertArrayHasKey('sites', app(Settings::class)->blueprint()->contents()['tabs']);

        $this->patchJson(cp_route('wm-consent.update'), [
            ...app(Settings::class)->formValues(),
            'text_de_title' => 'Ihre Daten',
            'text_en_title' => 'Your data',
            'site_site1_en_privacy_url' => '/en/privacy',
            'site_site2_fr_google_analytics_id' => 'G-SITE2FR1',
        ])->assertOk();

        $this->assertSame(['site1_en' => ['privacy_url' => '/en/privacy'], 'site2_fr' => ['google_analytics_id' => 'G-SITE2FR1']], app(Settings::class)->get('sites'));
        $this->assertSame('/en/privacy', app(Settings::class)->formValues()['site_site1_en_privacy_url']);

        Site::setCurrent('site1_en');
        $html = $this->render('{{ wm_consent }}');
        $this->assertStringContainsString('{"lang":"en",', $html);
        $this->assertStringContainsString('"privacyUrl":"/en/privacy"', $html);
        $this->assertStringContainsString('"texts":{"de":{"title":"Ihre Daten"},"en":{"title":"Your data"}}', $html);
        $this->assertStringNotContainsString('gtag/js', $html);
        $this->assertSame('<a href="#cookie-einstellungen" data-wm-consent-open>Cookie settings</a>', $this->render('{{ wm_consent:link }}'));

        Site::setCurrent('site2_fr');
        $html = $this->render('{{ wm_consent }}');
        $this->assertStringContainsString('{"lang":"fr",', $html);
        $this->assertStringContainsString('"privacyUrl":"/datenschutz"', $html);
        $this->assertStringContainsString('gtag/js?id=G-SITE2FR1', $html);
    }

    public function test_texts_saved_before_languages_show_up_in_the_main_language(): void
    {
        $this->app['files']->put(config('wm-consent.settings_path'), "texts:\n  title: 'Alter Titel'\n");

        $this->assertSame('Alter Titel', app(Settings::class)->formValues()['text_en_title']);
        $this->assertArrayNotHasKey('sites', app(Settings::class)->blueprint()->contents()['tabs']);
    }

    public function test_only_people_with_the_permission_open_the_page(): void
    {
        $this->actingAs(User::make()->email('redaktion@kunde.test')->save());

        $this->assertContains($this->get(cp_route('wm-consent.edit'))->status(), [302, 403]);
        $this->assertContains($this->patchJson(cp_route('wm-consent.update'), ['mode' => 'dark'])->status(), [302, 401, 403]);
        $this->assertFileDoesNotExist(config('wm-consent.settings_path'));
    }

    /** Wie eine echte View-Datei rendern (Antlers::parse() behandelt Text in Statamic 6 als Nutzerinhalt ohne Tags). */
    private function render(string $template): string
    {
        $name = 'wm_consent_'.md5($template);
        $this->app['files']->ensureDirectoryExists(self::views());
        $this->app['files']->put(self::views()."/{$name}.antlers.html", $template);
        View::addLocation(self::views());

        return trim(view($name)->render());
    }

    /** Test-Routen kommen nach Statamics Sammel-Route {segments?}; diese ans Ende, damit die Test-Route greift. */
    private function moveCatchAllLast(): void
    {
        $routes = $this->app['router']->getRoutes();
        $all = $routes->getRoutes();
        $collection = new \Illuminate\Routing\RouteCollection;

        foreach (array_merge(array_filter($all, fn ($route) => $route->uri() !== '{segments?}'), array_filter($all, fn ($route) => $route->uri() === '{segments?}')) as $route) {
            $collection->add($route);
        }

        $this->app['router']->setRoutes($collection);
    }

    private static function views(): string
    {
        return dirname(__DIR__).'/__fixtures__/views';
    }
}
