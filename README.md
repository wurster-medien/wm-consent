# wm-consent

Cookie-Banner von Wurster Medien für unsere Kundenseiten, ersetzt CookieFirst, Cookie Byte und Consent Manager.
Ein Kern für alle Systeme:

- **JS/CSS** (`resources/`, gebaut nach `dist/`): [vanilla-cookieconsent](https://github.com/orestbida/cookieconsent)
  3.1.0 und [iframemanager](https://github.com/orestbida/iframemanager) 1.3.0 (beide MIT), deutsche Texte,
  Farben je Seite für hell und dunkel (Hintergrund, Text, Akzent), „Alle akzeptieren“ und „Nur notwendige“
  gleichwertig, Google Consent Mode v2, Platzhalter für Videos und Karten ohne Vorschaubild von Google.
- **PHP-Kern** (`src/Core`, ohne Abhängigkeiten, PHP 7.4-tauglich): `HeadTag` (Ausgabe im `<head>`),
  `OutputFilter` (blockt bekannte Tracker und Einbettungen beim Ausliefern), `Services` (Dienste und Muster).
- **Statamic 5 und 6**: Addon in diesem Repo (`src/ServiceProvider.php`, Tag `{{ wm_consent }}`).
- **MODX 2 und 3**: Snippet, Plugin und Chunk, Anleitung in [`modx/README.md`](modx/README.md).

Der Kundenbereich (konto.wurster-medien.de, „Websites prüfen“) erkennt den Banner am Merkmal `data-wm-consent`.

## Statamic

```bash
composer require wurster-medien/wm-consent
php artisan vendor:publish --tag=wm-consent-config   # config/wm-consent.php: Farben, Links, GA-ID
```

Im Layout:

```antlers
<head>
    {{ wm_consent }}
    …
</head>
…
<footer>{{ wm_consent:link class="…" }}Cookie-Einstellungen{{ /wm_consent:link }}</footer>
```

- Bekannte Tracker und Einbettungen (auch im Bard-Inhalt) blockt die Middleware von selbst; das Ergebnis landet so
  auch im statischen Cache.
- Eigene Skripte: `{{ wm_consent:script service="linkedin" }}<script>…</script>{{ /wm_consent:script }}` oder
  `category="analytics|marketing|media"`.
- Google Analytics über `google_analytics_id` in der Config (bzw. `WM_CONSENT_GA_ID`), das eigene GA-Snippet dann
  entfernen.
- Alten Banner entfernen: `{{ cookie_modal }}` (Cookie Byte) bzw. `{{ consent_manager }}` und das Paket.

Lokal mit Path-Repository einbinden wie beim Social Hub (`"type": "path", "url": "../wm-consent"`), nicht committen.

## Entwickeln

```bash
npm install && npm run build      # dist/ neu bauen (wird committet)
composer install && vendor/bin/phpunit
php tools/build-modx.php          # build/wm-consent-modx.zip
```

`demo/index.html` zeigt den Banner mit Beispiel-Einstellungen (Datei im Browser öffnen, nach `npm run build`).

Neue Dienste in `src/Core/Services.php` (Muster) **und** `resources/js/services.js` (Name, Kategorie, Cookies)
eintragen, im Kundenbereich ggf. in `WebsiteSignals::SERVICES`.

## Bewusst nicht drin

- Kein Protokoll der Einwilligungen (Simon, 17.10.2026: vorerst ohne). Die Auswahl steht nur im Cookie
  `wm_consent` des Besuchers.
- Google Fonts lassen sich nicht sinnvoll per Zustimmung schalten: lokal einbinden (die Prüfung im Kundenbereich
  meldet sie).
- reCAPTCHA blockt der Filter nicht (Formulare gingen sonst nicht); besser durch eine Alternative ersetzen.
