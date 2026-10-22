<?php

namespace WursterMedien\Consent;

use Illuminate\Support\Facades\Http;
use Throwable;
use WursterMedien\Consent\Tags\WmConsent;

/**
 * Stylesheet des Banners zentral von der Agentur (Simon, 22.10.2026: „Design-Updates zentral statt mit jedem
 * CMS-Update“). Der Server der Seite holt die Datei (`wm-consent.styles_url`, Standard: styles/1/wm-consent.css in
 * diesem Repo auf GitHub) höchstens einmal je Stunde (`wm-consent.styles_ttl`) und liefert sie selbst unter
 * /wm-consent/wm-consent.css aus. Der Browser lädt nie etwas von uns: keine IP der Besucher an uns, kein Warten auf
 * einen fremden Server.
 *
 * Gespeichert als Datei (`wm-consent.styles_path`, daneben .json mit Adresse, letzter Abfrage und Prüfsumme), damit ein
 * geleerter Cache nichts verliert. Scheitert die Abfrage oder ist die Datei unbrauchbar (zu groß, @import, fremde
 * url()), gilt der letzte gute Stand weiter, ohne jeden Stand dist/wm-consent.css aus dem Paket.
 *
 * Je Hauptversion eine Datei (MAJOR): styles/1 muss zum HTML jeder 1.x ab 1.2 passen. Ändert sich das HTML des
 * Banners so, dass altes CSS nicht mehr passt, kommt eine neue Hauptversion mit eigenem Ordner.
 */
class AgencyStyles
{
    public const MAJOR = 1;

    public const URL = 'https://raw.githubusercontent.com/wurster-medien/wm-consent/main/styles/1/wm-consent.css';

    private const MAX_BYTES = 300000;

    /** @var array{path: string, version: string, central: bool, checked_at: int|null}|null */
    private ?array $state = null;

    /** Datei, die unter /wm-consent/wm-consent.css ausgeliefert wird. */
    public function path(): string
    {
        return $this->state()['path'];
    }

    /** Für die Adresse des Stylesheets (?v=): ändert sich mit dem Inhalt. */
    public function version(): string
    {
        return $this->state()['version'];
    }

    /**
     * Für den Kundenbereich (Status-Abfrage).
     *
     * @return array{central: bool, version: string, checked_at: string|null}
     */
    public function status(): array
    {
        $state = $this->state();

        return [
            'central' => $state['central'],
            'version' => $state['version'],
            'checked_at' => $state['checked_at'] !== null ? gmdate('c', $state['checked_at']) : null,
        ];
    }

    /**
     * Brauchbar ist nur Text bis MAX_BYTES mit dem Banner (#cc-main), ohne @import und ohne url() außer data:, damit
     * das Stylesheet nie etwas von anderswo nachlädt.
     */
    public static function validate(string $css): bool
    {
        if ($css === '' || strlen($css) > self::MAX_BYTES || ! str_contains($css, '#cc-main') || str_contains($css, "\0")) {
            return false;
        }

        if (preg_match('/@import/i', $css) === 1 || preg_match('/<\s*\/?\s*(html|script|style)/i', $css) === 1) {
            return false;
        }

        preg_match_all('/url\(\s*([\'"]?)\s*([^\'")\s]*)/i', $css, $matches);

        foreach ($matches[2] as $target) {
            if (! str_starts_with(strtolower($target), 'data:')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{path: string, version: string, central: bool, checked_at: int|null}
     */
    private function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $package = dirname(__DIR__).'/dist/wm-consent.css';
        $fallback = ['path' => $package, 'version' => WmConsent::version(), 'central' => false, 'checked_at' => null];
        $url = config('wm-consent.styles_url', self::URL);
        $path = config('wm-consent.styles_path');

        if (! is_string($url) || $url === '' || ! is_string($path) || $path === '') {
            return $this->state = $fallback;
        }

        $meta = $this->meta($path);
        $known = ($meta['url'] ?? null) === $url && is_file($path);
        $fresh = ($meta['url'] ?? null) === $url && (int) ($meta['checked'] ?? 0) > time() - (int) config('wm-consent.styles_ttl', 3600);

        if (! $fresh) {
            $css = $this->fetch($url);

            if ($css !== null) {
                $known = $this->write($path, $css);
            }

            $meta = ['url' => $url, 'checked' => time(), 'hash' => $known ? substr(md5((string) @file_get_contents($path)), 0, 10) : null];
            $this->put($path.'.json', (string) json_encode($meta));
        }

        if (! $known || ! is_string($meta['hash'] ?? null)) {
            return $this->state = $fallback;
        }

        return $this->state = ['path' => $path, 'version' => 'c'.$meta['hash'], 'central' => true, 'checked_at' => (int) $meta['checked']];
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(string $path): array
    {
        $json = is_file($path.'.json') ? json_decode((string) @file_get_contents($path.'.json'), true) : null;

        return is_array($json) ? $json : [];
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(3)->connectTimeout(2)->get($url);
        } catch (Throwable) {
            return null;
        }

        $css = $response->successful() ? $response->body() : null;

        return is_string($css) && self::validate($css) ? $css : null;
    }

    private function write(string $path, string $css): bool
    {
        if (is_file($path) && @file_get_contents($path) === $css) {
            return true;
        }

        return $this->put($path, $css);
    }

    /** Erst in eine Nachbardatei, dann umbenennen: parallele Aufrufe lesen nie eine halbe Datei. */
    private function put(string $path, string $content): bool
    {
        try {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }

            $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

            return file_put_contents($temporary, $content) !== false && rename($temporary, $path);
        } catch (Throwable) {
            return false;
        }
    }
}
