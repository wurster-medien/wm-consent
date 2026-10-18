<?php

namespace WursterMedien\Consent\Core;

/**
 * Blockt beim Ausliefern, was erst nach Zustimmung laden darf (Statamic: Middleware, MODX: Plugin auf
 * OnWebPagePrerender), damit niemand im Inhalt oder Template etwas markieren muss:
 *
 * - Skripte bekannter Dienste (Services::SCRIPTS) werden type="text/plain" mit data-category und data-service; der
 *   Banner führt sie nach Zustimmung aus. Ausgenommen: schon markierte, nicht ausführbare (JSON …) und alles mit
 *   data-wm-consent-ignore. Steht die Adresse nur in einem Attribut für einen Lazy-Loader der Seite (data-lazy-src
 *   …), wird sie zum src, damit der Lazy-Loader das Skript nicht ohne Zustimmung lädt.
 * - iframes bekannter Dienste (Services::FRAMES, Adresse auch aus data-lazy-src …) werden Platzhalter für
 *   iframemanager (div data-service mit der Adresse als data-id), YouTube über youtube-nocookie.com.
 * - <noscript> mit dem Tag Manager fällt weg (lädt sonst ohne Zustimmung, wenn JavaScript aus ist).
 *
 * Die gefundenen Dienste trägt der Filter in die Einstellungen des Kopf-Tags ein (`detected`), damit der Banner genau
 * die Kategorien zeigt, die auf der Seite vorkommen. Arbeitet mit regulären Ausdrücken statt DOM, damit das übrige
 * HTML Zeichen für Zeichen bleibt. PHP 7.4-tauglich.
 */
final class OutputFilter
{
    private const EXECUTABLE = ['', 'text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript'];

    /**
     * @return array{html: string, detected: list<string>}
     */
    public static function process(string $html): array
    {
        if (stripos($html, '<script') === false && stripos($html, '<iframe') === false) {
            return ['html' => $html, 'detected' => []];
        }

        $detected = [];

        $html = (string) preg_replace_callback('#<noscript\b[^>]*>.*?</noscript>#is', function (array $match): string {
            return stripos($match[0], 'googletagmanager.com/ns.html') !== false ? '' : $match[0];
        }, $html);

        $html = (string) preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', function (array $match) use (&$detected): string {
            $attributes = $match[1];

            if (self::hasAttribute($attributes, 'data-wm-consent-ignore') || self::hasAttribute($attributes, 'data-category')) {
                return $match[0];
            }

            $type = strtolower(trim((string) self::attribute($attributes, 'type')));

            if (! in_array($type, self::EXECUTABLE, true)) {
                return $match[0];
            }

            $src = (string) self::attribute($attributes, 'src');
            $lazy = $src === '' ? self::lazySource($attributes) : null;
            $service = Services::forScript($src !== '' ? $src : ($lazy[1] ?? ''), $match[2]);

            if ($service === null) {
                return $match[0];
            }

            $detected[] = $service;
            $attributes = self::withoutAttribute($attributes, 'type');

            if ($lazy !== null) {
                $attributes = self::withoutAttribute($attributes, $lazy[0]).' src="'.self::escape(html_entity_decode($lazy[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')).'"';
            }

            return '<script type="text/plain" data-category="'.Services::category($service).'" data-service="'.$service.'"'.$attributes.'>'.$match[2].'</script>';
        }, $html);

        $html = (string) preg_replace_callback('#<iframe\b([^>]*)>.*?</iframe>#is', function (array $match) use (&$detected): string {
            $attributes = $match[1];

            if (self::hasAttribute($attributes, 'data-wm-consent-ignore')) {
                return $match[0];
            }

            $src = (string) self::attribute($attributes, 'src');
            $src = html_entity_decode($src !== '' && $src !== 'about:blank' ? $src : (self::lazySource($attributes)[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $service = $src !== '' ? Services::forFrame($src) : null;

            if ($service === null) {
                return $match[0];
            }

            $detected[] = $service;

            return self::placeholder($service, $src, $attributes);
        }, $html);

        $detected = array_values(array_unique($detected));

        return ['html' => $detected === [] ? $html : self::withDetected($html, $detected), 'detected' => $detected];
    }

    public static function filter(string $html): string
    {
        return self::process($html)['html'];
    }

    /**
     * Platzhalter für iframemanager mit Titel, Klasse und Größe des iframes: Videos 16:9, sonst die Höhe des iframes;
     * bestimmt die Klasse die Größe schon (h-full, aspect-…), bleibt es bei ihr.
     */
    private static function placeholder(string $service, string $src, string $attributes): string
    {
        if ($service === 'youtube') {
            $src = (string) preg_replace('#^(https?:)?//(www\.)?youtube\.com/embed/#i', 'https://www.youtube-nocookie.com/embed/', $src);
        }

        if (strpos($src, '//') === 0) {
            $src = 'https:'.$src;
        }

        $title = (string) self::attribute($attributes, 'title');
        $height = (string) self::attribute($attributes, 'height');
        $class = (string) self::attribute($attributes, 'class');
        $video = in_array($service, ['youtube', 'vimeo'], true);
        $sizedByClass = preg_match('/(^|\s)(h-full|h-screen|aspect-[^\s]+)(\s|$)/', $class) === 1;
        $style = ! $video && ! $sizedByClass && preg_match('/^\d+$/', $height) === 1 ? ' style="height:'.$height.'px"' : '';

        return '<div data-service="'.$service.'" data-id="'.self::escape($src).'"'
            .($title !== '' ? ' data-title="'.self::escape($title).'"' : '')
            .($class !== '' ? ' class="'.self::escape($class).'"' : '')
            .($video && ! $sizedByClass ? ' data-autoscale' : '')
            .$style.'></div>';
    }

    /**
     * Gefundene Dienste in die Einstellungen des Kopf-Tags (<script type="application/json" id="wm-consent-config">).
     *
     * @param  list<string>  $detected
     */
    private static function withDetected(string $html, array $detected): string
    {
        return (string) preg_replace_callback('#(<script\b[^>]*\bid="wm-consent-config"[^>]*>)(.*?)(</script>)#is', function (array $match) use ($detected): string {
            $config = json_decode($match[2], true);

            if (! is_array($config)) {
                return $match[0];
            }

            $config['detected'] = array_values(array_unique(array_merge((array) ($config['detected'] ?? []), $detected)));

            return $match[1].HeadTag::json($config).$match[3];
        }, $html, 1);
    }

    private static function attribute(string $attributes, string $name): ?string
    {
        if (preg_match('/(?:^|\s)'.preg_quote($name, '/').'\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributes, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        return $match[1] ?? $match[2] ?? $match[3];
    }

    /**
     * Adresse, die ein Lazy-Loader der Seite später als src setzt (data-src, data-lazy-src, data-rocket-src …), als
     * [Attribut, Wert]; null ohne.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function lazySource(string $attributes): ?array
    {
        if (preg_match('/(?:^|\s)(data-[a-z0-9-]*src)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributes, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        $value = (string) ($match[2] ?? $match[3] ?? $match[4]);

        return $value !== '' ? [$match[1], $value] : null;
    }

    private static function hasAttribute(string $attributes, string $name): bool
    {
        return preg_match('/(?:^|\s)'.preg_quote($name, '/').'(?:\s*=|\s|$)/i', $attributes) === 1;
    }

    private static function withoutAttribute(string $attributes, string $name): string
    {
        return (string) preg_replace('/\s'.preg_quote($name, '/').'\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attributes);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
