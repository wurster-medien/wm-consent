<?php

namespace WursterMedien\Consent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WursterMedien\Consent\Content\ContentMap;
use WursterMedien\Consent\Content\ContentNeeds;
use WursterMedien\Consent\Content\DemoStand;

/**
 * Inhalte der Seiten für den Kundenbereich (Simon, 3.11.2026: Platzhalter prüfen, Freigaben mit Fingerabdruck): je Seite
 * Stellen und Bereiche mit Fingerabdruck, Demo-Stellen mit Text bzw. Bild. Signiert wie StatusController, nur mit
 * "wm-consent-content:{timestamp}"; ohne Schlüssel oder mit falscher Signatur 404. Ab 1.4 dazu needs: Sammlungen und Globals
 * mit ihren Feldern für die Liste „Inhalte liefern“ (ContentNeeds), Vorgaben je Stelle unter need (FieldNeeds).
 */
class ContentController
{
    public function __invoke(Request $request, ContentMap $content, ContentNeeds $needs, DemoStand $demo): JsonResponse
    {
        $key = StatusController::key();

        abort_if($key === null || ! StatusController::verified($request, $key, 'wm-consent-content'), 404);

        return response()->json([
            'demo_stand' => $demo->rememberedAt(),
            'pages' => $content->pages(),
            'needs' => ['collections' => $needs->collections(), 'globals' => $needs->globals()],
        ], 200, [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
            'X-Statamic-Uncacheable' => 'true',
        ]);
    }
}
