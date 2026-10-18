<?php

namespace WursterMedien\Consent\Core;

/**
 * Was in den <head> jeder Seite gehört (Statamic: {{ wm_consent }}, MODX: [[!wmConsent]]): Stylesheet, auf Wunsch
 * Google Consent Mode „abgelehnt“ als Voreinstellung, die Einstellungen der Seite als JSON (id="wm-consent-config",
 * Merkmal data-wm-consent für die Prüfung im Kundenbereich), auf Wunsch Google Analytics schon blockiert und das
 * Skript des Banners.
 *
 * Einstellungen (snake_case, gleich für Statamic-Config und MODX-Chunk), alles optional:
 * mode (auto|light|dark), position (POSITIONS), blur (Hintergrund unscharf und gesperrt bis zur Auswahl, Standard an),
 * blur_strength (Unschärfe in px, 0–30, Standard 8), overlay_opacity (Deckkraft der Fläche über der Seite in %, 0–90,
 * Standard 50), colors.light|dark.background|text|highlight (Nebenflächen, Nebentext, Linien und die Fläche über der
 * Seite ergeben sich daraus mit Transparenz), privacy_url,
 * imprint_url, texts (Ersetzungen, siehe resources/js/texts.js), services (zusätzlich anzeigen), revision (erhöhen =
 * neu fragen), consent_mode (Standard an), cookie_days, floating_button, google_analytics_id, load_once (Platzhalter
 * zusätzlich mit „Einmal laden“ neben „Immer laden“, Standard aus), cookies (eigene Einträge der Cookie-Liste,
 * siehe cookies()).
 *
 * Mehrere Websites und Sprachen: lang (de, en_GB …; ohne Angabe nimmt das Skript <html lang>), site (Schlüssel der
 * aktuellen Website: Statamic-Site, MODX-Kontext) und sites.<site> mit Werten, die für diese Website vorgehen
 * (SITE_KEYS, dazu texts). texts flach (alle Sprachen) oder je Sprache ({"de": {…}, "en": {…}}), auch gemischt.
 * Beschreibung und Dauer eigener Cookies ebenso als Text oder je Sprache.
 *
 * PHP 7.4-tauglich.
 */
final class HeadTag
{
    public const VERSION = '1.0.0';

    /** Wo der Banner steht (`position`), wie bei CookieConsent: oben, Mitte, unten jeweils links, mittig, rechts. */
    public const POSITIONS = ['top left', 'top center', 'top right', 'middle left', 'middle center', 'middle right', 'bottom left', 'bottom center', 'bottom right'];

    /** Was eine Website (`sites.<site>`) neben texts selbst festlegen kann; leer heißt: wie für alle. */
    public const SITE_KEYS = ['lang', 'privacy_url', 'imprint_url', 'google_analytics_id'];

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function render(array $settings, string $assetUrl, ?string $version = null): string
    {
        $assetUrl = rtrim($assetUrl, '/');
        $version = rawurlencode($version ?? self::VERSION);
        $settings = self::resolve($settings);
        $config = self::build($settings);
        $analyticsId = self::analyticsId($settings['google_analytics_id'] ?? null);

        $html = '<link rel="stylesheet" href="'.self::escape($assetUrl.'/wm-consent.css?v='.$version).'" data-wm-consent-ignore>'."\n";

        if ($config['consentMode']) {
            $html .= '<script data-wm-consent-ignore>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
                ."gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});</script>\n";
        }

        $html .= '<script type="application/json" id="wm-consent-config" data-wm-consent>'.self::json($config).'</script>'."\n";

        if ($analyticsId !== null) {
            $html .= '<script type="text/plain" data-category="analytics" data-service="google_analytics" async src="https://www.googletagmanager.com/gtag/js?id='.$analyticsId.'"></script>'."\n"
                .'<script type="text/plain" data-category="analytics" data-service="google_analytics">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
                ."gtag('js',new Date());gtag('config','".$analyticsId."');</script>\n";
        }

        return $html.'<script src="'.self::escape($assetUrl.'/wm-consent.js?v='.$version).'" defer data-wm-consent-ignore></script>'."\n";
    }

    /**
     * Einstellungen für das Skript, geprüft (Farben nur als Farbwerte, Links nur http(s) oder relativ, Dienste nur
     * bekannte).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function config(array $settings): array
    {
        return self::build(self::resolve($settings));
    }

    /**
     * Werte der aktuellen Website (`site`) über die allgemeinen legen. Texte als Map Sprache → Texte, '*' für alle
     * Sprachen; das Skript nimmt Standard, dann '*', dann die Sprache der Seite. Texte der Website gehen dabei vor.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function resolve(array $settings): array
    {
        $site = is_scalar($settings['site'] ?? null) ? (string) $settings['site'] : '';
        $overrides = $site !== '' && is_array($settings['sites'][$site] ?? null) ? $settings['sites'][$site] : [];

        foreach (self::SITE_KEYS as $key) {
            if (is_scalar($overrides[$key] ?? null) && trim((string) $overrides[$key]) !== '') {
                $settings[$key] = $overrides[$key];
            }
        }

        $global = self::texts($settings['texts'] ?? []);
        $own = self::texts($overrides['texts'] ?? []);
        $settings['texts'] = [];

        foreach (array_unique(array_merge(array_keys($global), array_keys($own))) as $lang) {
            $settings['texts'][$lang] = $lang === '*'
                ? array_replace($global['*'] ?? [], $own['*'] ?? [])
                : array_replace($global[$lang] ?? [], $own['*'] ?? [], $own[$lang] ?? []);
        }

        unset($settings['sites']);

        return $settings;
    }

    /**
     * Ersetzte Texte als Map Sprache → Schlüssel → Text: flache Einträge gelten für alle Sprachen ('*'), Einträge unter
     * einem Sprachkürzel nur für diese.
     *
     * @param  mixed  $texts
     * @return array<string, array<string, string>>
     */
    private static function texts($texts): array
    {
        $result = [];

        foreach ((array) $texts as $key => $value) {
            if (is_array($value) && Lang::code($key) === $key) {
                foreach ($value as $text => $content) {
                    if (is_scalar($content) && trim((string) $content) !== '') {
                        $result[$key][(string) $text] = (string) $content;
                    }
                }
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $result['*'][(string) $key] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $settings  mit resolve() aufgelöst
     * @return array<string, mixed>
     */
    private static function build(array $settings): array
    {
        $mode = in_array($settings['mode'] ?? 'auto', ['auto', 'light', 'dark'], true) ? $settings['mode'] ?? 'auto' : 'auto';
        $services = $settings['services'] ?? [];
        $services = is_string($services) ? preg_split('/[\s,|]+/', $services, -1, PREG_SPLIT_NO_EMPTY) : (array) $services;
        $services = array_values(array_intersect(array_map('strval', $services), Services::keys()));

        if (self::analyticsId($settings['google_analytics_id'] ?? null) !== null) {
            $services[] = 'google_analytics';
        }

        return [
            'lang' => Lang::code($settings['lang'] ?? null),
            'revision' => (int) ($settings['revision'] ?? 0),
            'mode' => $mode,
            'position' => in_array($settings['position'] ?? '', self::POSITIONS, true) ? $settings['position'] : 'bottom right',
            'blur' => (bool) ($settings['blur'] ?? true),
            'blurStrength' => max(0, min(30, (int) ($settings['blur_strength'] ?? 8))),
            'overlayOpacity' => max(0, min(90, (int) ($settings['overlay_opacity'] ?? 50))),
            'theme' => [
                'light' => self::colors((array) ($settings['colors']['light'] ?? [])),
                'dark' => self::colors((array) ($settings['colors']['dark'] ?? [])),
            ],
            'privacyUrl' => self::url($settings['privacy_url'] ?? null),
            'imprintUrl' => self::url($settings['imprint_url'] ?? null),
            'texts' => (object) $settings['texts'],
            'services' => array_values(array_unique($services)),
            'consentMode' => (bool) ($settings['consent_mode'] ?? true),
            'cookieDays' => max(1, min(365, (int) ($settings['cookie_days'] ?? 182))),
            'floatingButton' => (bool) ($settings['floating_button'] ?? false),
            'loadOnce' => (bool) ($settings['load_once'] ?? false),
            'cookies' => self::cookies((array) ($settings['cookies'] ?? [])),
        ];
    }

    /**
     * Eigene Einträge der Seite für die Cookie-Liste (zusätzlich zu den Vorlagen der Dienste): name, description,
     * duration (beide als Text oder je Sprache), optional provider mit privacy (Link zu dessen Datenschutz, http(s) oder
     * /pfad), category (Standard necessary) und type (cookie, local = Local Storage, session = Session Storage). Ohne
     * Namen fällt ein Eintrag weg, jeder Name einmal.
     *
     * @param  array<int, mixed>  $cookies
     * @return list<array<string, mixed>>
     */
    private static function cookies(array $cookies): array
    {
        $result = [];

        foreach ($cookies as $cookie) {
            if (! is_array($cookie)) {
                continue;
            }

            $name = trim((string) ($cookie['name'] ?? ''));
            $category = (string) ($cookie['category'] ?? 'necessary');
            $type = (string) ($cookie['type'] ?? 'cookie');

            if ($name === '' || isset($result[$name])) {
                continue;
            }

            $provider = mb_substr(trim((string) ($cookie['provider'] ?? '')), 0, 100);
            $privacy = trim(is_scalar($cookie['privacy'] ?? null) ? (string) $cookie['privacy'] : '');

            $result[$name] = [
                'name' => mb_substr($name, 0, 100),
                'description' => self::localized($cookie['description'] ?? '', 500),
                'duration' => self::localized($cookie['duration'] ?? '', 60),
                'provider' => $provider,
                'category' => in_array($category, Services::CATEGORIES, true) ? $category : 'necessary',
                'type' => in_array($type, ['cookie', 'local', 'session'], true) ? $type : 'cookie',
            ];

            if ($provider !== '' && preg_match('#^(https?://|/(?!/))#i', $privacy) === 1) {
                $result[$name]['privacy'] = mb_substr($privacy, 0, 300);
            }
        }

        return array_values($result);
    }

    /**
     * Text oder Map Sprache → Text (nur Sprachkürzel), gekürzt.
     *
     * @param  mixed  $value
     * @return string|array<string, string>
     */
    private static function localized($value, int $length)
    {
        if (! is_array($value)) {
            return mb_substr(trim(is_scalar($value) ? (string) $value : ''), 0, $length);
        }

        $result = [];

        foreach ($value as $lang => $text) {
            if (Lang::code($lang) === $lang && is_scalar($text)) {
                $result[$lang] = mb_substr(trim((string) $text), 0, $length);
            }
        }

        return $result;
    }

    /**
     * JSON, das sicher in einem <script>-Element steht.
     *
     * @param  array<string, mixed>  $config
     */
    public static function json(array $config): string
    {
        return (string) json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $colors
     * @return array<string, string>
     */
    private static function colors(array $colors): array
    {
        $result = [];
        foreach (['background', 'text', 'highlight'] as $key) {
            $value = trim((string) ($colors[$key] ?? ''));

            if (preg_match('/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([0-9.,%\s\/]+\))$/i', $value) === 1) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** @param  mixed  $url */
    private static function url($url): ?string
    {
        $url = trim((string) $url);

        return $url !== '' && preg_match('#^(https?://|/)[^\s"<>]*$#i', $url) === 1 ? $url : null;
    }

    /** @param  mixed  $id */
    private static function analyticsId($id): ?string
    {
        $id = strtoupper(trim((string) $id));

        return preg_match('/^G-[A-Z0-9]{4,12}$/', $id) === 1 ? $id : null;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
