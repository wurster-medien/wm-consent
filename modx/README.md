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
| `colors.light` / `colors.dark` | `background`, `text`, `highlight` (Akzent), optional `highlight_text`, `muted` (Nebenflächen), `muted_text` (Nebentext), `border` (Linien), `overlay` (Farbe über der Seite, z. B. `rgba(51, 65, 85, .7)`), `heading` (Überschriften) |
| `position` | `top`, `middle` oder `bottom` mit `left`, `center` oder `right`, z. B. `middle center` |
| `blur` | Hintergrund unscharf und gesperrt bis zur Auswahl (Standard `true`) |
| `blur_strength` | Stärke der Unschärfe in px, 0–30 (Standard 8) |
| `privacy_url`, `imprint_url` | Links im Banner |
| `texts` | einzelne Texte ersetzen, z. B. `{"title": "Ihre Privatsphäre"}` |
| `services` | Dienste immer zeigen, auch wenn der Filter sie nicht findet |
| `google_analytics_id` | GA4-Mess-ID, wird erst nach Zustimmung geladen |
| `consent_mode` | Google Consent Mode v2 (Standard `true`) |
| `revision` | erhöhen, wenn alle neu gefragt werden sollen |
| `floating_button` | runder Knopf unten links zum Wieder-Öffnen |
| `load_once` | Platzhalter zusätzlich mit „Einmal laden“ (sonst nur „Immer laden“ in der Akzentfarbe) |
| `cookies` | eigene Einträge der Cookie-Liste: `name`, `type` (`cookie`, `local`, `session`), `duration`, `description`, optional `category` und `provider` |
| `auto_cookies` | PHP-Session-Cookie von selbst in die Liste (Standard `true`) |
| `enabled`, `filter` | `false` schaltet den Banner bzw. nur den Filter ab |

## Cookie-Liste

Jeder Dienst, den der Filter auf der Seite findet, bringt seine Vorlage mit (Anbieter, Datenschutz-Link, Cookies mit
Laufzeit). Eigenes (Warenkorb im Local Storage, Popup-Status …) unter `cookies` eintragen. Zum Finden die Seite mit
`#wm-consent-inventar` am Ende der Adresse öffnen: die Browser-Konsole listet alle Cookies und Speichereinträge, die
noch nirgends beschrieben sind, samt Schnipsel zum Einfügen.
