<?php

namespace WursterMedien\Consent;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\YAML;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use WursterMedien\Consent\Core\HeadTag;
use WursterMedien\Consent\Core\Lang;
use WursterMedien\Consent\Core\Services;

/**
 * Einstellungen des Banners für diese Seite: config/wm-consent.php ist die Voreinstellung, was im Control Panel
 * („Cookie-Banner“) gespeichert ist, steht in content/wm-consent.yaml (`wm-consent.settings_path`, mit der Seite im
 * Git) und geht vor. Das Formular ist ein Blueprint mit flachen Feldern (light_background …, text_de_title …,
 * site_<handle>_privacy_url …), die beim Speichern in die Struktur von HeadTag (colors, texts je Sprache, sites je
 * Statamic-Site) umgerechnet werden. Texte gibt es je Sprache der Sites, den Tab „Websites“ nur bei mehreren Sites.
 */
class Settings
{
    /** Farben je Modus, wie HeadTag sie kennt. */
    public const COLORS = ['background', 'text', 'highlight'];

    /** Texte, die das Formular ersetzen lässt (Schlüssel wie in resources/js/texts.js). */
    public const TEXTS = ['title' => 'Titel', 'description' => 'Text im Banner', 'acceptAll' => 'Knopf „Alle akzeptieren“', 'acceptNecessary' => 'Knopf „Nur notwendige“', 'showPreferences' => 'Knopf „Einstellungen“', 'placeholder' => 'Platzhalter bei Videos und Karten ({dienst} = Name des Dienstes)'];

    /** Was jede Site im Tab „Websites“ selbst festlegen kann (HeadTag::SITE_KEYS); leer heißt: Wert aus „Allgemein“. */
    public const SITE_FIELDS = ['privacy_url' => 'Link Datenschutz', 'imprint_url' => 'Link Impressum', 'google_analytics_id' => 'Google Analytics (Mess-ID)'];

    /** Einfache Einstellungen, die das Formular 1:1 speichert. */
    private const PLAIN = ['enabled', 'filter', 'mode', 'position', 'blur', 'blur_strength', 'overlay_opacity', 'privacy_url', 'imprint_url', 'google_analytics_id', 'consent_mode', 'services', 'load_once', 'floating_button', 'auto_cookies', 'cookies', 'cookie_days'];

    /** @var array<string, mixed>|null */
    private ?array $cached = null;

    /**
     * Voreinstellung aus der Config, darüber das Gespeicherte (Listen wie services und cookies ersetzen ganz).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        $settings = (array) config('wm-consent', []);
        $stored = $this->stored();

        foreach ($stored as $key => $value) {
            $settings[$key] = is_array($value) && is_array($settings[$key] ?? null) && ! array_is_list($value) && ! array_is_list($settings[$key])
                ? array_replace_recursive($settings[$key], $value)
                : $value;
        }

        return $this->cached = $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Werte aus dem Formular speichern; „alle neu fragen“ erhöht die Revision.
     *
     * @param  array<string, mixed>  $values  verarbeitete Werte des Blueprints
     */
    public function saveForm(array $values): void
    {
        $settings = [];

        foreach (self::PLAIN as $key) {
            if (array_key_exists($key, $values)) {
                $settings[$key] = $values[$key];
            }
        }

        $settings['cookies'] = array_values(array_map(
            fn (array $cookie): array => array_filter(array_intersect_key($cookie, array_flip(['name', 'type', 'category', 'duration', 'description', 'provider'])), fn (mixed $value): bool => $value !== null && $value !== ''),
            array_filter((array) ($values['cookies'] ?? []), 'is_array'),
        ));

        foreach (['light', 'dark'] as $mode) {
            foreach (self::COLORS as $color) {
                if (filled($values["{$mode}_{$color}"] ?? null)) {
                    $settings['colors'][$mode][$color] = (string) $values["{$mode}_{$color}"];
                }
            }
        }

        /* Texte von Sprachen ohne Site im Formular (nur von Hand in der YAML) bleiben, flache wandern in die Sprache der Hauptsite. */
        $settings['texts'] = array_filter((array) ($this->stored()['texts'] ?? []), fn (mixed $value, string|int $lang): bool => is_array($value) && ! in_array($lang, self::languages(), true), ARRAY_FILTER_USE_BOTH);

        foreach (self::languages() as $lang) {
            foreach (array_keys(self::TEXTS) as $text) {
                if (filled($values["text_{$lang}_{$text}"] ?? null)) {
                    $settings['texts'][$lang][$text] = (string) $values["text_{$lang}_{$text}"];
                }
            }
        }

        /* Je Site nur, was das Formular zeigt; Weiteres aus der YAML (z. B. texts einer Site) bleibt. */
        $settings['sites'] = (array) ($this->stored()['sites'] ?? []);

        foreach (array_keys(self::sites()) as $site) {
            foreach (self::SITE_FIELDS as $key => $label) {
                $value = $values[self::siteField($site, $key)] ?? null;
                filled($value) ? $settings['sites'][$site][$key] = (string) $value : Arr::forget($settings['sites'], "{$site}.{$key}");
            }
        }

        $settings['texts'] = $settings['texts'] ?: null;
        $settings['sites'] = array_filter($settings['sites'], fn (mixed $values): bool => is_array($values) && $values !== []) ?: null;
        $settings['revision'] = (int) $this->get('revision', 0) + (! empty($values['reconsent']) ? 1 : 0);

        File::ensureDirectoryExists(dirname($this->path()));
        File::put($this->path(), YAML::dump(array_filter($settings, fn (mixed $value): bool => $value !== null)));
        $this->cached = null;
    }

    /**
     * Werte für das Formular (flach), aus den gültigen Einstellungen.
     *
     * @return array<string, mixed>
     */
    public function formValues(): array
    {
        $settings = $this->all();
        $values = array_intersect_key($settings, array_flip(self::PLAIN));
        $values['reconsent'] = false;

        foreach (['light', 'dark'] as $mode) {
            foreach (self::COLORS as $color) {
                $values["{$mode}_{$color}"] = $settings['colors'][$mode][$color] ?? null;
            }
        }

        $texts = (array) ($settings['texts'] ?? []);

        foreach (self::languages() as $index => $lang) {
            foreach (array_keys(self::TEXTS) as $text) {
                /* Flache Texte (vor den Sprachen gespeichert) zeigt das Formular bei der Hauptsprache. */
                $flat = $index === 0 && is_scalar($texts[$text] ?? null) ? (string) $texts[$text] : null;
                $values["text_{$lang}_{$text}"] = $texts[$lang][$text] ?? $flat;
            }
        }

        foreach (array_keys(self::sites()) as $site) {
            foreach (array_keys(self::SITE_FIELDS) as $key) {
                $values[self::siteField($site, $key)] = $settings['sites'][$site][$key] ?? null;
            }
        }

        return $values;
    }

    public function blueprint(): StatamicBlueprint
    {
        $colors = fn (string $mode): array => array_map(fn (string $color): array => [
            'handle' => "{$mode}_{$color}",
            'field' => ['type' => 'color', 'display' => self::colorLabel($color), 'width' => 33],
        ], self::COLORS);

        return Blueprint::make('wm_consent')->setContents(['tabs' => [
            'general' => ['display' => 'Allgemein', 'sections' => [
                ['display' => 'Banner', 'fields' => [
                    ['handle' => 'enabled', 'field' => ['type' => 'toggle', 'display' => 'Banner an', 'instructions' => 'Aus: kein Banner, nichts wird blockiert.', 'width' => 33]],
                    ['handle' => 'filter', 'field' => ['type' => 'toggle', 'display' => 'Tracker und Einbettungen blockieren', 'instructions' => 'Bekannte Dienste lädt die Seite erst nach Zustimmung.', 'width' => 33]],
                    ['handle' => 'reconsent', 'field' => ['type' => 'toggle', 'display' => 'Beim Speichern alle neu fragen', 'instructions' => 'Nach wesentlichen Änderungen an Diensten oder Texten.', 'width' => 33]],
                ]],
                ['display' => 'Aussehen', 'fields' => [
                    ['handle' => 'mode', 'field' => ['type' => 'button_group', 'display' => 'Darstellung', 'options' => ['auto' => 'Wie das Gerät', 'light' => 'Hell', 'dark' => 'Dunkel'], 'width' => 50]],
                    ['handle' => 'position', 'field' => ['type' => 'select', 'display' => 'Position', 'options' => self::positions(), 'width' => 50]],
                    ['handle' => 'blur', 'field' => ['type' => 'toggle', 'display' => 'Hintergrund unscharf', 'instructions' => 'Seite gesperrt, bis Besucher gewählt haben.', 'width' => 50]],
                    ['handle' => 'blur_strength', 'field' => ['type' => 'integer', 'display' => 'Unschärfe (px)', 'instructions' => '0 bis 30', 'width' => 50, 'if' => ['blur' => true], 'always_save' => true]],
                    ['handle' => 'floating_button', 'field' => ['type' => 'toggle', 'display' => 'Runder Knopf zum Wieder-Öffnen', 'instructions' => 'Sonst Link mit `{{ wm_consent:link }}` im Footer.', 'width' => 50]],
                ]],
                ['display' => 'Dienste', 'fields' => [
                    ['handle' => 'services', 'field' => ['type' => 'checkboxes', 'display' => 'Dienste immer anzeigen', 'instructions' => 'Gefundene Dienste stehen von selbst im Banner. Hier nur, was der Filter nicht sieht.', 'inline' => true, 'options' => Services::LABELS]],
                    ['handle' => 'load_once', 'field' => ['type' => 'toggle', 'display' => '„Einmal laden“ bei Videos und Karten', 'instructions' => 'Zusätzlich zu „Immer laden“ im Platzhalter.', 'width' => 50]],
                ]],
                ['display' => 'Google', 'fields' => [
                    ['handle' => 'consent_mode', 'field' => ['type' => 'toggle', 'display' => 'Google Consent Mode', 'width' => 50]],
                    ['handle' => 'google_analytics_id', 'field' => ['type' => 'text', 'display' => 'Google Analytics (Mess-ID)', 'instructions' => 'G-…; der Banner bindet GA selbst ein, erst nach Zustimmung. GA-Code im Template dann entfernen. Leer: GA-Code muss im Template stehen.', 'width' => 50]],
                ]],
                ['display' => 'Rechtliche Seiten', 'fields' => [
                    ['handle' => 'privacy_url', 'field' => ['type' => 'text', 'display' => 'Link Datenschutz', 'width' => 50]],
                    ['handle' => 'imprint_url', 'field' => ['type' => 'text', 'display' => 'Link Impressum', 'width' => 50]],
                ]],
            ]],
            'colors' => ['display' => 'Farben', 'sections' => [
                ['display' => 'Hell', 'instructions' => 'Leer lassen für den Standard. Nebenflächen, Nebentext und Linien ergeben sich aus dem Text mit Transparenz.', 'fields' => $colors('light')],
                ['display' => 'Dunkel', 'fields' => $colors('dark')],
                ['display' => 'Über der Seite', 'fields' => [
                    ['handle' => 'overlay_opacity', 'field' => ['type' => 'range', 'display' => 'Abdunkeln (%)', 'instructions' => 'Solange der Banner offen ist. Hell in der Textfarbe, dunkel in der Hintergrundfarbe.', 'min' => 0, 'max' => 90, 'step' => 5, 'default' => 50, 'width' => 50]],
                ]],
            ]],
            'cookies' => ['display' => 'Cookies', 'sections' => [[
                'fields' => [
                    ['handle' => 'auto_cookies', 'field' => ['type' => 'toggle', 'display' => 'Session- und XSRF-Cookie von selbst eintragen']],
                    ['handle' => 'cookies', 'field' => [
                        'type' => 'grid',
                        'display' => 'Eigene Einträge',
                        'instructions' => 'Was die Seite selbst speichert (z. B. Warenkorb im Local Storage). Dienste wie Google Analytics bringen ihre Cookies selbst mit. Finden: Seite mit #wm-consent-inventar öffnen, die Browser-Konsole listet alles Unbeschriebene.',
                        'mode' => 'stacked',
                        'add_row' => 'Eintrag hinzufügen',
                        'fields' => [
                            ['handle' => 'name', 'field' => ['type' => 'text', 'display' => 'Name', 'validate' => ['required'], 'width' => 33]],
                            ['handle' => 'type', 'field' => ['type' => 'select', 'display' => 'Art', 'options' => ['cookie' => 'Cookie', 'local' => 'Local Storage', 'session' => 'Session Storage'], 'default' => 'cookie', 'width' => 33]],
                            ['handle' => 'category', 'field' => ['type' => 'select', 'display' => 'Kategorie', 'options' => ['necessary' => 'Notwendig', 'analytics' => 'Statistik', 'marketing' => 'Marketing', 'media' => 'Externe Medien'], 'default' => 'necessary', 'width' => 33]],
                            ['handle' => 'duration', 'field' => ['type' => 'text', 'display' => 'Dauer', 'width' => 33]],
                            ['handle' => 'provider', 'field' => ['type' => 'text', 'display' => 'Anbieter', 'instructions' => 'Leer: diese Website.', 'width' => 66]],
                            ['handle' => 'description', 'field' => ['type' => 'text', 'display' => 'Zweck']],
                        ],
                    ]],
                ],
            ]]],
            'texts' => ['display' => 'Texte', 'sections' => array_map(fn (string $lang): array => [
                'display' => count(self::languages()) > 1 ? (Lang::LANGUAGES[$lang] ?? strtoupper($lang)) : null,
                'instructions' => 'Leer lassen für die Standardtexte'.(isset(Lang::LANGUAGES[$lang]) ? '.' : ' (für diese Sprache auf Englisch).'),
                'fields' => array_map(fn (string $key, string $label): array => [
                    'handle' => "text_{$lang}_{$key}",
                    'field' => ['type' => $key === 'description' || $key === 'placeholder' ? 'textarea' : 'text', 'display' => $label],
                ], array_keys(self::TEXTS), self::TEXTS),
            ], self::languages())],
            ...(count(self::sites()) > 1 ? ['sites' => ['display' => 'Websites', 'sections' => $this->siteSections()]] : []),
        ]]);
    }

    /**
     * Je Site ein Abschnitt mit Datenschutz, Impressum und Mess-ID; der Wert aus „Allgemein“ steht als Platzhalter.
     *
     * @return list<array<string, mixed>>
     */
    private function siteSections(): array
    {
        $general = $this->all();
        $sections = [];

        foreach (self::sites() as $handle => $site) {
            $sections[] = [
                'display' => $site['name'],
                'instructions' => implode(' · ', array_filter([$site['url'], Lang::LANGUAGES[$site['lang']] ?? strtoupper((string) $site['lang']), $sections === [] ? 'Leere Felder: Wert aus „Allgemein“.' : null])),
                'fields' => array_map(fn (string $key, string $label): array => [
                    'handle' => self::siteField($handle, $key),
                    'field' => ['type' => 'text', 'display' => $label, 'placeholder' => (string) ($general[$key] ?? ''), 'width' => 33],
                ], array_keys(self::SITE_FIELDS), self::SITE_FIELDS),
            ];
        }

        return $sections;
    }

    /**
     * Sites von Statamic mit Name, Adresse und Sprache (Kürzel), Hauptsite zuerst.
     *
     * @return array<string, array{name: string, url: string, lang: string|null}>
     */
    public static function sites(): array
    {
        return Site::all()
            ->sortBy(fn ($site): int => $site->handle() === Site::default()->handle() ? 0 : 1)
            ->mapWithKeys(fn ($site): array => [$site->handle() => ['name' => (string) $site->name(), 'url' => (string) $site->url(), 'lang' => Lang::code($site->lang()) ?? Lang::code($site->locale())]])
            ->all();
    }

    /**
     * Sprachen der Sites, die der Hauptsite zuerst (ohne erkennbare Sprache: Deutsch).
     *
     * @return list<string>
     */
    public static function languages(): array
    {
        return array_values(array_unique(array_map(fn (array $site): string => $site['lang'] ?? 'de', self::sites()))) ?: ['de'];
    }

    private static function siteField(string $site, string $key): string
    {
        return 'site_'.preg_replace('/[^a-z0-9_]/i', '_', $site).'_'.$key;
    }

    public function path(): string
    {
        return (string) config('wm-consent.settings_path', base_path('content/wm-consent.yaml'));
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $path = $this->path();

        return File::exists($path) ? (array) YAML::parse(File::get($path)) : [];
    }

    /**
     * @return array<string, string>
     */
    private static function positions(): array
    {
        $vertical = ['top' => 'Oben', 'middle' => 'Mitte', 'bottom' => 'Unten'];
        $horizontal = ['left' => 'links', 'center' => 'mittig', 'right' => 'rechts'];

        return collect(HeadTag::POSITIONS)
            ->mapWithKeys(function (string $position) use ($vertical, $horizontal): array {
                [$y, $x] = explode(' ', $position);

                return [$position => $vertical[$y].' '.$horizontal[$x]];
            })
            ->all();
    }

    private static function colorLabel(string $color): string
    {
        return match ($color) {
            'background' => 'Hintergrund',
            'text' => 'Text',
            'highlight' => 'Akzent (Knöpfe)',
            default => $color,
        };
    }
}
