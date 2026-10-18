<?php

/**
 * Lädt den Kern des Cookie-Banners für MODX (ohne Composer) und liest die Einstellungen der Seite aus dem Chunk
 * „wmConsentConfig“ (JSON, Schlüssel wie in config/wm-consent.php des Statamic-Addons). Für MODX 2 und 3.
 */

require_once __DIR__.'/src/Core/Lang.php';
require_once __DIR__.'/src/Core/Services.php';
require_once __DIR__.'/src/Core/HeadTag.php';
require_once __DIR__.'/src/Core/OutputFilter.php';

if (! function_exists('wm_consent_settings')) {
    /**
     * @param  object  $modx  modX (MODX 2) bzw. MODX\Revolution\modX (MODX 3)
     * @return array<string, mixed>
     */
    function wm_consent_settings($modx): array
    {
        static $settings = null;

        if ($settings === null) {
            $json = (string) $modx->getChunk('wmConsentConfig');
            $decoded = json_decode(trim($json), true);
            $settings = is_array($decoded) ? $decoded : [];

            if ($json !== '' && ! is_array($decoded)) {
                $modx->log(1, '[wm-consent] Chunk wmConsentConfig ist kein gültiges JSON, Banner läuft mit Standardwerten.');
            }
        }

        return $settings;
    }

    /** Version für die Adressen von Skript und Stylesheet (ändert sich mit jedem neuen Build). */
    function wm_consent_version(string $assetsPath): string
    {
        return substr(md5(\WursterMedien\Consent\Core\HeadTag::VERSION.@filemtime($assetsPath.'wm-consent.js').@filemtime($assetsPath.'wm-consent.css')), 0, 10);
    }
}
