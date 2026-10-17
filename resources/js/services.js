/**
 * Dienste und Kategorien. Die Schlüssel sind dieselben wie in src/Core/Services.php (dort die Muster, an denen der
 * Ausgabefilter sie erkennt); neue Dienste an beiden Stellen eintragen.
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

export const SERVICES = {
    google_analytics: { label: 'Google Analytics', category: 'analytics', cookies: ['_ga', '_ga_*', '_gid', '_gat*'] },
    google_tag_manager: { label: 'Google Tag Manager', category: 'analytics', cookies: [] },
    matomo: { label: 'Matomo', category: 'analytics', cookies: ['_pk_*', 'MATOMO_SESSID'] },
    hotjar: { label: 'Hotjar', category: 'analytics', cookies: ['_hj*'] },
    clarity: { label: 'Microsoft Clarity', category: 'analytics', cookies: ['_clck', '_clsk'] },
    meta_pixel: { label: 'Meta Pixel', category: 'marketing', cookies: ['_fbp', '_fbc'] },
    linkedin: { label: 'LinkedIn Insight', category: 'marketing', cookies: ['li_*', '_gcl_*'] },
    google_ads: { label: 'Google Ads', category: 'marketing', cookies: ['_gcl_*'] },
    tiktok: { label: 'TikTok Pixel', category: 'marketing', cookies: ['_ttp'] },
    youtube: { label: 'YouTube', category: 'media' },
    vimeo: { label: 'Vimeo', category: 'media' },
    google_maps: { label: 'Google Maps', category: 'media' },
    instagram: { label: 'Instagram', category: 'media' },
    facebook: { label: 'Facebook', category: 'media' },
    calendly: { label: 'Calendly', category: 'media' },
    openstreetmap: { label: 'OpenStreetMap', category: 'media' },
};
