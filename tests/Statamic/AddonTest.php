<?php

namespace WursterMedien\Consent\Tests\Statamic;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use WursterMedien\Consent\Detected;
use WursterMedien\Consent\Http\Controllers\StatusController;
use WursterMedien\Consent\ServiceProvider;
use WursterMedien\Consent\Settings;

class AddonTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    /** @var array<string, mixed>|false|null Antwort der Agentur-Voreinstellungen im Test, false = Fehler */
    private array|false|null $agencyDefaults = null;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.url', 'https://kunde.test');
        $app['config']->set('wm-consent.colors.light.highlight', '#c2410c');
        $app['config']->set('wm-consent.settings_path', dirname(__DIR__).'/__fixtures__/content/wm-consent.yaml');
        $app['config']->set('wm-consent.detected_path', dirname(__DIR__).'/__fixtures__/storage/detected.json');
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('wm-consent.defaults_url', null);
        $app['config']->set('wm-consent.styles_url', null);
        $app['config']->set('wm-consent.styles_path', dirname(__DIR__).'/__fixtures__/storage/styles/styles.css');
        $app['config']->set('statamic.system.license_key', null);
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory(dirname((string) config('wm-consent.styles_path')));
        $this->app['files']->deleteDirectory(self::views());
        $this->app['files']->delete(config('wm-consent.settings_path'));
        $this->app['files']->delete(config('wm-consent.detected_path'));

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

    public function test_the_cookies_tab_lists_what_the_filter_found_until_it_is_reset(): void
    {
        $this->actingAs(User::make()->email('agentur@kunde.test')->makeSuper()->save());
        $head = $this->render('{{ wm_consent }}');
        Route::middleware('statamic.web')->get('/kontakt', fn () => response('<html><head>'.$head.'<script type="text/plain" data-category="marketing" data-service="linkedin" src="x.js"></script></head><body><iframe src="https://www.youtube.com/embed/abc"></iframe></body></html>'));
        $this->moveCatchAllLast();

        $overview = fn (): string => app(Settings::class)->blueprint()->field('cookie_overview')->config()['html'];
        $this->assertStringContainsString('Noch keine Dienste gefunden', $overview());
        $this->assertStringNotContainsString('YouTube', $overview());

        $this->get('/kontakt')->assertOk();
        $this->get('/kontakt')->assertOk();

        $this->assertSame(['youtube', 'linkedin'], array_keys(app(Detected::class)->all()));
        $this->assertSame(['url' => 'http://localhost/kontakt', 'last' => date('Y-m-d')], app(Detected::class)->all()['youtube']);
        $html = $overview();
        $this->assertStringContainsString('<strong>YouTube</strong>', $html);
        $this->assertStringContainsString('gefunden auf <a href="http://localhost/kontakt" target="_blank" rel="noopener">/kontakt</a>', $html);
        $this->assertStringContainsString('VISITOR_INFO1_LIVE, YSC', $html);
        $this->assertStringContainsString('<strong>LinkedIn Insight</strong>', $html);
        $this->assertStringContainsString('wm_consent_&lt;dienst&gt;', $html);
        $this->assertStringContainsString('XSRF-TOKEN', $html);
        $this->get(cp_route('wm-consent.edit'))->assertOk();

        $this->patchJson(cp_route('wm-consent.update'), [...app(Settings::class)->formValues(), 'reset_detected' => true])->assertOk();

        $this->assertSame([], app(Detected::class)->all());
        $this->assertFileDoesNotExist(config('wm-consent.detected_path'));
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
        $this->assertSame('Your privacy', app(Settings::class)->blueprint()->field('text_en_title')->config()['placeholder']);
        $this->assertSame('Votre vie privée', app(Settings::class)->blueprint()->field('text_fr_title')->config()['placeholder']);

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

    public function test_agency_defaults_go_over_the_config_but_not_over_saved_settings(): void
    {
        $this->agencyDefaults(['position' => 'middle center', 'blur_strength' => 12, 'privacy_url' => '/fremd', 'cookie_days' => 'viel']);

        $html = $this->render('{{ wm_consent }}');
        $this->assertStringContainsString('"position":"middle center"', $html);
        $this->assertStringContainsString('"blurStrength":12', $html);
        $this->assertStringNotContainsString('/fremd', $html);
        $this->assertStringContainsString('"highlight":"#c2410c"', $html);

        $this->actingAs(User::make()->email('agentur@kunde.test')->makeSuper()->save());
        $this->patchJson(cp_route('wm-consent.update'), [...app(Settings::class)->formValues(), 'position' => 'top left'])->assertOk();

        $this->assertSame('top left', app(Settings::class)->get('position'));
        Http::assertSentCount(1);
    }

    public function test_saving_keeps_only_what_differs_so_later_agency_defaults_still_apply(): void
    {
        $this->agencyDefaults(['position' => 'middle center']);
        $this->actingAs(User::make()->email('agentur@kunde.test')->makeSuper()->save());

        $this->patchJson(cp_route('wm-consent.update'), [...app(Settings::class)->formValues(), 'mode' => 'dark'])->assertOk();

        $stored = \Statamic\Facades\YAML::parse(file_get_contents(config('wm-consent.settings_path')));
        $this->assertSame('dark', $stored['mode']);
        $this->assertArrayNotHasKey('position', $stored);
        $this->assertArrayNotHasKey('colors', $stored);

        $this->app->forgetScopedInstances();
        Cache::flush();
        $this->agencyDefaults(['position' => 'bottom center']);
        $this->assertSame('bottom center', app(Settings::class)->get('position'));
    }

    public function test_prune_removes_saved_values_equal_to_the_defaults(): void
    {
        $this->agencyDefaults(['position' => 'middle center']);
        file_put_contents(config('wm-consent.settings_path'), "position: 'middle center'
mode: dark
colors:
  light:
    highlight: '#c2410c'
");

        $this->artisan('wm-consent:prune')->expectsOutput('2 Werte entfernt.')->assertSuccessful();

        $this->assertSame(['mode' => 'dark'], \Statamic\Facades\YAML::parse(file_get_contents(config('wm-consent.settings_path'))));
    }

    public function test_without_answer_the_last_known_agency_defaults_stay(): void
    {
        $this->agencyDefaults(['position' => 'middle center']);
        $this->assertSame('middle center', app(Settings::class)->get('position'));

        Cache::put('wm-consent.agency-defaults', [...Cache::get('wm-consent.agency-defaults'), 'checked' => 0]);
        $this->agencyDefaults = false;
        $this->app->forgetScopedInstances();

        $this->assertSame('middle center', app(Settings::class)->get('position'));
    }

    public function test_the_stylesheet_comes_from_the_agency_and_is_served_by_the_site(): void
    {
        $central = "#cc-main{color:red}.cm{background:url(\"data:image/svg+xml,%3Csvg%3E\")}";
        $answer = $central;
        config(['wm-consent.styles_url' => 'https://agentur.test/styles.css']);
        Http::fake(['agentur.test/*' => function () use (&$answer) {
            return $answer === false ? Http::response('', 500) : Http::response($answer);
        }]);

        $html = $this->render('{{ wm_consent }}');
        $version = 'c'.substr(md5($central), 0, 10);

        $this->assertStringContainsString('<link rel="stylesheet" href="/wm-consent/wm-consent.css?v='.$version.'"', $html);
        $this->assertStringNotContainsString('wm-consent.js?v='.$version, $html);
        $response = $this->get('/wm-consent/wm-consent.css?v='.$version)->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->assertSame($central, file_get_contents($response->baseResponse->getFile()->getPathname()));
        /* Seite aus dem statischen Cache mit alter Version: nur kurz zwischenspeichern. */
        $this->get('/wm-consent/wm-consent.css?v=alt')->assertOk()->assertHeader('Cache-Control', 'max-age=3600, public');

        /* Unbrauchbares (fremde Adressen, @import) und Fehler ändern nichts, der letzte gute Stand bleibt. */
        foreach (['#cc-main{background:url(https://tracker.test/pixel.gif)}', '@import "https://fremd.test/x.css";#cc-main{}', '<html>Fehler</html>', false] as $answer) {
            $this->expireStyles();
            $this->assertStringContainsString('wm-consent.css?v='.$version, $this->render('{{ wm_consent }}'));
        }

        /* Abgeschaltet: wieder das aus dem Paket. */
        config(['wm-consent.styles_url' => null]);
        $this->app->forgetScopedInstances();
        $this->assertStringNotContainsString('wm-consent.css?v=c', $this->render('{{ wm_consent }}'));
        $response = $this->get('/wm-consent/wm-consent.css')->assertOk();
        $this->assertSame(realpath(dirname(__DIR__, 2).'/dist/wm-consent.css'), realpath($response->baseResponse->getFile()->getPathname()));
    }

    public function test_the_agency_reads_the_versions_only_with_a_valid_signature(): void
    {
        $this->post('/wm-consent/status')->assertNotFound();

        config(['statamic.system.license_key' => 'lizenz-123']);
        $time = time();
        $signed = fn (string $key, int $timestamp) => ['X-WM-Timestamp' => (string) $timestamp, 'X-WM-Signature' => StatusController::signature($key, $timestamp)];

        $this->post('/wm-consent/status', [], $signed('falsch', $time))->assertNotFound();
        $this->post('/wm-consent/status', [], $signed('lizenz-123', $time - 900))->assertNotFound();
        $this->get('/wm-consent/status', $signed('lizenz-123', $time))->assertNotFound();

        $response = $this->post('/wm-consent/status', [], $signed('lizenz-123', $time))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('statamic', \Statamic\Statamic::version())
            ->assertJsonPath('edition', 'pro')
            ->assertJsonPath('php', PHP_VERSION)
            ->assertJsonPath('styles.central', false);

        $this->assertSame([], $response->headers->getCookies());
        $this->assertArrayHasKey('laravel', $response->json());

        /* Eigener Schlüssel geht vor dem Lizenzschlüssel. */
        config(['wm-consent.status_key' => 'eigen']);
        $this->post('/wm-consent/status', [], $signed('lizenz-123', $time))->assertNotFound();
        $this->post('/wm-consent/status', [], $signed('eigen', $time))->assertOk();
    }

    public function test_only_people_with_the_permission_open_the_page(): void
    {
        $this->actingAs(User::make()->email('redaktion@kunde.test')->save());

        $this->assertContains($this->get(cp_route('wm-consent.edit'))->status(), [302, 403]);
        $this->assertContains($this->patchJson(cp_route('wm-consent.update'), ['mode' => 'dark'])->status(), [302, 401, 403]);
        $this->assertFileDoesNotExist(config('wm-consent.settings_path'));
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private function agencyDefaults(array $defaults): void
    {
        config(['wm-consent.defaults_url' => 'https://agentur.test/defaults.json']);

        if ($this->agencyDefaults === null) {
            Http::fake(['agentur.test/*' => fn () => $this->agencyDefaults === false ? Http::response('', 500) : Http::response($this->agencyDefaults)]);
        }

        $this->agencyDefaults = $defaults;
    }

    /** Letzte Abfrage des zentralen Stylesheets eine Stunde zurück, damit der nächste Aufruf neu holt. */
    private function expireStyles(): void
    {
        $meta = config('wm-consent.styles_path').'.json';
        file_put_contents($meta, json_encode([...json_decode(file_get_contents($meta), true), 'checked' => time() - 7200]));
        $this->app->forgetScopedInstances();
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
