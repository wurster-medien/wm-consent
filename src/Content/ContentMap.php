<?php

namespace WursterMedien\Consent\Content;

use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Markdown;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Assets\Assets;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Grid;
use Statamic\Fieldtypes\Group;
use Statamic\Fieldtypes\Replicator;

/**
 * Inhalte der Seiten für den Kundenbereich (Simon, 3.11.2026: Platzhalter prüfen und freigeben): je Eintrag mit Adresse
 * die Stellen (Texte und Bilder aus den Feldern der Blueprint, auch in Replicator, Bard, Grid und Gruppen) und die
 * Bereiche (Sets der obersten Ebene), jeweils mit Fingerabdruck. Demo ist eine Stelle, deren Text sich seit „Demo-Stand
 * merken“ (DemoStand) nicht geändert hat, oder ein Bild aus einem Ordner demo/. Jede Stelle geht mit ihrem Text bzw. Bild
 * mit (ab 1.3.1), damit die Anmerkungs-Ebene des Kundenbereichs sie auf der Seite findet und abhaken lässt. Nur lesen.
 */
final class ContentMap
{
    /** Feldtypen mit Text, den der Kunde prüft. */
    private const TEXT_TYPES = ['text', 'textarea', 'markdown'];

    /** So viele Seiten höchstens (große Blogs). */
    public const MAX_PAGES = 500;

    /** So lang wird der Text einer Demo-Stelle mitgeschickt. */
    private const MAX_TEXT = 500;

    public function __construct(private readonly DemoStand $demo) {}

    /**
     * Alle Texte aller Einträge, als Fingerabdrücke (für DemoStand::remember).
     *
     * @return list<string>
     */
    public function allTextFingerprints(): array
    {
        $prints = [];

        foreach (Entries::all() as $entry) {
            foreach ($this->spots($entry) as $spot) {
                if ($spot['kind'] === 'text') {
                    $prints[] = $spot['fingerprint'];
                }
            }
        }

        return array_values(array_unique($prints));
    }

    /**
     * Seiten mit Stellen und Bereichen.
     *
     * @return list<array{id: string, title: string, url: string, fingerprint: string, sections: list<array{key: string, label: string, fingerprint: string}>, spots: list<array<string, mixed>>}>
     */
    public function pages(): array
    {
        $demo = $this->demo->fingerprints();
        $pages = [];

        $entries = Entries::query()->where('published', true)->get()
            ->filter(fn (Entry $entry): bool => is_string($entry->absoluteUrl()) && $entry->absoluteUrl() !== '')
            ->filter(fn (Entry $entry): bool => self::reachable($entry))
            ->sortBy(fn (Entry $entry): string => (string) $entry->url())
            ->take(self::MAX_PAGES);

        foreach ($entries as $entry) {
            $spots = array_map(function (array $spot) use ($demo): array {
                $isDemo = $spot['kind'] === 'image' ? $spot['demo'] : $demo !== [] && isset($demo[$spot['fingerprint']]);
                $out = ['path' => $spot['path'], 'section' => $spot['section'], 'kind' => $spot['kind'], 'fingerprint' => $spot['fingerprint'], 'demo' => $isDemo];

                /* Text jeder Stelle (wie er auf der Seite steht), damit die Anmerkungs-Ebene sie auf der Seite findet. */
                $out['field'] = $spot['field'];

                if ($spot['kind'] === 'text') {
                    $out['text'] = mb_substr($spot['text'], 0, self::MAX_TEXT);
                    $out['length'] = mb_strlen($spot['text']);
                }

                /* Vorgaben aus der Blueprint für die Liste „Inhalte liefern“ im Kundenbereich (FieldNeeds). */
                if ($spot['need'] !== []) {
                    $out['need'] = $spot['need'];
                }

                if ($spot['kind'] === 'image') {
                    $out['url'] = $spot['url'];
                    $out['asset'] = $spot['asset'];
                }

                return $out;
            }, $this->spots($entry));

            $pages[] = [
                'id' => (string) $entry->id(),
                'title' => (string) ($entry->get('title') ?? $entry->slug()),
                'url' => (string) $entry->absoluteUrl(),
                'fingerprint' => self::hash(implode('|', array_map(fn (array $spot): string => $spot['path'].'='.$spot['fingerprint'], $spots))),
                'sections' => $this->sections($entry, $spots),
                'spots' => $spots,
            ];
        }

        return $pages;
    }

    /**
     * Hängt die Sammlung an einer Seite (mount), die nicht veröffentlicht ist, führt von der Website kein Weg zu ihren
     * Einträgen (Simon, 4.11.2026: „Aktuelles“ bei Zinser wird nicht genutzt): dann gehören sie nicht zum Prüfen.
     */
    private static function reachable(Entry $entry): bool
    {
        for ($depth = 0, $current = $entry; $depth < 5; $depth++) {
            $mount = $current->collection()?->mount();

            if ($mount === null || $mount->id() === $current->id()) {
                return true;
            }

            if (! $mount->published()) {
                return false;
            }

            $current = $mount;
        }

        return true;
    }

    /** Fingerabdruck eines Textes: ohne HTML, Leerraum zusammengefasst. */
    public static function fingerprint(string $text): string
    {
        return self::hash(self::normalize($text));
    }

    public static function normalize(string $text): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function hash(string $value): string
    {
        return substr(hash('sha256', $value), 0, 20);
    }

    /**
     * @param  list<array<string, mixed>>  $spots
     * @return list<array{key: string, label: string, fingerprint: string}>
     */
    private function sections(Entry $entry, array $spots): array
    {
        $sections = [];

        foreach ($spots as $spot) {
            if ($spot['section'] === null) {
                continue;
            }

            $sections[$spot['section']]['prints'][] = $spot['path'].'='.$spot['fingerprint'];
            $sections[$spot['section']]['label'] ??= $this->sectionLabels[$entry->id()][$spot['section']] ?? $spot['section'];
        }

        return array_values(array_map(fn (string $key, array $section): array => [
            'key' => $key,
            'label' => (string) $section['label'],
            'fingerprint' => self::hash(implode('|', $section['prints'])),
        ], array_keys($sections), $sections));
    }

    /** @var array<string, array<string, string>> Bezeichnung der Bereiche je Eintrag (Name des Sets) */
    private array $sectionLabels = [];

    /**
     * Stellen eines Eintrags in der Reihenfolge der Blueprint.
     *
     * @return list<array{path: string, section: string|null, field: string, kind: string, text: string, url: string|null, asset?: string, demo: bool, fingerprint: string, need: array<string, mixed>}>
     */
    public function spots(Entry $entry): array
    {
        $spots = [];
        $this->walk($entry->blueprint()->fields(), $entry->data()->all(), '', null, $spots, (string) $entry->id(), true);

        return $spots;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<array<string, mixed>>  $spots
     */
    private function walk(Fields $fields, array $values, string $prefix, ?string $section, array &$spots, string $entryId, bool $top): void
    {
        foreach ($fields->all() as $handle => $field) {
            /** @var Field $field */
            $value = $values[$handle] ?? null;
            $path = $prefix.$handle;
            $type = $field->fieldtype();
            $label = (string) ($field->display() ?: $handle);

            if ($field->type() === 'markdown') {
                /* Wie auf der Seite: Markdown erst umwandeln, sonst passen Text und Fingerabdruck nicht zur Seite. */
                $this->addText($spots, $path, $section, $label, is_string($value) ? Markdown::parse($value) : $value, $field);
            } elseif (in_array($field->type(), self::TEXT_TYPES, true)) {
                $this->addText($spots, $path, $section, $label, $value, $field);
            } elseif ($field->type() === 'list') {
                /* Liste (z. B. Überschriften Zeile für Zeile): jede Zeile eine Stelle, oft mit Markdown wie „**Dachausbau**“. */
                foreach (is_array($value) ? array_values($value) : [] as $index => $item) {
                    $this->addText($spots, $path.'.'.$index, $section, $label, is_string($item) ? Markdown::parse($item) : $item, $field);
                }
            } elseif ($type instanceof Bard) {
                $this->walkBard($type, $value, $path, $section, $field, $spots, $entryId, $top);
            } elseif ($type instanceof Replicator) {
                foreach (is_array($value) ? array_values($value) : [] as $index => $set) {
                    if (! is_array($set) || ! is_string($set['type'] ?? null) || ($set['enabled'] ?? true) === false) {
                        continue;
                    }

                    $key = $path.'.'.$index;
                    $own = $top ? $key : $section;

                    if ($top) {
                        $this->sectionLabels[$entryId][$key] = $this->setLabel($type, $set['type']);
                    }

                    $this->walk($type->fields($set['type'], $index), $set, $key.'.', $own, $spots, $entryId, false);
                }
            } elseif ($type instanceof Grid) {
                foreach (is_array($value) ? array_values($value) : [] as $index => $row) {
                    if (is_array($row)) {
                        $this->walk($type->fields($index), $row, $path.'.'.$index.'.', $section, $spots, $entryId, false);
                    }
                }
            } elseif ($type instanceof Group) {
                $this->walk($type->fields(), is_array($value) ? $value : [], $path.'.', $section, $spots, $entryId, false);
            } elseif ($type instanceof Assets) {
                $container = (string) ($field->get('container') ?? '');

                foreach (array_values(array_filter((array) $value, 'is_string')) as $index => $asset) {
                    $spots[] = [
                        'path' => $path.'.'.$index,
                        'section' => $section,
                        'field' => $label,
                        'kind' => 'image',
                        'text' => '',
                        'url' => $container !== '' ? Asset::find($container.'::'.$asset)?->url() : null,
                        'asset' => $asset,
                        'demo' => preg_match('#(^|/)demo/#', $asset) === 1,
                        'fingerprint' => self::hash($asset),
                        'need' => FieldNeeds::of($field),
                    ];
                }
            }
        }
    }

    /**
     * Bard: der Fließtext als eine Stelle, Sets darin wie im Replicator.
     *
     * @param  list<array<string, mixed>>  $spots
     */
    private function walkBard(Bard $type, mixed $value, string $path, ?string $section, Field $field, array &$spots, string $entryId, bool $top): void
    {
        $label = (string) ($field->display() ?: $field->handle());

        if (is_string($value)) {
            $this->addText($spots, $path, $section, $label, $value, $field);

            return;
        }

        $text = [];
        $index = 0;

        foreach (is_array($value) ? $value : [] as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'set' && is_array($node['attrs']['values'] ?? null)) {
                $values = $node['attrs']['values'];
                $handle = $values['type'] ?? null;

                if (is_string($handle) && ($values['enabled'] ?? true) !== false) {
                    $key = $path.'.'.$index;

                    if ($top) {
                        $this->sectionLabels[$entryId][$key] = $this->setLabel($type, $handle);
                    }

                    $this->walk($type->fields($handle, $index), $values, $key.'.', $top ? $key : $section, $spots, $entryId, false);
                }
            } else {
                $text[] = self::nodeText($node);
            }

            $index++;
        }

        $this->addText($spots, $path, $section, $label, implode(' ', $text), $field);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function nodeText(array $node): string
    {
        if (isset($node['text']) && is_string($node['text'])) {
            return $node['text'];
        }

        if (($node['type'] ?? null) === 'hardBreak') {
            return ' ';
        }

        /* Text in einem Absatz oder einer Überschrift am Stück (fett, kursiv, Links), Absätze durch Leerzeichen getrennt. */
        $glue = in_array($node['type'] ?? null, ['paragraph', 'heading'], true) ? '' : ' ';

        return implode($glue, array_map(fn (mixed $child): string => is_array($child) ? self::nodeText($child) : '', (array) ($node['content'] ?? [])));
    }

    /**
     * @param  list<array<string, mixed>>  $spots
     */
    private function addText(array &$spots, string $path, ?string $section, string $label, mixed $value, Field $field): void
    {
        if (! is_string($value) || ($text = self::normalize($value)) === '') {
            return;
        }

        $spots[] = ['path' => $path, 'section' => $section, 'field' => $label, 'kind' => 'text', 'text' => $text, 'url' => null, 'demo' => false, 'fingerprint' => self::hash($text), 'need' => FieldNeeds::of($field)];
    }

    private function setLabel(Replicator $type, string $handle): string
    {
        $config = $type->flattenedSetsConfig()[$handle] ?? [];

        return (string) (($config['display'] ?? null) ?: $handle);
    }
}
