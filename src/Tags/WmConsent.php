<?php

namespace WursterMedien\Consent\Tags;

use Statamic\Tags\Tags;
use WursterMedien\Consent\Core\HeadTag;
use WursterMedien\Consent\Core\Services;

/**
 * {{ wm_consent }} im <head> jedes Layouts: Banner, Einstellungen der Seite und auf Wunsch Google Analytics.
 * {{ wm_consent:link class="…" }}Cookie-Einstellungen{{ /wm_consent:link }} im Footer öffnet die Auswahl wieder.
 * {{ wm_consent:script service="google_analytics" }}<script>…</script>{{ /wm_consent:script }} blockt eigene Skripte,
 * die der Filter nicht kennt (oder mit category="analytics|marketing|media").
 */
class WmConsent extends Tags
{
    protected static $handle = 'wm_consent';

    public function index(): string
    {
        if (! config('wm-consent.enabled', true)) {
            return '';
        }

        $path = (string) parse_url(url('wm-consent'), PHP_URL_PATH);

        return HeadTag::render((array) config('wm-consent'), $path !== '' ? $path : '/wm-consent', self::version());
    }

    public function link(): string
    {
        $class = $this->params->get('class');

        return '<a href="#cookie-einstellungen" data-wm-consent-open'.($class ? ' class="'.e($class).'"' : '').'>'
            .($this->isPair ? $this->parse() : 'Cookie-Einstellungen').'</a>';
    }

    public function script(): string
    {
        $content = (string) $this->parse();

        if (! config('wm-consent.enabled', true)) {
            return $content;
        }

        $service = (string) $this->params->get('service', '');
        $category = (string) $this->params->get('category', Services::category($service) ?? 'analytics');
        $category = in_array($category, Services::CATEGORIES, true) ? $category : 'analytics';
        $serviceAttribute = $service !== '' ? ' data-service="'.e($service).'"' : '';

        return (string) preg_replace_callback('#<script\b([^>]*)>#i', function (array $match) use ($category, $serviceAttribute): string {
            $attributes = (string) preg_replace('/\stype\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $match[1]);

            return '<script type="text/plain" data-category="'.$category.'"'.$serviceAttribute.$attributes.'>';
        }, $content);
    }

    /** Version für die Adressen von Skript und Stylesheet: ändert sich mit jedem neuen Build. */
    public static function version(): string
    {
        static $version = null;

        if ($version === null) {
            $dist = dirname(__DIR__, 2).'/dist/';
            $version = substr(md5(HeadTag::VERSION.@filemtime($dist.'wm-consent.js').@filemtime($dist.'wm-consent.css').@filesize($dist.'wm-consent.js')), 0, 10);
        }

        return $version;
    }
}
