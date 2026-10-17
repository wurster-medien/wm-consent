<?php

namespace WursterMedien\Consent\Tags;

use Statamic\Tags\Tags;
use WursterMedien\Consent\Core\HeadTag;
use WursterMedien\Consent\Core\Services;
use WursterMedien\Consent\Settings;

/**
 * {{ wm_consent }} im <head> jedes Layouts: Banner, Einstellungen der Seite und auf Wunsch Google Analytics; einzelne
 * Einstellungen als Parameter (PARAMETERS), Session- und XSRF-Cookie stehen von selbst in der Cookie-Liste.
 * {{ wm_consent:link class="…" }}Cookie-Einstellungen{{ /wm_consent:link }} im Footer öffnet die Auswahl wieder.
 * {{ wm_consent:script service="google_analytics" }}<script>…</script>{{ /wm_consent:script }} blockt eigene Skripte,
 * die der Filter nicht kennt (oder mit category="analytics|marketing|media").
 */
class WmConsent extends Tags
{
    protected static $handle = 'wm_consent';

    /** Einstellungen, die der Tag je Aufruf ersetzen kann, z. B. {{ wm_consent :google_analytics_id="site:attributes:gtag" }} (Multisite). */
    private const PARAMETERS = ['google_analytics_id', 'privacy_url', 'imprint_url', 'mode', 'position', 'revision', 'services'];

    public function index(): string
    {
        if (! app(Settings::class)->get('enabled', true)) {
            return '';
        }

        $path = (string) parse_url(url('wm-consent'), PHP_URL_PATH);
        $settings = app(Settings::class)->all();

        foreach (self::PARAMETERS as $key) {
            if ($this->params->has($key)) {
                $settings[$key] = $this->params->get($key);
            }
        }

        $settings['cookies'] = [...self::laravelCookies(), ...(array) ($settings['cookies'] ?? [])];

        return HeadTag::render($settings, $path !== '' ? $path : '/wm-consent', self::version());
    }

    /**
     * Session- und XSRF-Cookie von Laravel mit Name und Laufzeit aus der Config (abschaltbar mit auto_cookies).
     *
     * @return list<array{name: string, duration: string, description: string}>
     */
    public static function laravelCookies(): array
    {
        if (! app(Settings::class)->get('auto_cookies', true)) {
            return [];
        }

        $minutes = (int) config('session.lifetime', 120);
        $duration = config('session.expire_on_close') ? 'Ende der Sitzung' : ($minutes % 60 === 0 ? ($minutes / 60).' Stunden' : $minutes.' Minuten');

        return [
            ['name' => (string) config('session.cookie', 'laravel_session'), 'duration' => $duration, 'description' => 'Hält Ihre Sitzung auf dieser Website, z. B. für Formulare und Anmeldung.'],
            ['name' => 'XSRF-TOKEN', 'duration' => $duration, 'description' => 'Schützt Formulare vor Missbrauch durch fremde Seiten (CSRF-Schutz).'],
        ];
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

        if (! app(Settings::class)->get('enabled', true)) {
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
