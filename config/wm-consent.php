<?php

/*
 * Cookie-Banner von Wurster Medien, Einstellungen dieser Seite.
 * Veröffentlichen: php artisan vendor:publish --tag=wm-consent-config
 */

return [

    /* Banner, Kopf-Tag und Ausgabefilter an (false: {{ wm_consent }} gibt nichts aus, nichts wird blockiert). */
    'enabled' => env('WM_CONSENT_ENABLED', true),

    /* Was im Control Panel (Werkzeuge → Cookie-Banner) gespeichert ist; geht vor den Werten in dieser Datei. */
    'settings_path' => base_path('content/wm-consent.yaml'),

    /* Bekannte Tracker und Einbettungen beim Ausliefern blockieren (src/Core/OutputFilter.php). */
    'filter' => true,

    /* auto (folgt dem System), light oder dark. */
    'mode' => 'auto',

    /* top, middle oder bottom mit left, center oder right, z. B. middle center (mittig). */
    'position' => 'bottom right',

    /* Hintergrund unscharf und gesperrt, bis Besucher gewählt haben; Stärke der Unschärfe in px (0–30). Farbe darüber: colors.*.overlay. */
    'blur' => true,

    'blur_strength' => 8,

    /* Deckkraft der Fläche über der Seite, solange der Banner offen ist, in % (0–90); hell in Textfarbe, dunkel in Hintergrundfarbe. */
    'overlay_opacity' => 50,

    /*
     * Farben je Modus: Hintergrund, Text, Akzent (Knöpfe, Schalter). Nebenflächen, Nebentext und Linien ergeben sich
     * aus dem Text mit Transparenz, die Schrift auf dem Akzent (weiß oder dunkel) aus dessen Helligkeit.
     */
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

    /*
     * Texte ersetzen, flach für alle Sprachen ('title' => 'Ihre Privatsphäre') oder je Sprache ('en' => ['title' => 'Your
     * privacy']). Schlüssel siehe resources/js/texts.js; Standardtexte gibt es auf Deutsch, Englisch und Französisch.
     */
    'texts' => [],

    /*
     * Werte je Statamic-Site (Handle), die vorgehen: lang, privacy_url, imprint_url, google_analytics_id, texts. Pflege
     * besser im Control Panel (Tab „Websites“), z. B. 'site1_en' => ['privacy_url' => '/en/privacy'].
     */
    'sites' => [],

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

    /*
     * Cookie-Liste in den Einstellungen des Banners: Dienste bringen ihre Vorlage selbst mit (resources/js/services.js),
     * Session- und XSRF-Cookie trägt das Addon von selbst ein (auto_cookies). Hier nur, was die Seite selbst speichert,
     * z. B. ['name' => 'warenkorb', 'type' => 'local', 'duration' => 'bis zum Löschen', 'description' => 'Merkt sich die Auswahl.'].
     * type: cookie, local (Local Storage) oder session (Session Storage); category: necessary (Standard), analytics,
     * marketing, media. Finden: Seite mit #wm-consent-inventar öffnen, die Konsole listet alles Unbeschriebene.
     */
    'cookies' => [],

    'auto_cookies' => true,

];
