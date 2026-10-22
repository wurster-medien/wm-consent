<?php

namespace WursterMedien\Consent;

use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use WursterMedien\Consent\Console\Prune;
use WursterMedien\Consent\Http\Middleware\FilterOutput;
use WursterMedien\Consent\Support\StatamicVersion;
use WursterMedien\Consent\Tags\WmConsent;

/**
 * Cookie-Banner von Wurster Medien für Statamic 5 und 6: Tag {{ wm_consent }} für den <head>, Ausgabefilter in der
 * Gruppe statamic.web (innen, damit der statische Cache schon das gefilterte HTML speichert), Skript und Stylesheet
 * über eine eigene Route (kein Veröffentlichen nach Updates nötig, Stylesheet zentral über AgencyStyles), Versionen für
 * den Kundenbereich über POST /wm-consent/status (StatusController), Einstellungen im Control Panel unter Werkzeuge →
 * „Cookie-Banner“ (Settings, gespeichert in content/wm-consent.yaml).
 */
class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        WmConsent::class,
    ];

    protected $commands = [
        Prune::class,
    ];

    protected $routes = [
        'web' => __DIR__.'/../routes/web.php',
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    protected $viewNamespace = 'wm-consent';

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/wm-consent.php', 'wm-consent');
        $this->app->scoped(Settings::class);
        $this->app->scoped(AgencyDefaults::class);
        $this->app->scoped(AgencyStyles::class);
        $this->app->scoped(Detected::class);
        $this->app->singleton(StatamicVersion::class);
    }

    public function bootAddon(): void
    {
        $this->publishes([__DIR__.'/../config/wm-consent.php' => config_path('wm-consent.php')], 'wm-consent-config');

        $this->app['router']->pushMiddlewareToGroup('statamic.web', FilterOutput::class);

        Permission::extend(function (): void {
            Permission::register('manage wm consent')
                ->label('Cookie-Banner verwalten')
                ->description('Farben, Texte, Dienste und Cookies des Banners ändern');
        });

        Nav::extend(function ($nav): void {
            $nav->tools('Cookie-Banner')
                ->route('wm-consent.edit')
                ->icon('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 9 9 3 3 0 0 1-3.6-3.4A3 3 0 0 1 14.2 5 3 3 0 0 1 12 3Z"/><circle cx="8.5" cy="10.5" r=".8" fill="currentColor"/><circle cx="10" cy="15.5" r=".8" fill="currentColor"/><circle cx="15" cy="14.5" r=".8" fill="currentColor"/></svg>')
                ->can('manage wm consent');
        });
    }
}
