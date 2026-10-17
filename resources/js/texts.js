/**
 * Texte des Banners (Deutsch, Sie). Jede Seite kann einzelne über `texts` in ihren Einstellungen ersetzen; Links auf
 * Datenschutz und Impressum kommen aus `privacyUrl` und `imprintUrl`.
 */
export function texts(overrides, config) {
    const link = (url, label) => (url ? `<a href="${escape(url)}">${label}</a>` : '');
    const footer = [link(config.privacyUrl, 'Datenschutz'), link(config.imprintUrl, 'Impressum')].filter(Boolean).join('\n');

    const defaults = {
        title: 'Cookies und externe Inhalte',
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
        more: config.privacyUrl ? `Einzelheiten stehen in unserer ${link(config.privacyUrl, 'Datenschutzerklärung')}.` : 'Einzelheiten stehen in unserer Datenschutzerklärung.',
        loadOnce: 'Einmal laden',
        loadAlways: 'Immer laden',
        reopen: 'Cookie-Einstellungen',
        providers: 'Anbieter',
        thisWebsite: 'Diese Website',
        footer,
    };

    return {
        ...defaults,
        ...overrides,
        placeholder: (service) => (overrides.placeholder || 'Dieser Inhalt kommt von {dienst}. Beim Laden erfährt {dienst} Ihre IP-Adresse.').replaceAll('{dienst}', service)
            + (config.privacyUrl ? ` ${link(config.privacyUrl, 'Datenschutz')}` : ''),
    };
}

function escape(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
