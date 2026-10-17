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
 * blur_strength (Unschärfe in px, 0–30, Standard 8),
 * colors.light|dark.background|text|highlight|highlight_text|muted (Nebenflächen)|muted_text (Nebentext)|border|overlay
 * (Farbe über der Seite, mit Transparenz, z. B. rgba(15, 23, 42, .5))|heading (Überschriften), privacy_url,
 * imprint_url, texts (Ersetzungen, siehe resources/js/texts.js), services (zusätzlich anzeigen), revision (erhöhen =
 * neu fragen), consent_mode (Standard an), cookie_days, floating_button, google_analytics_id, load_once (Platzhalter
 * zusätzlich mit „Einmal laden“ neben „Immer laden“, Standard aus), cookies (eigene Einträge der Cookie-Liste,
 * siehe cookies()).
 *
 * PHP 7.4-tauglich.
 */
final class HeadTag
{
    public const VERSION = '1.0.0';

    /** Wo der Banner steht (`position`), wie bei CookieConsent: oben, Mitte, unten jeweils links, mittig, rechts. */
    public const POSITIONS = ['top left', 'top center', 'top right', 'middle left', 'middle center', 'middle right', 'bottom left', 'bottom center', 'bottom right'];

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function render(array $settings, string $assetUrl, ?string $version = null): string
    {
        $assetUrl = rtrim($assetUrl, '/');
        $version = rawurlencode($version ?? self::VERSION);
        $config = self::config($settings);
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
        $mode = in_array($settings['mode'] ?? 'auto', ['auto', 'light', 'dark'], true) ? $settings['mode'] ?? 'auto' : 'auto';
        $services = $settings['services'] ?? [];
        $services = is_string($services) ? preg_split('/[\s,|]+/', $services, -1, PREG_SPLIT_NO_EMPTY) : (array) $services;
        $services = array_values(array_intersect(array_map('strval', $services), Services::keys()));

        if (self::analyticsId($settings['google_analytics_id'] ?? null) !== null) {
            $services[] = 'google_analytics';
        }

        return [
            'revision' => (int) ($settings['revision'] ?? 0),
            'mode' => $mode,
            'position' => in_array($settings['position'] ?? '', self::POSITIONS, true) ? $settings['position'] : 'bottom right',
            'blur' => (bool) ($settings['blur'] ?? true),
            'blurStrength' => max(0, min(30, (int) ($settings['blur_strength'] ?? 8))),
            'theme' => [
                'light' => self::colors((array) ($settings['colors']['light'] ?? [])),
                'dark' => self::colors((array) ($settings['colors']['dark'] ?? [])),
            ],
            'privacyUrl' => self::url($settings['privacy_url'] ?? null),
            'imprintUrl' => self::url($settings['imprint_url'] ?? null),
            'texts' => array_map('strval', array_filter((array) ($settings['texts'] ?? []), 'is_scalar')),
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
     * duration, optional provider, category (Standard necessary) und type (cookie, local = Local Storage, session =
     * Session Storage). Ohne Namen fällt ein Eintrag weg, jeder Name einmal.
     *
     * @param  array<int, mixed>  $cookies
     * @return list<array{name: string, description: string, duration: string, provider: string, category: string, type: string}>
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

            $result[$name] = [
                'name' => mb_substr($name, 0, 100),
                'description' => mb_substr(trim((string) ($cookie['description'] ?? '')), 0, 500),
                'duration' => mb_substr(trim((string) ($cookie['duration'] ?? '')), 0, 60),
                'provider' => mb_substr(trim((string) ($cookie['provider'] ?? '')), 0, 100),
                'category' => in_array($category, Services::CATEGORIES, true) ? $category : 'necessary',
                'type' => in_array($type, ['cookie', 'local', 'session'], true) ? $type : 'cookie',
            ];
        }

        return array_values($result);
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
        $keys = ['background' => 'background', 'text' => 'text', 'highlight' => 'highlight', 'highlight_text' => 'highlightText', 'muted' => 'muted', 'muted_text' => 'mutedText', 'border' => 'border', 'overlay' => 'overlay', 'heading' => 'heading'];

        foreach ($keys as $key => $name) {
            $value = trim((string) ($colors[$key] ?? ''));

            if (preg_match('/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([0-9.,%\s\/]+\))$/i', $value) === 1) {
                $result[$name] = $value;
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
