<?php

namespace WursterMedien\Consent\Core;

/**
 * Sprachen des Banners: Kürzel aus Locale oder lang-Attribut und die wenigen Texte, die schon in PHP entstehen
 * (Session- und XSRF-Cookie, Link „Cookie-Einstellungen“). Texte als Map Sprache → Text; das Skript nimmt die
 * Sprache der Seite (resources/js/texts.js, dort auch alle übrigen Texte). PHP 7.4-tauglich.
 */
final class Lang
{
    /** Sprachen mit eigenen Standardtexten; andere bekommen Englisch. */
    public const LANGUAGES = ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français'];

    private const TEXTS = [
        'session' => [
            'de' => 'Hält Ihre Sitzung auf dieser Website, z. B. für Formulare und Anmeldung.',
            'en' => 'Keeps your session on this website, e.g. for forms and login.',
            'fr' => 'Maintient votre session sur ce site, p. ex. pour les formulaires et la connexion.',
        ],
        'xsrf' => [
            'de' => 'Schützt Formulare vor Missbrauch durch fremde Seiten (CSRF-Schutz).',
            'en' => 'Protects forms against misuse by other websites (CSRF protection).',
            'fr' => 'Protège les formulaires contre les abus par d’autres sites (protection CSRF).',
        ],
        'end_of_session' => ['de' => 'Ende der Sitzung', 'en' => 'End of session', 'fr' => 'Fin de la session'],
        'link' => ['de' => 'Cookie-Einstellungen', 'en' => 'Cookie settings', 'fr' => 'Paramètres des cookies'],
    ];

    /**
     * Zweibuchstabiges Kürzel aus de, de_DE, de-AT … (null, wenn keins erkennbar).
     *
     * @param  mixed  $value
     */
    public static function code($value): ?string
    {
        return is_scalar($value) && preg_match('/^([a-z]{2})(?:[_-][a-z0-9]+)*$/i', trim((string) $value), $match) === 1 ? strtolower($match[1]) : null;
    }

    /**
     * Ein Text in allen Sprachen.
     *
     * @return array<string, string>
     */
    public static function all(string $key): array
    {
        return self::TEXTS[$key];
    }

    /** Ein Text in einer Sprache (andere Sprachen: Englisch, ohne Sprache: Deutsch). */
    public static function get(string $key, ?string $lang): string
    {
        return self::TEXTS[$key][$lang ?? 'de'] ?? self::TEXTS[$key]['en'];
    }

    /**
     * Laufzeit in Minuten als Text in allen Sprachen (0: Ende der Sitzung).
     *
     * @return array<string, string>
     */
    public static function minutes(int $minutes): array
    {
        if ($minutes <= 0) {
            return self::TEXTS['end_of_session'];
        }

        $hours = $minutes % 60 === 0 ? intdiv($minutes, 60) : null;

        return $hours !== null
            ? ['de' => $hours.($hours === 1 ? ' Stunde' : ' Stunden'), 'en' => $hours.($hours === 1 ? ' hour' : ' hours'), 'fr' => $hours.($hours === 1 ? ' heure' : ' heures')]
            : ['de' => $minutes.' Minuten', 'en' => $minutes.' minutes', 'fr' => $minutes.' minutes'];
    }
}
