<?php

namespace WursterMedien\Consent;

use Statamic\Providers\AddonServiceProvider;
use WursterMedien\Consent\Http\Middleware\FilterOutput;
use WursterMedien\Consent\Tags\WmConsent;

/**
 * Cookie-Banner von Wurster Medien für Statamic 5 und 6: Tag {{ wm_consent }} für den <head>, Ausgabefilter in der
 * Gruppe statamic.web (innen, damit der statische Cache schon das gefilterte HTML speichert), Skript und Stylesheet
 * über eine eigene Route (kein Veröffentlichen nach Updates nötig).
 */
class ServiceProvider extends AddonServiceProvider
{
    protected $tags = [
        WmConsent::class,
    ];

    protected $routes = [
        'web' => __DIR__.'/../routes/web.php',
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/wm-consent.php', 'wm-consent');
    }

    public function bootAddon(): void
    {
        $this->publishes([__DIR__.'/../config/wm-consent.php' => config_path('wm-consent.php')], 'wm-consent-config');

        $this->app['router']->pushMiddlewareToGroup('statamic.web', FilterOutput::class);
    }
}
