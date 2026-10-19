# Cookie-Banner von Wurster Medien für MODX (2 und 3)

## Einspielen

1. `build/wm-consent-modx.zip` (bauen mit `php tools/build-modx.php` im Repo) entpacken und per SFTP hochladen:
   - `core/components/wm-consent/` nach `core/components/wm-consent/`
   - `assets/components/wm-consent/` nach `assets/components/wm-consent/`
2. Im Manager unter Elemente anlegen, alle als **statisches Element** (Medienquelle „Filesystem“):
   - **Snippet** `wmConsent`, Datei `core/components/wm-consent/elements/snippets/wmconsent.snippet.php`
   - **Plugin** `wmConsentFilter`, Datei `core/components/wm-consent/elements/plugins/wmconsent.plugin.php`,
     Systemereignis **OnWebPagePrerender** anhaken
   - **Chunk** `wmConsentConfig` mit den Einstellungen als JSON (Vorlage `wmConsentConfig.example.json`)
3. In jedes Template in den `<head>`, möglichst weit oben: `[[!wmConsent]]` (ungecacht, mit Ausrufezeichen).
4. Im Footer der Link zum Ändern der Auswahl:
   `<a href="#cookie-einstellungen" data-wm-consent-open>Cookie-Einstellungen</a>`
5. Alten Banner (CookieFirst o. Ä.) aus den Templates entfernen, Cache leeren.

## Einspielen per SSH (ohne Manager)

Das Paket enthält `wm-consent-site.php`. Paketordner als Benutzer der Seite hochladen (z. B. nach `~/wm-consent-pkg`), dann:

```bash
T="php ~/wm-consent-pkg/wm-consent-site.php --root=/home/kunde/kunde.de"
$T info                                    # Version, Kontexte, Kopf-Chunks, alter Banner, Tracking
$T install --config=~/site-config.json     # Dateien, Snippet, Plugin, Chunk wmConsentConfig
$T head --only=chunk:START --apply         # [[!wmConsent]] in den <head> (nie in Mail-Vorlagen)
$T get chunk JS > JS.html                  # ändern, dann:
$T put chunk JS JS.html
```

Jede Änderung sichert vorher alle Chunks und Templates nach `~/wm-consent-backup/…` (`restore`, `uninstall`).
Statische Elemente schreibt es in ihre Datei. Läuft mit MODX 2 und 3, auch bei halb aktualisierten Seiten.

## Was dann passiert

- Das Plugin blockt bekannte Tracker (Google Analytics, Tag Manager, Meta Pixel, LinkedIn, Hotjar …) und macht
  aus YouTube-, Vimeo-, Maps-, Instagram- und Facebook-Einbettungen Platzhalter mit „Immer laden“ (auf Wunsch
  zusätzlich „Einmal laden“, `load_once`).
  Im Inhalt muss niemand etwas markieren.
- Eigene Skripte, die der Filter nicht kennt, von Hand blockieren:
  `<script type="text/plain" data-category="analytics">…</script>` (Kategorien `analytics`, `marketing`, `media`).
- Google Analytics kann der Banner selbst einbinden: `"google_analytics_id": "G-…"` im Chunk, das eigene GA dann
  aus dem Template entfernen.
- Ein Skript soll trotz Treffer sofort laufen: Attribut `data-wm-consent-ignore`.

## Einstellungen (Chunk `wmConsentConfig`)

| Schlüssel | Bedeutung |
| --- | --- |
| `mode` | `auto` (folgt dem System), `light` oder `dark` |
| `colors.light` / `colors.dark` | `background`, `text`, `highlight` (Akzent); Nebenflächen, Nebentext und Linien ergeben sich aus dem Text mit Transparenz |
| `overlay_opacity` | Deckkraft der Fläche über der Seite in % (0–90, Standard 50), hell in Textfarbe, dunkel in Hintergrundfarbe |
| `position` | `top`, `middle` oder `bottom` mit `left`, `center` oder `right`, z. B. `middle center` |
| `blur` | Hintergrund unscharf und gesperrt bis zur Auswahl (Standard `true`) |
| `blur_strength` | Stärke der Unschärfe in px, 0–30 (Standard 8) |
| `privacy_url`, `imprint_url` | Links im Banner |
| `texts` | einzelne Texte ersetzen: flach für alle Sprachen, z. B. `{"title": "Ihre Privatsphäre"}`, oder je Sprache, z. B. `{"de": {"title": "…"}, "en": {"title": "…"}}` |
| `lang` | Sprache erzwingen (`de`, `en`, `fr` …); sonst `cultureKey` des Kontexts, wenn er ihn selbst setzt, sonst `<html lang>` |
| `sites` | Werte je Kontext, die vorgehen: `lang`, `privacy_url`, `imprint_url`, `google_analytics_id`, `texts`, siehe unten |
| `services` | Dienste immer zeigen, auch wenn der Filter sie nicht findet |
| `google_analytics_id` | GA4-Mess-ID, wird erst nach Zustimmung geladen |
| `consent_mode` | Google Consent Mode v2 (Standard `true`) |
| `revision` | erhöhen, wenn alle neu gefragt werden sollen |
| `floating_button` | runder Knopf unten links zum Wieder-Öffnen |
| `load_once` | Platzhalter zusätzlich mit „Einmal laden“ (sonst nur „Immer laden“ in der Akzentfarbe) |
| `cookies` | eigene Einträge der Cookie-Liste: `name`, `type` (`cookie`, `local`, `session`), `duration`, `description` (beide auch je Sprache, z. B. `{"de": "1 Tag", "en": "1 day"}`), optional `category`, `provider` und `privacy` (Link zum Datenschutz des Anbieters) |
| `auto_cookies` | PHP-Session-Cookie von selbst in die Liste (Standard `true`) |
| `enabled`, `filter` | `false` schaltet den Banner bzw. nur den Filter ab |

## Mehrsprachig und mehrere Kontexte

Der Banner spricht Deutsch, Englisch und Französisch (andere Sprachen: Englisch). Ein Chunk für alle Kontexte; was
je Kontext abweicht, steht unter `sites` mit dem Kontext-Schlüssel. Leere Werte übernehmen die allgemeinen.

```json
{
    "privacy_url": "/datenschutz",
    "imprint_url": "/impressum",
    "google_analytics_id": "G-AAAAAAAAAA",
    "texts": { "de": { "title": "Ihre Privatsphäre" }, "en": { "title": "Your privacy" } },
    "sites": {
        "en": { "lang": "en", "privacy_url": "/en/privacy-policy", "imprint_url": "/en/legal-notice" },
        "fr": { "lang": "fr", "privacy_url": "/fr/confidentialite", "imprint_url": "/fr/mentions-legales" }
    }
}
```

`lang` je Kontext nur nötig, wenn der Kontext keinen eigenen `cultureKey` hat und `<html lang>` im Template nicht
stimmt. Bestehende Chunks ohne `sites` laufen unverändert weiter.

## Cookie-Liste

Jeder Dienst, den der Filter auf der Seite findet, bringt seine Vorlage mit (Anbieter, Datenschutz-Link, Cookies mit
Laufzeit). Eigenes (Warenkorb im Local Storage, Popup-Status …) unter `cookies` eintragen. Zum Finden die Seite mit
`#wm-consent-inventar` am Ende der Adresse öffnen: die Browser-Konsole listet alle Cookies und Speichereinträge, die
noch nirgends beschrieben sind, samt Schnipsel zum Einfügen.
