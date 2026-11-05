<?php

namespace WursterMedien\Consent\Content;

use Statamic\Contracts\Entries\Collection as EntryCollection;
use Statamic\Contracts\Entries\Entry;
use Statamic\Contracts\Globals\GlobalSet;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet as GlobalSets;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fieldtypes\Assets\Assets;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Group;
use Statamic\Fieldtypes\Replicator;

/**
 * Was der Kunde außer den Platzhaltern auf den Seiten liefern muss (Simon, 5.11.2026: Liste „Inhalte liefern“ im
 * Kundenbereich aus der Blueprint): je Sammlung mit Einträgen (Team, Leistungen, Stellen, Referenzen …) die Felder eines
 * Eintrags samt Zahl der Einträge und wie viele davon noch Demo sind, je Global-Set (Impressum, Kontaktdaten) die Felder
 * mit Stand leer, Demo oder gefüllt. Seitenbäume (Struktur mit mehr als einer Ebene) stehen schon über die Seiten in
 * ContentMap. Nur lesen, keine Inhalte außer den Feldnamen.
 */
final class ContentNeeds
{
    private const TEXT_TYPES = ['text', 'textarea', 'markdown', 'list'];

    private const MAX_FIELDS = 60;

    public function __construct(private readonly ContentMap $map, private readonly DemoStand $demo) {}

    /**
     * @return list<array{handle: string, title: string, url: string|null, entries: int, demo_entries: int, fields: list<array{label: string, kind: string, need?: array<string, mixed>}>}>
     */
    public function collections(): array
    {
        $demo = $this->demo->fingerprints();
        $list = [];

        foreach (Collection::all() as $collection) {
            /** @var EntryCollection $collection */
            if (self::isPageTree($collection) || ! self::reachable($collection)) {
                continue;
            }

            $blueprint = $collection->entryBlueprint();

            if ($blueprint === null) {
                continue;
            }

            $fields = $this->fields($blueprint->fields(), '', $collection->autoGeneratesTitles());

            if ($fields === []) {
                continue;
            }

            $entries = $collection->queryEntries()->where('published', true)->get();
            $demoEntries = $entries->filter(function (Entry $entry) use ($demo): bool {
                $spots = $this->map->spots($entry);

                return $spots !== [] && collect($spots)->every(fn (array $spot): bool => $spot['kind'] === 'image' ? $spot['demo'] : isset($demo[$spot['fingerprint']]));
            });
            $mount = $collection->mount();

            $list[] = [
                'handle' => $collection->handle(),
                'title' => (string) $collection->title(),
                'url' => $mount !== null && is_string($mount->absoluteUrl()) ? $mount->absoluteUrl() : null,
                'entries' => $entries->count(),
                'demo_entries' => $demo === [] ? 0 : $demoEntries->count(),
                'fields' => array_slice($fields, 0, self::MAX_FIELDS),
            ];
        }

        return $list;
    }

    /**
     * @return list<array{handle: string, title: string, fields: list<array{label: string, kind: string, state: string, need?: array<string, mixed>}>}>
     */
    public function globals(): array
    {
        $demo = $this->demo->fingerprints();
        $list = [];

        foreach (GlobalSets::all() as $set) {
            /** @var GlobalSet $set */
            $variables = $set->inDefaultSite();
            $blueprint = $variables?->blueprint();

            if ($blueprint === null) {
                continue;
            }

            $values = $variables->data()->all();
            $fields = [];

            foreach ($this->fields($blueprint->fields(), '', false, $values) as $field) {
                $value = $field['value'];
                unset($field['value']);
                $text = is_array($value) ? implode(' ', array_filter($value, 'is_string')) : (is_string($value) ? $value : '');
                $filled = $field['kind'] === 'image' ? array_filter((array) $value) !== [] : ContentMap::normalize($text) !== '';
                $isDemo = $filled && (($field['kind'] === 'image' && collect((array) $value)->contains(fn (mixed $asset): bool => is_string($asset) && preg_match('#(^|/)demo/#', $asset) === 1))
                    || ($field['kind'] !== 'image' && isset($demo[ContentMap::fingerprint($text)])));
                $fields[] = [...$field, 'state' => $isDemo ? 'demo' : ($filled ? 'filled' : 'empty')];
            }

            if ($fields !== []) {
                $list[] = ['handle' => $set->handle(), 'title' => (string) $set->title(), 'fields' => array_slice($fields, 0, self::MAX_FIELDS)];
            }
        }

        return $list;
    }

    /**
     * Texte der Globals zum Demo-Stand (wm-consent:demo-stand), damit unsere Musterangaben im Impressum als Demo gelten.
     *
     * @return list<string>
     */
    public function globalTextFingerprints(): array
    {
        $prints = [];

        foreach (GlobalSets::all() as $set) {
            $variables = $set->inDefaultSite();
            $blueprint = $variables?->blueprint();

            if ($blueprint === null) {
                continue;
            }

            foreach ($this->fields($blueprint->fields(), '', false, $variables->data()->all()) as $field) {
                $text = is_array($field['value']) ? implode(' ', array_filter($field['value'], 'is_string')) : (is_string($field['value']) ? $field['value'] : '');

                if ($field['kind'] === 'text' && ContentMap::normalize($text) !== '') {
                    $prints[] = ContentMap::fingerprint($text);
                }
            }
        }

        return $prints;
    }

    /**
     * Felder in der Reihenfolge der Blueprint, Gruppen aufgelöst; Replicator und Bard mit Sets als „frei aufgebaut“.
     *
     * @param  array<string, mixed>|null  $values  mit Werten (Globals), sonst ohne
     * @return list<array{label: string, kind: string, need?: array<string, mixed>, value?: mixed}>
     */
    private function fields(Fields $fields, string $prefix, bool $skipTitle, ?array $values = null): array
    {
        $list = [];

        foreach ($fields->all() as $handle => $field) {
            /** @var Field $field */
            if (FieldNeeds::skipped($field) || ($skipTitle && $handle === 'title')) {
                continue;
            }

            $type = $field->fieldtype();
            $label = $prefix.(string) ($field->display() ?: $handle);
            $kind = match (true) {
                $type instanceof Group => 'group',
                $type instanceof Replicator, $type instanceof Bard && $type->config('sets') => 'content',
                $type instanceof Bard, in_array($field->type(), self::TEXT_TYPES, true) => 'text',
                $type instanceof Assets => 'image',
                default => null,
            };

            if ($kind === 'group') {
                $list = [...$list, ...$this->fields($type->fields(), $label.' › ', false, $values === null ? null : (is_array($values[$handle] ?? null) ? $values[$handle] : []))];

                continue;
            }

            if ($kind === null) {
                continue;
            }

            $row = ['label' => $label, 'kind' => $kind];

            if (($need = FieldNeeds::of($field)) !== []) {
                $row['need'] = $need;
            }

            if ($values !== null) {
                $row['value'] = $values[$handle] ?? null;
            }

            $list[] = $row;
        }

        return $list;
    }

    /** Seitenbaum (Struktur mit mehr als einer Ebene): steht schon über die Seiten in ContentMap. */
    private static function isPageTree(EntryCollection $collection): bool
    {
        $structure = $collection->structure();

        return $structure !== null && $structure->maxDepth() !== 1;
    }

    /** Wie ContentMap: hängt die Sammlung an einer nicht veröffentlichten Seite, wird sie nicht genutzt. */
    private static function reachable(EntryCollection $collection): bool
    {
        $mount = $collection->mount();

        return $mount === null || $mount->published();
    }
}
