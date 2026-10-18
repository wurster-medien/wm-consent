/**
 * Texte des Banners auf Deutsch (Sie), Englisch und Französisch (vous); andere Sprachen bekommen Englisch. Jede Seite
 * kann einzelne über `texts` ersetzen: `texts['*']` für alle Sprachen, `texts.<sprache>` nur für eine (HeadTag.php
 * legt dabei die Texte der Website darüber). Links auf Datenschutz und Impressum kommen aus `privacyUrl` und
 * `imprintUrl`.
 */
export const LANGUAGES = ['de', 'en', 'fr'];

const DEFAULTS = {
    de: {
        title: 'Ihre Privatsphäre',
        description: 'Wir nutzen Cookies und Dienste anderer Anbieter, um die Website zu verbessern und Inhalte wie Videos oder Karten zu zeigen. Sie entscheiden, was Sie zulassen; Ihre Auswahl können Sie jederzeit unter „Cookie-Einstellungen“ ändern.',
        acceptAll: 'Alle akzeptieren',
        acceptNecessary: 'Nur notwendige',
        showPreferences: 'Einstellungen',
        save: 'Auswahl speichern',
        close: 'Schließen',
        preferencesTitle: 'Cookie-Einstellungen',
        preferencesIntroTitle: 'Ihre Auswahl',
        preferencesIntro: 'Hier legen Sie fest, welche Dienste wir nutzen dürfen. Notwendige Cookies brauchen wir für den Betrieb, alles andere nur mit Ihrer Zustimmung.',
        moreTitle: 'Mehr erfahren',
        more: 'Einzelheiten stehen in unserer {datenschutzerklaerung}.',
        privacyPolicy: 'Datenschutzerklärung',
        privacy: 'Datenschutz',
        imprint: 'Impressum',
        loadOnce: 'Einmal laden',
        loadAlways: 'Immer laden',
        reopen: 'Cookie-Einstellungen',
        thisWebsite: 'Diese Website',
        placeholder: 'Dieser Inhalt kommt von {dienst}. Beim Laden erfährt {dienst} Ihre IP-Adresse.',
        serviceCounter: 'Dienst|Dienste',
        andOthers: 'u. a.',
    },
    en: {
        title: 'Your privacy',
        description: 'We use cookies and services from other providers to improve this website and to show content such as videos or maps. You decide what you allow; you can change your choice at any time under “Cookie settings”.',
        acceptAll: 'Accept all',
        acceptNecessary: 'Necessary only',
        showPreferences: 'Settings',
        save: 'Save selection',
        close: 'Close',
        preferencesTitle: 'Cookie settings',
        preferencesIntroTitle: 'Your choice',
        preferencesIntro: 'Here you decide which services we may use. We need necessary cookies to run the website; everything else only with your consent.',
        moreTitle: 'Learn more',
        more: 'You will find details in our {datenschutzerklaerung}.',
        privacyPolicy: 'privacy policy',
        privacy: 'Privacy',
        imprint: 'Legal notice',
        loadOnce: 'Load once',
        loadAlways: 'Always load',
        reopen: 'Cookie settings',
        thisWebsite: 'This website',
        placeholder: 'This content is provided by {dienst}. When it loads, {dienst} receives your IP address.',
        serviceCounter: 'service|services',
        andOthers: 'etc.',
    },
    fr: {
        title: 'Votre vie privée',
        description: 'Nous utilisons des cookies et des services d’autres fournisseurs pour améliorer ce site et afficher des contenus comme des vidéos ou des cartes. Vous décidez de ce que vous autorisez ; vous pouvez modifier votre choix à tout moment sous « Paramètres des cookies ».',
        acceptAll: 'Tout accepter',
        acceptNecessary: 'Nécessaires uniquement',
        showPreferences: 'Paramètres',
        save: 'Enregistrer la sélection',
        close: 'Fermer',
        preferencesTitle: 'Paramètres des cookies',
        preferencesIntroTitle: 'Votre choix',
        preferencesIntro: 'Vous choisissez ici les services que nous pouvons utiliser. Les cookies nécessaires sont indispensables au fonctionnement du site ; tout le reste uniquement avec votre accord.',
        moreTitle: 'En savoir plus',
        more: 'Vous trouverez les détails dans notre {datenschutzerklaerung}.',
        privacyPolicy: 'politique de confidentialité',
        privacy: 'Confidentialité',
        imprint: 'Mentions légales',
        loadOnce: 'Charger une fois',
        loadAlways: 'Toujours charger',
        reopen: 'Paramètres des cookies',
        thisWebsite: 'Ce site',
        placeholder: 'Ce contenu est fourni par {dienst}. Lors du chargement, {dienst} reçoit votre adresse IP.',
        serviceCounter: 'service|services',
        andOthers: 'etc.',
    },
};

/**
 * Sprache der Seite: aus den Einstellungen (Site bzw. Kontext), sonst aus <html lang>, sonst Deutsch. Liefert das
 * Kürzel (für eigene Texte) und die Sprache der Standardtexte.
 */
export function language(config) {
    const code = (value) => (/^([a-z]{2})(?:[_-]|$)/i.exec(String(value || '').trim()) || [])[1]?.toLowerCase();
    const lang = code(config.lang) || code(document.documentElement.lang) || 'de';

    return { lang, base: LANGUAGES.includes(lang) ? lang : 'en' };
}

/** Text in der Sprache der Seite aus Text oder Map Sprache → Text (eigene Cookies, Vorlagen der Dienste). */
export function localize(value, { lang, base }) {
    if (value === null || typeof value !== 'object') {
        return value ?? '';
    }

    return value[lang] ?? value[base] ?? value.en ?? value.de ?? Object.values(value)[0] ?? '';
}

export function texts(overrides, config, { lang, base }) {
    const merged = { ...DEFAULTS[base], ...(overrides['*'] || {}), ...(overrides[lang] || {}) };
    const link = (url, label) => (url ? `<a href="${escape(url)}">${label}</a>` : '');
    const footer = [link(config.privacyUrl, merged.privacy), link(config.imprintUrl, merged.imprint)].filter(Boolean).join('\n');
    const policy = config.privacyUrl ? link(config.privacyUrl, merged.privacyPolicy) : merged.privacyPolicy;

    return {
        footer,
        ...merged,
        more: merged.more.replaceAll('{datenschutzerklaerung}', policy),
        placeholder: (service) => merged.placeholder.replaceAll('{dienst}', service).replaceAll('{service}', service)
            + (config.privacyUrl ? ` ${link(config.privacyUrl, merged.privacy)}` : ''),
    };
}

function escape(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
