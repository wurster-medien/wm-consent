<?php

/*
 * Cookie-Banner von Wurster Medien, Einstellungen dieser Seite.
 * Veröffentlichen: php artisan vendor:publish --tag=wm-consent-config
 */

return [

    /* Banner, Kopf-Tag und Ausgabefilter an (false: {{ wm_consent }} gibt nichts aus, nichts wird blockiert). */
    'enabled' => env('WM_CONSENT_ENABLED', true),

    /* Bekannte Tracker und Einbettungen beim Ausliefern blockieren (src/Core/OutputFilter.php). */
    'filter' => true,

    /* auto (folgt dem System), light oder dark. */
    'mode' => 'auto',

    /* bottom right, bottom left, bottom center oder middle center. */
    'position' => 'bottom right',

    /* Farben je Modus: Hintergrund, Text, Akzent (Knöpfe, Schalter); highlight_text nur, wenn die Schrift auf dem Akzent nicht passt. */
    'colors' => [
        'light' => [
            'background' => '#ffffff',
            'text' => '#2c2f31',
            'highlight' => '#14b8a6',
        ],
        'dark' => [
            'background' => '#161a1c',
            'text' => '#ebf3f6',
            'highlight' => '#2dd4bf',
        ],
    ],

    'privacy_url' => '/datenschutz',

    'imprint_url' => '/impressum',

    /* Texte ersetzen, z. B. 'title' => 'Ihre Privatsphäre'. Schlüssel siehe resources/js/texts.js. */
    'texts' => [],

    /* Dienste, die immer im Banner stehen sollen, auch wenn der Filter sie auf der Seite nicht findet (z. B. 'google_analytics'). */
    'services' => [],

    /* Mess-ID von Google Analytics: der Kopf-Tag bindet GA selbst ein, erst nach Zustimmung. Eigenes GA im Template dann entfernen. */
    'google_analytics_id' => env('WM_CONSENT_GA_ID'),

    /* Google Consent Mode v2: alles „abgelehnt“ als Voreinstellung, nach der Auswahl aktualisiert. */
    'consent_mode' => true,

    /* Erhöhen, wenn sich Dienste oder Texte wesentlich ändern: alle Besucher werden neu gefragt. */
    'revision' => 0,

    'cookie_days' => 182,

    /* Runder Knopf unten links, um die Auswahl wieder zu öffnen (sonst Link mit {{ wm_consent:link }} im Footer). */
    'floating_button' => false,

    /* Platzhalter für Videos und Karten: „Immer laden“ (Akzentfarbe) und auf Wunsch zusätzlich „Einmal laden“. */
    'load_once' => false,

];
