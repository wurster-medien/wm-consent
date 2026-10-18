/**
 * Dienste und Kategorien mit Vorlagen für die Cookie-Liste (Anbieter, Datenschutz, einzelne Cookies mit Laufzeit,
 * laut Anbieter, Stand 10/2026). Der Banner zeigt sie von selbst für jeden Dienst, der auf der Seite vorkommt.
 * Die Schlüssel sind dieselben wie in src/Core/Services.php (dort die Muster, an denen der Ausgabefilter sie erkennt);
 * neue Dienste an beiden Stellen eintragen. `name` mit * am Ende ist ein Präfix (z. B. _ga_* für _ga_ABC123), `more`:
 * dazu weitere Cookies („u. a.“). `widget`: Skript ohne Platzhalter (Bewertungen), lädt mit der Zustimmung zu
 * „Externe Medien“. Texte je Sprache ({ de, en, fr }, siehe texts.js), Laufzeiten und Anbieter über die Helfer unten.
 */
import { LANGUAGES } from './texts.js';

const each = (text) => Object.fromEntries(LANGUAGES.map((lang) => [lang, text(lang)]));

const UNITS = {
    year: { de: ['Jahr', 'Jahre'], en: ['year', 'years'], fr: ['an', 'ans'] },
    month: { de: ['Monat', 'Monate'], en: ['month', 'months'], fr: ['mois', 'mois'] },
    day: { de: ['Tag', 'Tage'], en: ['day', 'days'], fr: ['jour', 'jours'] },
    minute: { de: ['Minute', 'Minuten'], en: ['minute', 'minutes'], fr: ['minute', 'minutes'] },
};

/** Laufzeit, z. B. period(2, 'year') → 2 Jahre / 2 years / 2 ans. */
export const period = (count, unit) => each((lang) => `${count} ${UNITS[unit][lang][count === 1 ? 0 : 1]}`);
const upTo = (count, unit) => each((lang) => `${{ de: 'bis', en: 'up to', fr: 'jusqu’à' }[lang]} ${period(count, unit)[lang]}`);
const sessionUpTo = (count, unit) => each((lang) => `${{ de: 'Sitzung bis', en: 'Session up to', fr: 'Session jusqu’à' }[lang]} ${period(count, unit)[lang]}`);
const until = (year) => ({ de: `bis ${year}`, en: `until ${year}`, fr: `jusqu’en ${year}` });
const SESSION = { de: 'Sitzung', en: 'Session', fr: 'Session' };

const COUNTRIES = {
    IE: { de: 'Irland', en: 'Ireland', fr: 'Irlande' },
    US: { de: 'USA', en: 'USA', fr: 'États-Unis' },
    MT: { de: 'Malta', en: 'Malta', fr: 'Malte' },
    GB: { de: 'Vereinigtes Königreich', en: 'United Kingdom', fr: 'Royaume-Uni' },
    CH: { de: 'Schweiz', en: 'Switzerland', fr: 'Suisse' },
    AT: { de: 'Österreich', en: 'Austria', fr: 'Autriche' },
};
const company = (name, country) => each((lang) => `${name}, ${COUNTRIES[country][lang]}`);

export const CATEGORIES = {
    necessary: {
        title: { de: 'Notwendig', en: 'Necessary', fr: 'Nécessaires' },
        description: {
            de: 'Für den Betrieb der Website nötig, z. B. um Ihre Auswahl zu diesem Banner zu speichern. Lassen sich nicht abschalten.',
            en: 'Required to run the website, e.g. to store your choice in this banner. They cannot be switched off.',
            fr: 'Indispensables au fonctionnement du site, p. ex. pour enregistrer votre choix dans ce bandeau. Ils ne peuvent pas être désactivés.',
        },
    },
    analytics: {
        title: { de: 'Statistik', en: 'Analytics', fr: 'Statistiques' },
        description: {
            de: 'Helfen uns zu verstehen, wie Besucher die Website nutzen, damit wir sie verbessern können.',
            en: 'Help us understand how visitors use the website so that we can improve it.',
            fr: 'Nous aident à comprendre comment les visiteurs utilisent le site afin de l’améliorer.',
        },
    },
    marketing: {
        title: { de: 'Marketing', en: 'Marketing', fr: 'Marketing' },
        description: {
            de: 'Messen den Erfolg von Werbung und zeigen Ihnen auf anderen Plattformen passende Anzeigen.',
            en: 'Measure the success of advertising and show you relevant ads on other platforms.',
            fr: 'Mesurent l’efficacité de la publicité et vous montrent des annonces adaptées sur d’autres plateformes.',
        },
    },
    media: {
        title: { de: 'Externe Medien', en: 'External media', fr: 'Médias externes' },
        description: {
            de: 'Videos, Karten, Bewertungen und Beiträge von anderen Anbietern. Erst beim Laden erfährt der Anbieter Ihre IP-Adresse.',
            en: 'Videos, maps, reviews and posts from other providers. The provider only receives your IP address once the content loads.',
            fr: 'Vidéos, cartes, avis et publications d’autres fournisseurs. Le fournisseur ne reçoit votre adresse IP qu’au chargement.',
        },
    },
};

const GOOGLE = { provider: company('Google Ireland Limited', 'IE'), privacy: 'https://policies.google.com/privacy' };
const META = { provider: company('Meta Platforms Ireland Ltd.', 'IE'), privacy: 'https://www.facebook.com/privacy/policy/' };

const VISITORS = { de: 'Unterscheidet Besucher.', en: 'Distinguishes visitors.', fr: 'Distingue les visiteurs.' };
const SESSION_STATE = { de: 'Hält die Sitzung fest.', en: 'Keeps track of the session.', fr: 'Enregistre la session.' };

export const SERVICES = {
    google_analytics: {
        label: 'Google Analytics', category: 'analytics', ...GOOGLE,
        cookies: [
            { name: '_ga', duration: period(2, 'year'), description: VISITORS },
            { name: '_ga_*', duration: period(2, 'year'), description: { de: 'Hält den Stand der Sitzung fest.', en: 'Keeps track of the session state.', fr: 'Enregistre l’état de la session.' } },
        ],
    },
    google_tag_manager: {
        label: 'Google Tag Manager', category: 'analytics', ...GOOGLE,
        cookies: [{ name: '–', duration: '–', description: { de: 'Setzt selbst keine Cookies, lädt aber die darin eingerichteten Dienste.', en: 'Sets no cookies itself, but loads the services configured in it.', fr: 'Ne dépose pas de cookies lui-même, mais charge les services qui y sont configurés.' } }],
    },
    matomo: {
        label: 'Matomo', category: 'analytics', provider: { de: 'Betreiber dieser Website (eigener Server)', en: 'Operator of this website (own server)', fr: 'Exploitant de ce site (serveur propre)' }, privacy: '',
        cookies: [
            { name: '_pk_id*', duration: period(13, 'month'), description: VISITORS },
            { name: '_pk_ses*', duration: period(30, 'minute'), description: SESSION_STATE },
        ],
    },
    hotjar: {
        label: 'Hotjar', category: 'analytics', provider: company('Hotjar Ltd.', 'MT'), privacy: 'https://www.hotjar.com/legal/policies/privacy/',
        cookies: [
            { name: '_hjSessionUser_*', duration: period(1, 'year'), description: VISITORS },
            { name: '_hjSession_*', duration: period(30, 'minute'), description: SESSION_STATE },
        ],
    },
    clarity: {
        label: 'Microsoft Clarity', category: 'analytics', provider: company('Microsoft Ireland Operations Ltd.', 'IE'), privacy: 'https://privacy.microsoft.com/privacystatement',
        cookies: [
            { name: '_clck', duration: period(1, 'year'), description: VISITORS },
            { name: '_clsk', duration: period(1, 'day'), description: { de: 'Fasst Seitenaufrufe zu einer Sitzung zusammen.', en: 'Groups page views into one session.', fr: 'Regroupe les pages vues en une session.' } },
        ],
    },
    meta_pixel: {
        label: 'Meta Pixel', category: 'marketing', ...META,
        cookies: [
            { name: '_fbp', duration: period(3, 'month'), description: { de: 'Erkennt Besucher für Werbung auf Facebook und Instagram wieder.', en: 'Recognises visitors for advertising on Facebook and Instagram.', fr: 'Reconnaît les visiteurs pour la publicité sur Facebook et Instagram.' } },
            { name: '_fbc', duration: period(3, 'month'), description: { de: 'Merkt sich den Klick auf eine Anzeige.', en: 'Remembers the click on an ad.', fr: 'Mémorise le clic sur une annonce.' } },
        ],
    },
    linkedin: {
        label: 'LinkedIn Insight', category: 'marketing', provider: company('LinkedIn Ireland Unlimited Company', 'IE'), privacy: 'https://www.linkedin.com/legal/privacy-policy',
        cookies: [
            { name: 'li_sugr', duration: period(3, 'month'), description: { de: 'Ordnet Besuche einem LinkedIn-Mitglied zu.', en: 'Links visits to a LinkedIn member.', fr: 'Associe les visites à un membre LinkedIn.' } },
            { name: 'bcookie', duration: period(1, 'year'), description: { de: 'Erkennt den Browser wieder (linkedin.com).', en: 'Recognises the browser (linkedin.com).', fr: 'Reconnaît le navigateur (linkedin.com).' } },
            { name: 'lidc', duration: period(1, 'day'), description: { de: 'Wählt das Rechenzentrum (linkedin.com).', en: 'Selects the data centre (linkedin.com).', fr: 'Choisit le centre de données (linkedin.com).' } },
            { name: 'UserMatchHistory', duration: period(30, 'day'), description: { de: 'Abgleich für Werbung (linkedin.com).', en: 'Matching for advertising (linkedin.com).', fr: 'Rapprochement pour la publicité (linkedin.com).' } },
        ],
    },
    google_ads: {
        label: 'Google Ads', category: 'marketing', ...GOOGLE,
        cookies: [{ name: '_gcl_au', duration: period(3, 'month'), description: { de: 'Misst, ob ein Besuch aus einer Anzeige zu einer Anfrage führt.', en: 'Measures whether a visit from an ad leads to an enquiry.', fr: 'Mesure si une visite issue d’une annonce aboutit à une demande.' } }],
    },
    tiktok: {
        label: 'TikTok Pixel', category: 'marketing', provider: company('TikTok Technology Limited', 'IE'), privacy: 'https://www.tiktok.com/legal/privacy-policy-eea',
        cookies: [{ name: '_ttp', duration: period(13, 'month'), description: { de: 'Erkennt Besucher für Werbung auf TikTok wieder.', en: 'Recognises visitors for advertising on TikTok.', fr: 'Reconnaît les visiteurs pour la publicité sur TikTok.' } }],
    },
    youtube: {
        label: 'YouTube', category: 'media', ...GOOGLE,
        cookies: [{ name: 'VISITOR_INFO1_LIVE, YSC', more: true, duration: sessionUpTo(6, 'month'), description: { de: 'Erst beim Abspielen, auf youtube.com/youtube-nocookie.com: Einstellungen und Statistik des Players.', en: 'Only on playback, on youtube.com/youtube-nocookie.com: player settings and statistics.', fr: 'Uniquement à la lecture, sur youtube.com/youtube-nocookie.com : réglages et statistiques du lecteur.' } }],
    },
    vimeo: {
        label: 'Vimeo', category: 'media', provider: company('Vimeo.com, Inc.', 'US'), privacy: 'https://vimeo.com/privacy',
        cookies: [{ name: 'vuid', duration: period(2, 'year'), description: { de: 'Erst beim Abspielen, auf vimeo.com: unterscheidet Zuschauer.', en: 'Only on playback, on vimeo.com: distinguishes viewers.', fr: 'Uniquement à la lecture, sur vimeo.com : distingue les spectateurs.' } }],
    },
    google_maps: {
        label: 'Google Maps', category: 'media', ...GOOGLE,
        cookies: [{ name: 'NID', more: true, duration: upTo(6, 'month'), description: { de: 'Erst beim Laden der Karte, auf google.com: Einstellungen und Sicherheit.', en: 'Only when the map loads, on google.com: settings and security.', fr: 'Uniquement au chargement de la carte, sur google.com : réglages et sécurité.' } }],
    },
    instagram: {
        label: 'Instagram', category: 'media', ...META,
        cookies: [{ name: 'ig_did, mid', more: true, duration: upTo(2, 'year'), description: { de: 'Erst beim Laden des Beitrags, auf instagram.com.', en: 'Only when the post loads, on instagram.com.', fr: 'Uniquement au chargement de la publication, sur instagram.com.' } }],
    },
    facebook: {
        label: 'Facebook', category: 'media', ...META,
        cookies: [{ name: 'datr, fr', more: true, duration: upTo(2, 'year'), description: { de: 'Erst beim Laden des Inhalts, auf facebook.com.', en: 'Only when the content loads, on facebook.com.', fr: 'Uniquement au chargement du contenu, sur facebook.com.' } }],
    },
    calendly: {
        label: 'Calendly', category: 'media', provider: company('Calendly LLC', 'US'), privacy: 'https://calendly.com/privacy',
        cookies: [{ name: '__cf_bm', more: true, duration: upTo(1, 'year'), description: { de: 'Erst beim Laden des Kalenders, auf calendly.com.', en: 'Only when the calendar loads, on calendly.com.', fr: 'Uniquement au chargement du calendrier, sur calendly.com.' } }],
    },
    openstreetmap: {
        label: 'OpenStreetMap', category: 'media', provider: company('OpenStreetMap Foundation', 'GB'), privacy: 'https://osmfoundation.org/wiki/Privacy_Policy',
        cookies: [{ name: '–', duration: '–', description: { de: 'Setzt in der Regel keine Cookies; beim Laden der Karte erfährt der Anbieter Ihre IP-Adresse.', en: 'Usually sets no cookies; when the map loads, the provider receives your IP address.', fr: 'Ne dépose généralement pas de cookies ; au chargement de la carte, le fournisseur reçoit votre adresse IP.' } }],
    },
    tripadvisor: {
        label: 'Tripadvisor', category: 'media', widget: true, provider: company('Tripadvisor LLC', 'US'), privacy: 'https://tripadvisor.mediaroom.com/de-privacy-policy',
        cookies: [
            { name: 'TAUnique, PMC', duration: period(2, 'year'), description: { de: 'Erst beim Laden der Bewertungen, auf tripadvisor.de/.com: erkennen den Browser wieder.', en: 'Only when the reviews load, on tripadvisor.de/.com: recognise the browser.', fr: 'Uniquement au chargement des avis, sur tripadvisor.de/.com : reconnaissent le navigateur.' } },
            { name: 'TADCID', duration: period(10, 'year'), description: { de: 'Auf tripadvisor.de/.com: Kennung des Browsers.', en: 'On tripadvisor.de/.com: browser identifier.', fr: 'Sur tripadvisor.de/.com : identifiant du navigateur.' } },
            { name: 'TASession, TASID, TATravelInfo, TAUD, TART', more: true, duration: sessionUpTo(6, 'month'), description: { de: 'Auf tripadvisor.de/.com: Sitzung, Sicherheit und Einstellungen.', en: 'On tripadvisor.de/.com: session, security and settings.', fr: 'Sur tripadvisor.de/.com : session, sécurité et réglages.' } },
        ],
    },
    holidaycheck: {
        label: 'HolidayCheck', category: 'media', widget: true, provider: company('HolidayCheck AG', 'CH'), privacy: 'https://www.holidaycheck.de/datenschutz',
        cookies: [
            { name: 'internal_traffic', duration: until(2038), description: { de: 'Erst beim Laden der Bewertungen, auf holidaycheck.de: kennzeichnet interne Zugriffe.', en: 'Only when the reviews load, on holidaycheck.de: marks internal traffic.', fr: 'Uniquement au chargement des avis, sur holidaycheck.de : signale le trafic interne.' } },
            { name: 'mobile_detected', duration: SESSION, description: { de: 'Auf holidaycheck.de: merkt sich, ob ein Mobilgerät erkannt wurde.', en: 'On holidaycheck.de: remembers whether a mobile device was detected.', fr: 'Sur holidaycheck.de : mémorise si un appareil mobile a été détecté.' } },
        ],
    },
    wellness_hotel: {
        label: 'wellness-hotel.info', category: 'media', widget: true, provider: company('thematica GmbH', 'AT'), privacy: 'https://wellness-hotel.info/datenschutzerkl%C3%A4rung',
        cookies: [{ name: '–', duration: '–', description: { de: 'Setzt beim Laden der Bewertungen keine Cookies; der Anbieter erfährt Ihre IP-Adresse.', en: 'Sets no cookies when the reviews load; the provider receives your IP address.', fr: 'Ne dépose pas de cookies au chargement des avis ; le fournisseur reçoit votre adresse IP.' } }],
    },
};

/** Notwendige Einträge, die der Banner selbst setzt („Immer laden“ in der Sprache der Seite). */
export function ownCookies(cookieDays, mediaKeys, label, loadAlways = '') {
    return [
        { name: 'wm_consent', duration: period(cookieDays, 'day'), description: { de: 'Speichert Ihre Auswahl zu diesem Banner.', en: 'Stores your choice in this banner.', fr: 'Enregistre votre choix dans ce bandeau.' }, provider: '', type: 'cookie' },
        ...mediaKeys.map((key) => ({
            name: `wm_consent_${key}`,
            duration: period(6, 'month'),
            description: { de: `Merkt sich „${loadAlways}“ für ${label(key)}.`, en: `Remembers “${loadAlways}” for ${label(key)}.`, fr: `Mémorise « ${loadAlways} » pour ${label(key)}.` },
            provider: '',
            type: 'cookie',
        })),
    ];
}
