<?php

namespace WursterMedien\Consent\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert dist/wm-consent.js bzw. .css aus dem Paket aus. Die Adresse trägt die Version (?v=), deshalb darf der
 * Browser lange zwischenspeichern.
 */
class AssetController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $path = dirname(__DIR__, 3).'/dist/'.basename($file);

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($file, '.css') ? 'text/css; charset=UTF-8' : 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
