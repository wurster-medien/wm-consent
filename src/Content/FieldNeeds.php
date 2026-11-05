<?php

namespace WursterMedien\Consent\Content;

use Statamic\Fields\Field;

/**
 * Was ein Feld vom Kunden braucht (Simon, 5.11.2026: Inhalte liefern aus der Blueprint): Pflicht, Zeichengrenze,
 * Zahl der Bilder aus der Konfiguration des Feldes, dazu eigene Schlüssel, die Statamic stehen lässt und im Control
 * Panel nicht zeigt: wm_image_width (Mindestbreite in Pixeln), wm_image_ratio („2:1“), wm_hint (Hinweis für den Kunden),
 * wm_need: false (Feld nie abfragen). Ohne wm_image_width zählt eine Pixelzahl in den Hinweisen („Min. 2600px Breite“).
 */
final class FieldNeeds
{
    /** Felder, die man auf der Seite nicht sieht oder die wir selbst pflegen. */
    private const HIDDEN_HANDLES = '/^(slug|seo|meta|og|twitter|robots|analytics|tracking|gtm|ga|icon|fa_icon)(_|$)/i';

    /**
     * @return array{required?: true, max_chars?: int, max_files?: int, min_files?: int, width?: int, ratio?: string, hint?: string}
     */
    public static function of(Field $field): array
    {
        $rules = self::rules($field);
        $max = $field->get('character_limit');
        $maxRule = collect($rules)->map(fn (string $rule): ?int => preg_match('/^max:(\d+)$/', $rule, $match) === 1 ? (int) $match[1] : null)->filter()->first();
        $width = $field->get('wm_image_width');
        $ratio = $field->get('wm_image_ratio');
        $hint = $field->get('wm_hint');

        if (! is_numeric($width) && is_string($instructions = $field->get('instructions')) && preg_match('/(\d{3,5})\s*px/i', $instructions, $match) === 1) {
            $width = $match[1];
        }

        return array_filter([
            'required' => in_array('required', $rules, true) || $field->get('required') === true ? true : null,
            'max_chars' => is_numeric($max) && (int) $max > 0 ? (int) $max : $maxRule,
            'max_files' => is_numeric($field->get('max_files')) && (int) $field->get('max_files') > 0 ? (int) $field->get('max_files') : null,
            'min_files' => is_numeric($field->get('min_files')) && (int) $field->get('min_files') > 0 ? (int) $field->get('min_files') : null,
            'width' => is_numeric($width) && (int) $width >= 100 ? min((int) $width, 20000) : null,
            'ratio' => is_string($ratio) && preg_match('/^\d{1,3}(\.\d{1,2})?:\d{1,3}(\.\d{1,2})?$/', trim($ratio)) === 1 ? trim($ratio) : null,
            'hint' => is_string($hint) && trim($hint) !== '' ? mb_substr(trim($hint), 0, 300) : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /** Felder, die der Kunde nicht liefert: ausgeblendet, berechnet, technisch oder mit wm_need: false. */
    public static function skipped(Field $field): bool
    {
        return $field->get('wm_need') === false
            || in_array($field->get('visibility'), ['hidden', 'computed'], true)
            || preg_match(self::HIDDEN_HANDLES, $field->handle()) === 1;
    }

    /**
     * @return list<string>
     */
    private static function rules(Field $field): array
    {
        $rules = $field->get('validate');

        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        return array_values(array_filter(array_map(fn (mixed $rule): string => is_string($rule) ? trim($rule) : '', (array) $rules)));
    }
}
