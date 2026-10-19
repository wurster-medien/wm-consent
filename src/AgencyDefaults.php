<?php

namespace WursterMedien\Consent;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use WursterMedien\Consent\Core\HeadTag;

/**
 * Voreinstellungen der Agentur für alle Seiten (Simon, 20.10.2026: „mitte mitte als Standard, ohne jede Seite pushen und
 * deployen zu müssen“). Kommen aus einer JSON-Datei (`wm-consent.defaults_url`, Standard: defaults.json in diesem Repo
 * auf GitHub) und gehen vor config/wm-consent.php der Seite, aber nicht vor dem, was im Control Panel gespeichert ist.
 * Nur allgemeine Werte (KEYS), nie Farben, Links oder Analytics; der Banner muss auch ohne die Datei laufen.
 *
 * Geholt wird höchstens einmal je Stunde (`wm-consent.defaults_ttl`), mit kurzem Timeout; scheitert es, gilt der letzte
 * bekannte Stand weiter (im Cache ohne Ablauf).
 */
class AgencyDefaults
{
    public const URL = 'https://raw.githubusercontent.com/wurster-medien/wm-consent/main/defaults.json';

    /** Was die Agentur für alle Seiten vorgeben kann. */
    public const KEYS = ['position', 'blur', 'blur_strength', 'overlay_opacity', 'floating_button', 'load_once', 'consent_mode', 'cookie_days'];

    private const CACHE_KEY = 'wm-consent.agency-defaults';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $url = config('wm-consent.defaults_url', self::URL);

        if (! is_string($url) || $url === '') {
            return $this->values = [];
        }

        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            $cached = null;
        }

        $fresh = is_array($cached) && ($cached['url'] ?? null) === $url && (int) ($cached['checked'] ?? 0) > time() - (int) config('wm-consent.defaults_ttl', 3600);

        if ($fresh) {
            return $this->values = (array) ($cached['values'] ?? []);
        }

        $values = $this->fetch($url) ?? (is_array($cached) && ($cached['url'] ?? null) === $url ? (array) ($cached['values'] ?? []) : []);

        try {
            Cache::forever(self::CACHE_KEY, ['url' => $url, 'checked' => time(), 'values' => $values]);
        } catch (Throwable) {
            /* Ohne Cache gilt der Wert für diese Anfrage. */
        }

        return $this->values = $values;
    }

    /**
     * Nur bekannte Schlüssel mit gültigem Wert, null bei Fehler (dann bleibt der letzte Stand).
     *
     * @return array<string, mixed>|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $response = Http::timeout(3)->connectTimeout(2)->acceptJson()->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || ! is_array($response->json())) {
            return null;
        }

        return self::validate((array) $response->json());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function validate(array $data): array
    {
        $values = [];

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            $valid = match ($key) {
                'position' => is_string($value) && in_array($value, HeadTag::POSITIONS, true),
                'blur', 'floating_button', 'load_once', 'consent_mode' => is_bool($value),
                'blur_strength' => is_int($value) && $value >= 0 && $value <= 30,
                'overlay_opacity' => is_int($value) && $value >= 0 && $value <= 90,
                'cookie_days' => is_int($value) && $value >= 1 && $value <= 395,
                default => false,
            };

            if ($valid) {
                $values[$key] = $value;
            }
        }

        return $values;
    }
}
