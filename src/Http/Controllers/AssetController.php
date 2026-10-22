<?php

namespace WursterMedien\Consent\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use WursterMedien\Consent\AgencyStyles;

/**
 * Liefert dist/wm-consent.js bzw. das Stylesheet aus: das zentrale der Agentur (AgencyStyles), sonst dist/wm-consent.css
 * aus dem Paket. Die Adresse trägt die Version (?v=), deshalb darf der Browser lange zwischenspeichern. Passt ?v= nicht
 * zum Stylesheet, das jetzt gilt (Seite aus dem statischen Cache von vor einer Änderung), nur eine Stunde, damit die
 * neue Fassung bald ankommt. Immer als Datei: der statische Cache von Statamic speichert solche Antworten nicht.
 */
class AssetController
{
    public function __invoke(Request $request, AgencyStyles $styles, string $file): BinaryFileResponse
    {
        $css = str_ends_with($file, '.css');
        $path = $css ? $styles->path() : dirname(__DIR__, 3).'/dist/'.basename($file);

        abort_unless(is_file($path), 404);

        $current = ! $css || $request->query('v') === $styles->version();

        return response()->file($path, [
            'Content-Type' => $css ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8',
            'Cache-Control' => $current ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
        ]);
    }
}
