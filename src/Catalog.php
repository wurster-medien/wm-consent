<?php

namespace WursterMedien\Consent;

/**
 * Daten fürs Control Panel aus dist/cp.json (beim Build aus resources/js/services.js und texts.js): Namen der
 * Kategorien, Dienste mit Cookies auf Deutsch, Standardtexte je Sprache.
 */
final class Catalog
{
    /** @var array{categories: array<string, string>, services: array<string, array{label: string, category: string, provider: string, cookies: list<array<string, mixed>>}>, texts: array<string, array<string, string>>}|null */
    private static ?array $data = null;

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return self::data()['categories'] ?: ['necessary' => 'Notwendig', 'analytics' => 'Statistik', 'marketing' => 'Marketing', 'media' => 'Externe Medien'];
    }

    /**
     * @return array<string, array{label: string, category: string, provider: string, cookies: list<array<string, mixed>>}>
     */
    public static function services(): array
    {
        return self::data()['services'];
    }

    /** Standardtext in einer Sprache (andere Sprachen: Englisch wie im Banner). */
    public static function text(string $key, string $lang): string
    {
        $texts = self::data()['texts'];

        return (string) ($texts[$lang][$key] ?? $texts['en'][$key] ?? '');
    }

    /**
     * @return array{categories: array<string, string>, services: array<string, mixed>, texts: array<string, array<string, string>>}
     */
    private static function data(): array
    {
        if (self::$data === null) {
            $json = json_decode((string) @file_get_contents(dirname(__DIR__).'/dist/cp.json'), true);
            self::$data = [
                'categories' => (array) ($json['categories'] ?? []),
                'services' => (array) ($json['services'] ?? []),
                'texts' => (array) ($json['texts'] ?? []),
            ];
        }

        return self::$data;
    }
}
