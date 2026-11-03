<?php

namespace WursterMedien\Consent\Content;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;

/**
 * „Demo-Stand merken“ (Simon, 3.11.2026): Fingerabdrücke aller Texte der Seite zu dem Zeitpunkt, an dem nur unsere
 * Demo- und Branding-Inhalte drinstehen (wm-consent:demo-stand). Was später noch denselben Fingerabdruck hat, ist ein
 * Platzhalter, den der Kunde prüft. Gespeichert in wm-consent.demo_path (storage, nicht im Git).
 */
final class DemoStand
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  list<string>  $fingerprints
     */
    public function remember(array $fingerprints): void
    {
        $path = (string) config('wm-consent.demo_path');
        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, (string) json_encode(['remembered_at' => Carbon::now()->toIso8601String(), 'fingerprints' => array_values($fingerprints)], JSON_PRETTY_PRINT));
    }

    public function forget(): void
    {
        $this->files->delete((string) config('wm-consent.demo_path'));
    }

    /**
     * Fingerabdrücke als Schlüssel; leer ohne gemerkten Stand.
     *
     * @return array<string, true>
     */
    public function fingerprints(): array
    {
        $data = $this->data();

        return array_fill_keys(array_filter((array) ($data['fingerprints'] ?? []), 'is_string'), true);
    }

    public function rememberedAt(): ?string
    {
        $at = $this->data()['remembered_at'] ?? null;

        return is_string($at) ? $at : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        $path = (string) config('wm-consent.demo_path');

        if (! $this->files->exists($path)) {
            return [];
        }

        $data = json_decode((string) $this->files->get($path), true);

        return is_array($data) ? $data : [];
    }
}
