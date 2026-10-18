<?php

namespace WursterMedien\Consent;

use Illuminate\Support\Facades\File;

/**
 * Welche Dienste der Ausgabefilter auf der Website gefunden hat, für die Übersicht im Tab „Cookies“: je Dienst die
 * Adresse, auf der er zuerst auftauchte, und der Tag, an dem er zuletzt gesehen wurde. Steht in
 * storage/wm-consent/detected.json (`wm-consent.detected_path`, nicht im Git) und wird nur geschrieben, wenn ein
 * Dienst neu ist oder an diesem Tag noch nicht gesehen wurde.
 */
class Detected
{
    /** @var array<string, array{url: string, last: string}>|null */
    private ?array $cached = null;

    /**
     * @return array<string, array{url: string, last: string}>
     */
    public function all(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $path = $this->path();
        $stored = File::exists($path) ? json_decode((string) File::get($path), true) : [];

        return $this->cached = array_filter(is_array($stored) ? $stored : [], fn (mixed $entry): bool => is_array($entry) && isset($entry['url'], $entry['last']));
    }

    /**
     * @param  list<string>  $services  gefundene Dienste einer Seite
     */
    public function record(array $services, string $url): void
    {
        $all = $this->all();
        $today = date('Y-m-d');
        $changed = false;

        foreach ($services as $service) {
            if (! isset($all[$service])) {
                $all[$service] = ['url' => $url, 'last' => $today];
                $changed = true;
            } elseif ($all[$service]['last'] !== $today) {
                $all[$service]['last'] = $today;
                $changed = true;
            }
        }

        if ($changed) {
            File::ensureDirectoryExists(dirname($this->path()));
            File::put($this->path(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
            $this->cached = $all;
        }
    }

    public function clear(): void
    {
        File::delete($this->path());
        $this->cached = null;
    }

    public function path(): string
    {
        return (string) config('wm-consent.detected_path', storage_path('wm-consent/detected.json'));
    }
}
