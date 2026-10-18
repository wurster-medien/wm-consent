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

Einstellungen im Control Panel unter **Werkzeuge → Cookie-Banner** (Recht „Cookie-Banner verwalten“, Statamic 5 und
6): Allgemein (an/aus, Darstellung, Position, Unschärfe, Links, GA-ID, Dienste), Farben (hell/dunkel), Cookies (eigene
Einträge), Texte. Gespeichert in `content/wm-consent.yaml` (mit der Seite im Git), `config/wm-consent.php` ist nur die
Voreinstellung. „Beim Speichern alle neu fragen“ erhöht die Revision.

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
  auch im statischen Cache. Das gilt auch für Skripte und iframes, die ein Lazy-Loader der Seite erst später lädt
  (`data-lazy-src` u. ä.): sie warten dann auf die Zustimmung statt auf das Scrollen. Bewertungs-Widgets (Tripadvisor,
  HolidayCheck, wellness-hotel.info) laufen unter „Externe Medien“.
- Eigene Skripte: `{{ wm_consent:script service="linkedin" }}<script>…</script>{{ /wm_consent:script }}` oder
  `category="analytics|marketing|media"`.
- Google Analytics über `google_analytics_id` in der Config (bzw. `WM_CONSENT_GA_ID`), das eigene GA-Snippet dann
  entfernen.
- Alten Banner entfernen: `{{ cookie_modal }}` (Cookie Byte) bzw. `{{ consent_manager }}` und das Paket.

Lokal mit Path-Repository einbinden wie beim Social Hub (`"type": "path", "url": "../wm-consent"`), nicht committen.

## Cookie-Liste

In den Einstellungen des Banners steht je Kategorie jeder Dienst mit Anbieter und Datenschutz-Link, darunter jeder
Eintrag mit Dauer und Zweck in einem Satz, ohne Pflegeaufwand:

- **Dienste** bringen ihre Vorlage mit (`resources/js/services.js`, laut Anbieter, Stand 10/2026) und erscheinen, sobald
  der Filter sie auf der Seite findet oder sie unter `services` stehen.
- **Session- und XSRF-Cookie** trägt das Statamic-Addon selbst ein (Name und Laufzeit aus `config/session.php`), das
  MODX-Snippet die PHP-Session; abschalten mit `auto_cookies => false`. Die Cookies des Banners stehen immer drin.
- **Eigenes** der Seite (Warenkorb im Local Storage, Popup-Status …) unter `cookies`. Zum Finden die Seite mit
  `#wm-consent-inventar` am Ende der Adresse öffnen: die Browser-Konsole listet alles noch nicht Beschriebene samt
  Schnipsel zum Einfügen (HttpOnly-Cookies sieht das Skript nicht, die kommen über `auto_cookies`).

## Sprachen und mehrere Websites

- Der Banner spricht **Deutsch, Englisch und Französisch** (Texte, Kategorien, Cookie-Liste; `resources/js/texts.js`
  und `services.js`), andere Sprachen bekommen Englisch. Die Sprache kommt von selbst: Statamic aus der aktuellen
  Site, MODX aus dem `cultureKey` des Kontexts, sonst aus `<html lang>`.
- **Statamic:** Unter „Texte“ steht je Sprache der Sites ein Abschnitt. Bei mehreren Sites (z. B. site1/de,
  site1/en, site2/de) zeigt der Tab „Websites“ je Site Datenschutz, Impressum und GA-Mess-ID; leer heißt: Wert aus
  „Allgemein“. Gespeichert als `texts.<sprache>` und `sites.<site-handle>`.
- **MODX:** im Chunk `texts` je Sprache (`{"de": {…}, "en": {…}}`) und `sites` je Kontext, siehe `modx/README.md`.
- Flache `texts` (ohne Sprache) gelten für alle Sprachen. Beschreibung und Dauer eigener Cookies gehen auch je Sprache:
  `"description": {"de": "…", "en": "…"}`.
- Parameter am Tag gehen für Sonderfälle weiter vor, z. B. `{{ wm_consent lang="en" }}` oder
  `{{ wm_consent :google_analytics_id="site:attributes:gtag" }}` (auch `privacy_url`, `imprint_url`, `mode`,
  `position`, `revision`, `services`).

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
