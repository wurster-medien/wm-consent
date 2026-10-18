<?php

namespace WursterMedien\Consent\Core;

/**
 * Dienste, die erst nach Zustimmung laden dürfen, mit Kategorie und Mustern, an denen der Ausgabefilter sie erkennt.
 * Schlüssel und Kategorien wie in resources/js/services.js; neue Dienste an beiden Stellen eintragen.
 *
 * Ohne Abhängigkeiten und PHP 7.4-tauglich (MODX 2).
 */
final class Services
{
    /**
     * Skripte: Muster in src (externe, auch data-lazy-src u. ä.) bzw. im Inhalt (eingebettete). Reihenfolge zählt, der
     * erste Treffer gilt. Bewertungs-Widgets (Tripadvisor, HolidayCheck, wellness-hotel.info) laufen als „Externe
     * Medien“ ohne Platzhalter.
     *
     * @var array<string, array{category: string, src: list<string>, inline: list<string>}>
     */
    public const SCRIPTS = [
        'google_tag_manager' => ['category' => 'analytics', 'src' => ['googletagmanager.com/gtm.js'], 'inline' => ['googletagmanager.com/gtm.js']],
        'google_analytics' => ['category' => 'analytics', 'src' => ['googletagmanager.com/gtag/js', 'google-analytics.com/analytics.js', 'google-analytics.com/ga.js'], 'inline' => ['gtag(', 'GoogleAnalyticsObject', 'google-analytics.com']],
        'matomo' => ['category' => 'analytics', 'src' => ['matomo.js', 'piwik.js'], 'inline' => ['_paq']],
        'hotjar' => ['category' => 'analytics', 'src' => ['static.hotjar.com'], 'inline' => ['static.hotjar.com']],
        'clarity' => ['category' => 'analytics', 'src' => ['clarity.ms/tag'], 'inline' => ['clarity.ms/tag']],
        'meta_pixel' => ['category' => 'marketing', 'src' => ['fbevents.js'], 'inline' => ['fbevents.js', 'fbq(']],
        'linkedin' => ['category' => 'marketing', 'src' => ['snap.licdn.com'], 'inline' => ['snap.licdn.com', '_linkedin_partner_id']],
        'google_ads' => ['category' => 'marketing', 'src' => ['googleadservices.com', 'googlesyndication.com', 'doubleclick.net'], 'inline' => ['googleadservices.com', 'googlesyndication.com']],
        'tiktok' => ['category' => 'marketing', 'src' => ['analytics.tiktok.com'], 'inline' => ['analytics.tiktok.com']],
        'instagram' => ['category' => 'media', 'src' => ['instagram.com/embed.js'], 'inline' => []],
        'facebook' => ['category' => 'media', 'src' => ['connect.facebook.net'], 'inline' => []],
        'tripadvisor' => ['category' => 'media', 'src' => ['jscache.com/wejs', 'tripadvisor.com/WidgetEmbed', 'tripadvisor.de/WidgetEmbed'], 'inline' => []],
        'holidaycheck' => ['category' => 'media', 'src' => ['holidaycheck.de/widgets'], 'inline' => []],
        'wellness_hotel' => ['category' => 'media', 'src' => ['wellness-hotel.info/ratingswidget'], 'inline' => []],
    ];

    /**
     * Eingebettete Inhalte (iframes), die als Platzhalter mit „Laden“ erscheinen.
     *
     * @var array<string, list<string>>
     */
    public const FRAMES = [
        'youtube' => ['youtube.com/embed', 'youtube-nocookie.com/embed'],
        'vimeo' => ['player.vimeo.com'],
        'google_maps' => ['google.com/maps/embed', 'maps.google.', 'google.de/maps'],
        'instagram' => ['instagram.com/p/', 'instagram.com/reel/'],
        'facebook' => ['facebook.com/plugins'],
        'calendly' => ['calendly.com'],
        'openstreetmap' => ['openstreetmap.org/export/embed'],
    ];

    /** @var list<string> */
    public const CATEGORIES = ['necessary', 'analytics', 'marketing', 'media'];

    /**
     * Namen für Auswahllisten (Control Panel), wie in resources/js/services.js.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'google_analytics' => 'Google Analytics',
        'google_tag_manager' => 'Google Tag Manager',
        'matomo' => 'Matomo',
        'hotjar' => 'Hotjar',
        'clarity' => 'Microsoft Clarity',
        'meta_pixel' => 'Meta Pixel',
        'linkedin' => 'LinkedIn Insight',
        'google_ads' => 'Google Ads',
        'tiktok' => 'TikTok Pixel',
        'youtube' => 'YouTube',
        'vimeo' => 'Vimeo',
        'google_maps' => 'Google Maps',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'calendly' => 'Calendly',
        'openstreetmap' => 'OpenStreetMap',
        'tripadvisor' => 'Tripadvisor',
        'holidaycheck' => 'HolidayCheck',
        'wellness_hotel' => 'wellness-hotel.info',
    ];

    public static function category(string $service): ?string
    {
        if (isset(self::SCRIPTS[$service])) {
            return self::SCRIPTS[$service]['category'];
        }

        return isset(self::FRAMES[$service]) ? 'media' : null;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_values(array_unique(array_merge(array_keys(self::SCRIPTS), array_keys(self::FRAMES))));
    }

    /** Dienst eines Skripts nach src bzw. Inhalt, null ohne Treffer. */
    public static function forScript(string $src, string $inline): ?string
    {
        foreach (self::SCRIPTS as $key => $service) {
            foreach ($src !== '' ? $service['src'] : $service['inline'] as $pattern) {
                if (stripos($src !== '' ? $src : $inline, $pattern) !== false) {
                    return $key;
                }
            }
        }

        return null;
    }

    /** Dienst eines iframes nach src, null ohne Treffer. */
    public static function forFrame(string $src): ?string
    {
        foreach (self::FRAMES as $key => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($src, $pattern) !== false) {
                    return $key;
                }
            }
        }

        return null;
    }
}
