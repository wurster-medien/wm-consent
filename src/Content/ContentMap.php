<?php

namespace WursterMedien\Consent\Content;

use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Asset;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as Entries;
use Statamic\Facades\Markdown;
use Statamic\Facades\Site;
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
 * merken“ (DemoStand) nicht geändert hat, oder ein Bild aus einem Ordner demo/ (ab 1.4.1 auch jedes seitdem unveränderte Bild). Jede Stelle geht mit ihrem Text bzw. Bild
 * mit (ab 1.3.1), damit die Anmerkungs-Ebene des Kundenbereichs sie auf der Seite findet und abhaken lässt. Ab 1.5 dazu
 * je Seite die wiederholbaren Felder (Listen, Grids, Bilder) mit Zahl und Höchstzahl. Nur lesen.
 */
final class ContentMap
{
    /** Feldtypen mit Text, den der Kunde prüft. */
    private const TEXT_TYPES = ['text', 'textarea', 'markdown'];

    /** So viele Seiten höchstens (große Blogs). */
    public const MAX_PAGES = 500;

    /** So lang wird der Text einer Stelle mitgeschickt (ab 1.4.3 für das Formular „Inhalte eintragen“ im Kundenbereich). */
    private const MAX_TEXT = 5000;

    public function __construct(private readonly DemoStand $demo) {}

    /**
     * Alle Texte und Bilder aller Einträge, als Fingerabdrücke (für DemoStand::remember); ab 1.4.1 auch die Bilder, damit
     * Musterbilder außerhalb von demo/ als Platzhalter gelten.
     *
     * @return array{texts: list<string>, images: list<string>}
     */
    public function allFingerprints(): array
    {
        $prints = ['text' => [], 'image' => []];

        foreach (Entries::all() as $entry) {
            foreach ($this->spots($entry) as $spot) {
                $prints[$spot['kind']][] = $spot['fingerprint'];
            }
        }

        return ['texts' => array_values(array_unique($prints['text'])), 'images' => array_values(array_unique($prints['image']))];
    }

    /**
     * Platzhalter: Bild aus demo/ oder (wie Texte) seit dem Demo-Stand unverändert.
     *
     * @param  array<string, mixed>  $spot
     * @param  array<string, true>  $demo
     */
    public static function isDemo(array $spot, array $demo): bool
    {
        return ($spot['kind'] === 'image' && $spot['demo']) || isset($demo[$spot['fingerprint']]);
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
            ->sortBy(self::navigationOrder())
            ->take(self::MAX_PAGES);

        foreach ($entries as $entry) {
            $spots = array_map(function (array $spot) use ($demo): array {
                $isDemo = self::isDemo($spot, $demo);
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
                'collection' => (string) $entry->collectionHandle(),
                'fingerprint' => self::hash(implode('|', array_map(fn (array $spot): string => $spot['path'].'='.$spot['fingerprint'], $spots))),
                'sections' => $this->sections($entry, $spots),
                'spots' => $spots,
                'lists' => $this->lists[(string) $entry->id()] ?? [],
            ];
        }

        return $pages;
    }

    /**
     * Reihenfolge wie in der Navigation (ab 1.4.2, Simon 5.11.2026): Seiten in der Reihenfolge ihres Baums, Einträge einer
     * Sammlung direkt hinter der Seite, an der sie hängt (in ihrer eigenen Reihenfolge, sonst nach Adresse), der Rest danach
     * nach Adresse.
     *
     * @return \Closure(Entry): string
     */
    private static function navigationOrder(): \Closure
    {
        $order = [];

        foreach (Collection::all() as $collection) {
            $tree = $collection->structure()?->in(Site::default()->handle());

            foreach ($tree?->flattenedPages() ?? [] as $page) {
                $order[(string) ($page->reference() ?? $page->id())] ??= count($order);
            }
        }

        return function (Entry $entry) use ($order): string {
            $own = $order[(string) $entry->id()] ?? null;
            $mount = $entry->collection()?->mount();
            $parent = $mount !== null ? ($order[(string) $mount->id()] ?? null) : null;
            $key = fn (?int $index): string => str_pad((string) ($index ?? 999999), 6, '0', STR_PAD_LEFT);

            return match (true) {
                $parent !== null => $key($parent).'|1|'.$key($own).'|'.$entry->url(),
                $own !== null && $mount === null => $key($own).'|0',
                default => $key(null).'|'.$entry->url(),
            };
        };
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

    /** @var array<string, list<array<string, mixed>>> Wiederholbare Felder je Eintrag (lists in pages()) */
    private array $lists = [];

    private int $gridDepth = 0;

    /**
     * Stellen eines Eintrags in der Reihenfolge der Blueprint.
     *
     * @return list<array{path: string, section: string|null, field: string, kind: string, text: string, url: string|null, asset?: string, demo: bool, fingerprint: string, need: array<string, mixed>}>
     */
    public function spots(Entry $entry): array
    {
        $spots = [];
        $this->lists[(string) $entry->id()] = [];
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

                $this->addList($entryId, $field, $path, $section, 'list', is_array($value) ? count($value) : 0, null);
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
                /* Felder in einer Zeile sind kein eigenes wiederholbares Feld, die Zeile ist die Einheit. */
                $this->gridDepth++;

                foreach (is_array($value) ? array_values($value) : [] as $index => $row) {
                    if (is_array($row)) {
                        $this->walk($type->fields($index), $row, $path.'.'.$index.'.', $section, $spots, $entryId, false);
                    }
                }

                $this->gridDepth--;
                $this->addList($entryId, $field, $path, $section, 'grid', is_array($value) ? count($value) : 0, $field->get('max_rows'), self::rowFields($type->fields()));
            } elseif ($type instanceof Group) {
                $this->walk($type->fields(), is_array($value) ? $value : [], $path.'.', $section, $spots, $entryId, false);
            } elseif ($type instanceof Assets) {
                $container = (string) ($field->get('container') ?? '');
                $assets = array_values(array_filter((array) $value, 'is_string'));
                $this->addList($entryId, $field, $path, $section, 'assets', count($assets), $field->get('max_files'));

                foreach ($assets as $index => $asset) {
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

    /**
     * Wiederholbares Feld (ab 1.5, Simon 6.11.2026: im Formular „Inhalte eintragen“ Zeilen und Einträge hinzufügen oder
     * weglassen): Liste, Grid oder Bilder mit ihrem Pfad, der Zahl der Einträge (auch 0, dann gibt es keine Stelle) und der
     * Höchstzahl aus der Blueprint; beim Grid die Felder einer Zeile.
     *
     * @param  list<array<string, mixed>>  $fields
     */
    private function addList(string $entryId, Field $field, string $path, ?string $section, string $type, int $count, mixed $max, array $fields = []): void
    {
        if ($this->gridDepth > 0 || FieldNeeds::skipped($field) || ($type === 'grid' && $fields === [])) {
            return;
        }

        $this->lists[$entryId][] = array_filter([
            'path' => $path,
            'section' => $section,
            'field' => (string) ($field->display() ?: $field->handle()),
            'type' => $type,
            'count' => $count,
            'max' => is_numeric($max) && (int) $max > 0 ? (int) $max : null,
            'need' => FieldNeeds::of($field),
            'fields' => $fields,
        ], fn (mixed $value, string $key): bool => $value !== null && ($value !== [] || $key === 'fields' && $type === 'grid'), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Felder einer Grid-Zeile mit Text oder Bildern.
     *
     * @return list<array{handle: string, label: string, kind: string, need: array<string, mixed>}>
     */
    public static function rowFields(Fields $fields): array
    {
        $row = [];

        foreach ($fields->all() as $handle => $field) {
            /** @var Field $field */
            $kind = match (true) {
                in_array($field->type(), [...self::TEXT_TYPES, 'list', 'bard'], true) => 'text',
                $field->fieldtype() instanceof Assets => 'image',
                default => null,
            };

            if ($kind !== null && ! FieldNeeds::skipped($field)) {
                $row[] = ['handle' => (string) $handle, 'label' => (string) ($field->display() ?: $handle), 'kind' => $kind, 'need' => FieldNeeds::of($field)];
            }
        }

        return $row;
    }

    private function setLabel(Replicator $type, string $handle): string
    {
        $config = $type->flattenedSetsConfig()[$handle] ?? [];

        return (string) (($config['display'] ?? null) ?: $handle);
    }
}
