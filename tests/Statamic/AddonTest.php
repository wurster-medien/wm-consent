<?php

namespace WursterMedien\Consent\Tests\Statamic;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Statamic\Testing\AddonTestCase;
use WursterMedien\Consent\ServiceProvider;

class AddonTest extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.url', 'https://kunde.test');
        $app['config']->set('wm-consent.colors.light.highlight', '#c2410c');
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory(self::views());

        parent::tearDown();
    }

    public function test_the_head_tag_renders_banner_and_settings(): void
    {
        $html = $this->render('{{ wm_consent }}');

        $this->assertStringContainsString('<script src="/wm-consent/wm-consent.js?v=', $html);
        $this->assertStringContainsString('"highlight":"#c2410c"', $html);
        $this->assertStringContainsString('data-wm-consent>', $html);
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
