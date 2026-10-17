/**
 * Dienste und Kategorien mit Vorlagen für die Cookie-Liste (Anbieter, Datenschutz, einzelne Cookies mit Laufzeit,
 * laut Anbieter, Stand 10/2026). Der Banner zeigt sie von selbst für jeden Dienst, der auf der Seite vorkommt.
 * Die Schlüssel sind dieselben wie in src/Core/Services.php (dort die Muster, an denen der Ausgabefilter sie erkennt);
 * neue Dienste an beiden Stellen eintragen. `name` mit * am Ende ist ein Präfix (z. B. _ga_* für _ga_ABC123).
 */
export const CATEGORIES = {
    necessary: {
        title: 'Notwendig',
        description: 'Für den Betrieb der Website nötig, z. B. um Ihre Auswahl zu diesem Banner zu speichern. Lassen sich nicht abschalten.',
    },
    analytics: {
        title: 'Statistik',
        description: 'Helfen uns zu verstehen, wie Besucher die Website nutzen, damit wir sie verbessern können.',
    },
    marketing: {
        title: 'Marketing',
        description: 'Messen den Erfolg von Werbung und zeigen Ihnen auf anderen Plattformen passende Anzeigen.',
    },
    media: {
        title: 'Externe Medien',
        description: 'Videos, Karten und Beiträge von anderen Anbietern. Erst beim Laden erfährt der Anbieter Ihre IP-Adresse.',
    },
};

const GOOGLE = { provider: 'Google Ireland Limited, Irland', privacy: 'https://policies.google.com/privacy' };
const META = { provider: 'Meta Platforms Ireland Ltd., Irland', privacy: 'https://www.facebook.com/privacy/policy/' };

export const SERVICES = {
    google_analytics: {
        label: 'Google Analytics', category: 'analytics', ...GOOGLE,
        cookies: [
            { name: '_ga', duration: '2 Jahre', description: 'Unterscheidet Besucher.' },
            { name: '_ga_*', duration: '2 Jahre', description: 'Hält den Stand der Sitzung fest.' },
        ],
    },
    google_tag_manager: {
        label: 'Google Tag Manager', category: 'analytics', ...GOOGLE,
        cookies: [{ name: '–', duration: '–', description: 'Setzt selbst keine Cookies, lädt aber die darin eingerichteten Dienste.' }],
    },
    matomo: {
        label: 'Matomo', category: 'analytics', provider: 'Betreiber dieser Website (eigener Server)', privacy: '',
        cookies: [
            { name: '_pk_id*', duration: '13 Monate', description: 'Unterscheidet Besucher.' },
            { name: '_pk_ses*', duration: '30 Minuten', description: 'Hält die Sitzung fest.' },
        ],
    },
    hotjar: {
        label: 'Hotjar', category: 'analytics', provider: 'Hotjar Ltd., Malta', privacy: 'https://www.hotjar.com/legal/policies/privacy/',
        cookies: [
            { name: '_hjSessionUser_*', duration: '1 Jahr', description: 'Unterscheidet Besucher.' },
            { name: '_hjSession_*', duration: '30 Minuten', description: 'Hält die Sitzung fest.' },
        ],
    },
    clarity: {
        label: 'Microsoft Clarity', category: 'analytics', provider: 'Microsoft Ireland Operations Ltd., Irland', privacy: 'https://privacy.microsoft.com/de-de/privacystatement',
        cookies: [
            { name: '_clck', duration: '1 Jahr', description: 'Unterscheidet Besucher.' },
            { name: '_clsk', duration: '1 Tag', description: 'Fasst Seitenaufrufe zu einer Sitzung zusammen.' },
        ],
    },
    meta_pixel: {
        label: 'Meta Pixel', category: 'marketing', ...META,
        cookies: [
            { name: '_fbp', duration: '3 Monate', description: 'Erkennt Besucher für Werbung auf Facebook und Instagram wieder.' },
            { name: '_fbc', duration: '3 Monate', description: 'Merkt sich den Klick auf eine Anzeige.' },
        ],
    },
    linkedin: {
        label: 'LinkedIn Insight', category: 'marketing', provider: 'LinkedIn Ireland Unlimited Company, Irland', privacy: 'https://de.linkedin.com/legal/privacy-policy',
        cookies: [
            { name: 'li_sugr', duration: '3 Monate', description: 'Ordnet Besuche einem LinkedIn-Mitglied zu.' },
            { name: 'bcookie', duration: '1 Jahr', description: 'Erkennt den Browser wieder (linkedin.com).' },
            { name: 'lidc', duration: '1 Tag', description: 'Wählt das Rechenzentrum (linkedin.com).' },
            { name: 'UserMatchHistory', duration: '30 Tage', description: 'Abgleich für Werbung (linkedin.com).' },
        ],
    },
    google_ads: {
        label: 'Google Ads', category: 'marketing', ...GOOGLE,
        cookies: [{ name: '_gcl_au', duration: '3 Monate', description: 'Misst, ob ein Besuch aus einer Anzeige zu einer Anfrage führt.' }],
    },
    tiktok: {
        label: 'TikTok Pixel', category: 'marketing', provider: 'TikTok Technology Limited, Irland', privacy: 'https://www.tiktok.com/legal/privacy-policy-eea',
        cookies: [{ name: '_ttp', duration: '13 Monate', description: 'Erkennt Besucher für Werbung auf TikTok wieder.' }],
    },
    youtube: {
        label: 'YouTube', category: 'media', ...GOOGLE,
        cookies: [{ name: 'VISITOR_INFO1_LIVE, YSC u. a.', duration: 'Sitzung bis 6 Monate', description: 'Erst beim Abspielen, auf youtube.com/youtube-nocookie.com: Einstellungen und Statistik des Players.' }],
    },
    vimeo: {
        label: 'Vimeo', category: 'media', provider: 'Vimeo.com, Inc., USA', privacy: 'https://vimeo.com/privacy',
        cookies: [{ name: 'vuid', duration: '2 Jahre', description: 'Erst beim Abspielen, auf vimeo.com: unterscheidet Zuschauer.' }],
    },
    google_maps: {
        label: 'Google Maps', category: 'media', ...GOOGLE,
        cookies: [{ name: 'NID u. a.', duration: 'bis 6 Monate', description: 'Erst beim Laden der Karte, auf google.com: Einstellungen und Sicherheit.' }],
    },
    instagram: {
        label: 'Instagram', category: 'media', ...META,
        cookies: [{ name: 'ig_did, mid u. a.', duration: 'bis 2 Jahre', description: 'Erst beim Laden des Beitrags, auf instagram.com.' }],
    },
    facebook: {
        label: 'Facebook', category: 'media', ...META,
        cookies: [{ name: 'datr, fr u. a.', duration: 'bis 2 Jahre', description: 'Erst beim Laden des Inhalts, auf facebook.com.' }],
    },
    calendly: {
        label: 'Calendly', category: 'media', provider: 'Calendly LLC, USA', privacy: 'https://calendly.com/privacy',
        cookies: [{ name: '__cf_bm u. a.', duration: 'bis 1 Jahr', description: 'Erst beim Laden des Kalenders, auf calendly.com.' }],
    },
    openstreetmap: {
        label: 'OpenStreetMap', category: 'media', provider: 'OpenStreetMap Foundation, Vereinigtes Königreich', privacy: 'https://osmfoundation.org/wiki/Privacy_Policy',
        cookies: [{ name: '–', duration: '–', description: 'Setzt in der Regel keine Cookies; beim Laden der Karte erfährt der Anbieter Ihre IP-Adresse.' }],
    },
};

/** Notwendige Einträge, die der Banner selbst setzt. */
export function ownCookies(cookieDays, mediaKeys, label) {
    return [
        { name: 'wm_consent', duration: `${cookieDays} Tage`, description: 'Speichert Ihre Auswahl zu diesem Banner.', provider: '', type: 'cookie' },
        ...mediaKeys.map((key) => ({ name: `wm_consent_${key}`, duration: '6 Monate', description: `Merkt sich „Immer laden“ für ${label(key)}.`, provider: '', type: 'cookie' })),
    ];
}
