/**
 * Cookie-Banner von Wurster Medien: Kern für Statamic und MODX.
 *
 * Liest die Einstellungen der Seite aus <script type="application/json" id="wm-consent-config">, die der Kopf-Tag
 * (HeadTag.php) ausgibt, und startet damit CookieConsent (vanilla-cookieconsent, MIT) und für Videos und Karten
 * iframemanager (MIT). Skripte, die erst nach Zustimmung laufen dürfen, stehen als type="text/plain" mit
 * data-category (und data-service) im HTML; das setzt der Ausgabefilter (OutputFilter.php) selbst.
 */
import * as CookieConsent from 'vanilla-cookieconsent';
import '@orestbida/iframemanager';
import { SERVICES, CATEGORIES, ownCookies } from './services.js';
import { texts, language, localize } from './texts.js';

const configElement = document.getElementById('wm-consent-config');
const config = configElement ? JSON.parse(configElement.textContent || '{}') : {};

/** Dienste dieser Seite: eingetragen plus vom Ausgabefilter gefunden. */
const serviceKeys = [...new Set([...(config.services || []), ...(config.detected || [])])].filter((key) => SERVICES[key]);
/** Sprache der Seite (lang: Kürzel für eigene Texte, base: Sprache der Standardtexte). */
const lang = language(config);
const t = texts(config.texts || {}, config, lang);

/** Eigene Einträge der Seite (Einstellung `cookies`, beim Statamic-Addon samt Session- und XSRF-Cookie). */
const siteCookies = Array.isArray(config.cookies) ? config.cookies : [];

applyTheme(config.theme || {}, config.mode || 'auto');

const iframes = window.iframemanager();
/** Videos, Karten und Beiträge mit Platzhalter (iframemanager), ohne Widgets wie Bewertungen. */
const mediaServices = serviceKeys.filter((key) => SERVICES[key].category === 'media' && !SERVICES[key].widget);

iframes.run({
    currLang: lang.lang,
    services: Object.fromEntries(mediaServices.map((key) => [key, {
        embedUrl: '{data-id}',
        iframe: { allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen; clipboard-write' },
        cookie: { name: `wm_consent_${key}` },
        languages: {
            [lang.lang]: {
                notice: t.placeholder(SERVICES[key].label),
                loadBtn: config.loadOnce ? t.loadOnce : '',
                loadAllBtn: t.loadAlways,
            },
        },
    }])),
    onChange: ({ changedServices, eventSource }) => {
        if (eventSource.type === 'click' && eventSource.action === 'accept') {
            changedServices.forEach((key) => CookieConsent.acceptService(key, 'media'));
        }
    },
});

alwaysFirst();

CookieConsent.run({
    revision: Number(config.revision || 0),
    cookie: { name: 'wm_consent', expiresAfterDays: Number(config.cookieDays || 182) },
    disablePageInteraction: config.blur !== false,
    guiOptions: {
        consentModal: { layout: 'box wide', position: config.position || 'bottom right', equalWeightButtons: true, flipButtons: false },
        preferencesModal: { layout: 'box', equalWeightButtons: true, flipButtons: false },
    },
    categories: categories(),
    language: { default: lang.lang, translations: { [lang.lang]: translation() } },
    onConsent: syncAll,
    onChange: syncAll,
    onModalReady: ({ modalName, modal }) => {
        if (modalName === 'preferencesModal') {
            togglesIntoCards(modal);
            partialCategories(modal);
        }
    },
    onModalShow: ({ modalName, modal }) => {
        if (modalName === 'preferencesModal' && modal) {
            partialCategories(modal);
        }
    },
});

document.addEventListener('click', (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-wm-consent-open], a[href="#cookie-einstellungen"]') : null;

    if (trigger) {
        event.preventDefault();
        CookieConsent.showPreferences();
    }
});

if (config.floatingButton) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'wm-consent-reopen';
    button.setAttribute('aria-label', t.reopen);
    button.setAttribute('data-wm-consent-open', '');
    button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5Zm-4.5 9a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm3 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm6-1a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z"/></svg>';
    document.body.appendChild(button);
}

window.wmConsent = {
    show: () => CookieConsent.showPreferences(),
    accepted: (category) => CookieConsent.acceptedCategory(category),
    inventory,
};

if (window.location.hash.includes('wm-consent-inventar')) {
    window.setTimeout(inventory, 2000);
}

/** Kategorien mit Diensten dieser Seite; „Notwendig“ immer, andere nur mit Diensten. */
function categories() {
    const result = { necessary: { enabled: true, readOnly: true } };

    for (const category of Object.keys(CATEGORIES)) {
        const keys = serviceKeys.filter((key) => SERVICES[key].category === category);

        if (category === 'necessary' || (keys.length === 0 && !siteCookies.some((cookie) => cookie.category === category))) {
            continue;
        }

        result[category] = {
            autoClear: {
                cookies: keys.flatMap((key) => (SERVICES[key].cookies || []).map((cookie) => cookie.name))
                    .filter((name) => /^[A-Za-z0-9_*-]+$/.test(name))
                    .map((name) => ({ name: toPattern(name) })),
            },
            services: Object.fromEntries(keys.map((key) => [key, { label: SERVICES[key].label }])),
        };
    }

    return result;
}

function translation() {
    const sections = [{ title: t.preferencesIntroTitle, description: t.preferencesIntro }];

    for (const [category, info] of Object.entries(CATEGORIES)) {
        const list = cookieList(category);

        if (category !== 'necessary' && list === '') {
            continue;
        }

        sections.push({ title: localize(info.title, lang), description: localize(info.description, lang) + list, linkedCategory: category });
    }

    sections.push({ title: t.moreTitle, description: t.more });

    return {
        consentModal: {
            title: t.title,
            description: t.description,
            acceptAllBtn: t.acceptAll,
            acceptNecessaryBtn: t.acceptNecessary,
            showPreferencesBtn: t.showPreferences,
            footer: t.footer,
        },
        preferencesModal: {
            title: t.preferencesTitle,
            acceptAllBtn: t.acceptAll,
            acceptNecessaryBtn: t.acceptNecessary,
            savePreferencesBtn: t.save,
            closeIconLabel: t.close,
            serviceCounterLabel: t.serviceCounter,
            sections,
        },
    };
}

/**
 * Zeilen der Cookie-Tabelle einer Kategorie: Vorlagen der Dienste dieser Seite, eigene Einträge, bei „Notwendig“
 * dazu die Cookies des Banners.
 */
function cookieList(category) {
    const own = category === 'necessary' ? ownCookies(Number(config.cookieDays || 182), mediaServices, (key) => SERVICES[key].label, t.loadAlways) : [];
    const groups = [];
    const websiteCookies = [...own, ...siteCookies.filter((cookie) => (cookie.category || 'necessary') === category && !cookie.provider)];

    if (websiteCookies.length > 0) {
        groups.push({ title: t.thisWebsite, provider: '', privacy: config.privacyUrl || '', cookies: websiteCookies });
    }

    for (const cookie of siteCookies.filter((cookie) => (cookie.category || 'necessary') === category && cookie.provider)) {
        const group = groups.find((entry) => entry.title === cookie.provider);
        if (group) {
            group.cookies.push(cookie);
            group.privacy ||= cookie.privacy || '';
        } else {
            groups.push({ title: cookie.provider, provider: '', privacy: cookie.privacy || '', cookies: [cookie] });
        }
    }

    for (const key of serviceKeys.filter((key) => SERVICES[key].category === category)) {
        const service = SERVICES[key];
        groups.push({ key, title: service.label, provider: service.provider || '', privacy: service.privacy || '', cookies: service.cookies || [] });
    }

    return groups.length === 0 ? '' : `<span class="wm-cookies">${groups.map(cookieGroup).join('')}</span>`;
}

/**
 * Ein Dienst bzw. „Diese Website“ in der Cookie-Liste: Name, Anbieter und Datenschutz in einer Zeile, darunter je
 * Eintrag Name mit Dauer (und Art, wenn nicht Cookie) als Marke und der Zweck als Satz. Nur span-Elemente, weil
 * CookieConsent die Beschreibung in ein <p> setzt.
 */
function cookieGroup(group) {
    const storage = { local: 'Local Storage', session: 'Session Storage' };
    const privacy = group.privacy ? `<a href="${escapeHtml(group.privacy)}" target="_blank" rel="noopener">${escapeHtml(t.privacy)}</a>` : '';
    const meta = [group.provider ? escapeHtml(localize(group.provider, lang)) : '', privacy].filter(Boolean).join(' · ');
    const rows = group.cookies.map((cookie) => {
        const badges = [localize(cookie.duration, lang), storage[cookie.type]].filter((value) => value && value !== '–')
            .map((value) => `<span class="wm-cookie-badge">${escapeHtml(value)}</span>`).join('');
        const name = cookie.name && cookie.name !== '–' ? `<span class="wm-cookie-name">${escapeHtml(cookie.name + (cookie.more ? ` ${t.andOthers}` : ''))}</span>` : '';

        return `<span class="wm-cookie">${name || badges ? `<span class="wm-cookie-head">${name}${badges}</span>` : ''}`
            + `<span class="wm-cookie-desc">${escapeHtml(localize(cookie.description, lang))}</span></span>`;
    }).join('');

    return `<span class="wm-cookie-group"${group.key ? ` data-wm-service="${escapeHtml(group.key)}"` : ''}><span class="wm-cookie-service"><span class="wm-cookie-title">${escapeHtml(group.title)}</span>`
        + `${meta ? `<span class="wm-cookie-meta">${meta}</span>` : ''}</span>${rows}</span>`;
}

/**
 * Schalter der Dienste (CookieConsent baut sie als eigene Liste über der Beschreibung) in den Kopf der passenden
 * Karte der Cookie-Liste: lesen und entscheiden an einer Stelle. Die Elemente selbst wandern, damit Logik, Kopplung
 * an den Schalter der Kategorie und Tastatur bleiben. Bei nur einem Dienst bleibt es beim Schalter der Kategorie.
 */
function togglesIntoCards(modal) {
    modal.querySelectorAll('.pm__section-services').forEach((list) => {
        const toggles = [...list.querySelectorAll('.toggle-service')];
        const wrapper = list.parentElement;

        if (toggles.length < 2 || !wrapper) {
            return;
        }

        for (const toggle of toggles) {
            const input = toggle.querySelector('input');
            const card = input && wrapper.querySelector(`.wm-cookie-group[data-wm-service="${CSS.escape(input.value)}"] .wm-cookie-service`);

            if (card) {
                card.appendChild(toggle);
            }
        }

        if (!list.querySelector('.toggle-service')) {
            list.hidden = true;
        }
    });
}

/**
 * Kategorie, in der nur einige Dienste an sind: Schalter halb (indeterminate, Knopf in der Mitte mit Strich). Ein
 * Klick darauf schaltet dann alle Dienste an statt aus. Neu berechnet nach jeder Änderung im Dialog und beim Öffnen,
 * weil CookieConsent die Schalter beim Öffnen und Speichern ohne Ereignis setzt.
 */
function partialCategories(modal) {
    if (!modal.dataset.wmPartial) {
        modal.dataset.wmPartial = '1';
        modal.addEventListener('change', () => partialCategories(modal));
        /* Vor dem Klick-Handler von CookieConsent (Erfassungsphase): der Browser hat schon auf „aus“ umgeschaltet. */
        modal.addEventListener('click', (event) => {
            const input = event.target;

            if (input instanceof HTMLInputElement && input.dataset.wmPartial === '1') {
                input.checked = true;
            }
        }, true);
    }

    modal.querySelectorAll('.pm__section-title-wrapper .section__toggle').forEach((input) => {
        const section = input.closest('.pm__section--toggle');
        const services = section ? [...section.querySelectorAll('.toggle-service input')] : [];
        const on = services.filter((service) => service.checked).length;
        const partial = on > 0 && on < services.length;

        input.indeterminate = partial;
        input.dataset.wmPartial = partial ? '1' : '';
    });
}

/**
 * Inventar für uns beim Einrichten: mit #wm-consent-inventar an der Adresse stehen in der Konsole alle Cookies und
 * Einträge im Local/Session Storage dieser Seite, die noch nirgends beschrieben sind, samt Schnipsel für `cookies`.
 */
function inventory() {
    const described = [
        ...ownCookies(0, mediaServices, () => '').map((cookie) => cookie.name),
        ...siteCookies.map((cookie) => cookie.name),
        ...serviceKeys.flatMap((key) => (SERVICES[key].cookies || []).map((cookie) => cookie.name)),
        'cc_cookie',
    ];
    const known = (name) => described.some((pattern) => (pattern.endsWith('*') ? name.startsWith(pattern.slice(0, -1)) : pattern === name));
    const found = [
        ...document.cookie.split(';').map((part) => part.split('=')[0].trim()).filter(Boolean).map((name) => ({ name, type: 'cookie' })),
        ...Object.keys(window.localStorage || {}).map((name) => ({ name, type: 'local' })),
        ...Object.keys(window.sessionStorage || {}).map((name) => ({ name, type: 'session' })),
    ].filter((entry) => !known(entry.name));

    console.info(`[wm-consent] ${found.length} nicht beschriebene Einträge (HttpOnly-Cookies wie die Sitzung sieht das Skript nicht).`);

    if (found.length > 0) {
        console.table(found);
        console.info('[wm-consent] Für die Einstellungen (cookies), Beschreibung und Dauer ergänzen:\n' + JSON.stringify(found.map((entry) => ({ ...entry, category: 'necessary', duration: '', description: '' })), null, 4));
    }
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

/** Nach jeder Entscheidung: Google Consent Mode und Platzhalter für Videos und Karten nachziehen. */
function syncAll() {
    if (typeof window.gtag === 'function') {
        const analytics = CookieConsent.acceptedCategory('analytics');
        const marketing = CookieConsent.acceptedCategory('marketing');
        window.gtag('consent', 'update', {
            analytics_storage: analytics ? 'granted' : 'denied',
            ad_storage: marketing ? 'granted' : 'denied',
            ad_user_data: marketing ? 'granted' : 'denied',
            ad_personalization: marketing ? 'granted' : 'denied',
        });
    }

    for (const key of mediaServices) {
        if (CookieConsent.acceptedService(key, 'media')) {
            iframes.acceptService(key);
        } else {
            iframes.rejectService(key);
        }
    }

    alwaysFirst();
}

/** In den Platzhaltern „Immer laden“ zuerst (auch für die Tastatur), „Einmal laden“ (load_once) danach. */
function alwaysFirst() {
    document.querySelectorAll('div[data-service] .c-n-a').forEach((box) => {
        const always = box.querySelector('.c-la-b');

        if (always && box.firstElementChild !== always) {
            box.prepend(always);
        }
    });
}

/**
 * Farben der Seite als Variablen von CookieConsent und iframemanager: je Modus Hintergrund, Text und Akzent, der Rest
 * daraus gemischt. „auto“ folgt dem System, „light“/„dark“ fest.
 */
function applyTheme(theme, mode) {
    const style = document.createElement('style');
    const body = window.getComputedStyle(document.body || document.documentElement);
    const fontSize = Math.max(16, Math.min(18, parseFloat(body.fontSize) || 16));
    const headingElement = document.querySelector('h1, h2, h3');
    const headingFont = headingElement ? window.getComputedStyle(headingElement).fontFamily : '';
    const overlay = Math.max(0, Math.min(90, Number(config.overlayOpacity ?? 50)));
    style.setAttribute('data-wm-consent-theme', '');
    style.textContent = [
        `:root{--wm-consent-font:${body.fontFamily || 'inherit'};--wm-consent-font-size:${fontSize}px;--im-font-family:var(--wm-consent-font);--wm-consent-heading-font:${headingFont || 'inherit'};--wm-consent-blur:${Math.max(0, Math.min(30, Number(config.blurStrength ?? 8)))}px}`,
        `:root{${variables(theme.light || {}, false, overlay)}}`,
        `:root.cc--darkmode{${variables(theme.dark || {}, true, overlay)}}`,
    ].join('');
    document.head.appendChild(style);

    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const update = () => root.classList.toggle('cc--darkmode', mode === 'dark' || (mode === 'auto' && media.matches));
    update();

    if (mode === 'auto') {
        media.addEventListener('change', update);
    }
}

/**
 * Nur Hintergrund, Text und Akzent sind Farben; Nebenflächen, Nebentext, Linien und Schalter sind der Text mit
 * Transparenz, die Fläche über der Seite die dunklere der beiden Farben mit `overlay` Prozent Deckkraft.
 */
function variables(colors, dark, overlay) {
    const bg = colors.background || (dark ? '#161a1c' : '#ffffff');
    const text = colors.text || (dark ? '#ebf3f6' : '#2c2f31');
    const accent = colors.highlight || '#14b8a6';
    const onAccent = readableOn(accent);
    const tint = (amount) => `color-mix(in srgb, ${text} ${amount}%, transparent)`;
    const muted = tint(8);
    const mutedHover = tint(15);
    const mutedText = tint(75);
    const border = tint(12);

    return [
        `--cc-bg:${bg}`,
        `--cc-primary-color:${text}`,
        `--cc-secondary-color:${mutedText}`,
        `--cc-btn-primary-bg:${accent}`,
        `--cc-btn-primary-color:${onAccent}`,
        `--cc-btn-primary-border-color:${accent}`,
        `--cc-btn-primary-hover-bg:color-mix(in srgb, ${accent} 85%, ${text})`,
        `--cc-btn-primary-hover-color:${onAccent}`,
        `--cc-btn-primary-hover-border-color:color-mix(in srgb, ${accent} 85%, ${text})`,
        `--cc-btn-secondary-bg:${muted}`,
        `--cc-btn-secondary-color:${text}`,
        `--cc-btn-secondary-border-color:${muted}`,
        `--cc-btn-secondary-hover-bg:${mutedHover}`,
        `--cc-btn-secondary-hover-color:${text}`,
        `--cc-btn-secondary-hover-border-color:${mutedHover}`,
        `--cc-separator-border-color:${border}`,
        `--cc-toggle-on-bg:${accent}`,
        `--cc-toggle-off-bg:${tint(35)}`,
        `--cc-toggle-on-knob-bg:${bg}`,
        `--cc-toggle-off-knob-bg:${bg}`,
        `--cc-toggle-readonly-bg:${tint(20)}`,
        `--cc-toggle-readonly-knob-bg:${bg}`,
        `--cc-section-category-border:${border}`,
        `--cc-cookie-category-block-bg:${muted}`,
        `--cc-cookie-category-block-border:${border}`,
        `--cc-cookie-category-block-hover-bg:${mutedHover}`,
        `--cc-cookie-category-block-hover-border:${border}`,
        `--cc-cookie-category-expanded-block-bg:${muted}`,
        `--cc-cookie-category-expanded-block-hover-bg:${mutedHover}`,
        `--cc-footer-bg:${bg}`,
        `--cc-footer-color:${mutedText}`,
        `--cc-footer-border-color:${border}`,
        `--cc-link-color:${text}`,
        `--cc-overlay-bg:color-mix(in srgb, ${dark ? bg : text} ${overlay}%, transparent)`,
        /* Platzhalter liegen auf der Seite, nicht auf dem Banner: Fläche deckend. */
        `--im-bg:color-mix(in srgb, ${text} 8%, ${bg})`,
        `--im-color:${text}`,
        `--im-link-color:${text}`,
        `--im-link-hover-color:${mutedText}`,
        `--im-btn-bg:${accent}`,
        `--im-btn-color:${onAccent}`,
        `--im-btn-hover-bg:color-mix(in srgb, ${accent} 85%, ${text})`,
        `--im-btn-s-bg:${mutedHover}`,
        `--im-btn-s-color:${text}`,
        `--im-btn-s-hover-bg:${border}`,
        `--im-overlay-bg:transparent`,
        `--wm-consent-bg:${bg}`,
        `--wm-consent-text:${text}`,
        `--wm-consent-accent:${accent}`,
        `--wm-consent-on-accent:${onAccent}`,
        `--wm-consent-muted:${muted}`,
    ].join(';');
}

/** Weiß oder Fast-Schwarz, je nachdem, was auf der Akzentfarbe besser lesbar ist (nur #rgb/#rrggbb). */
function readableOn(color) {
    const hex = String(color).replace('#', '');
    const full = hex.length === 3 ? hex.split('').map((c) => c + c).join('') : hex;

    if (!/^[0-9a-f]{6}$/i.test(full)) {
        return '#ffffff';
    }

    const [r, g, b] = [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16) / 255)
        .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

    return luminance > 0.4 ? '#1d2022' : '#ffffff';
}

function toPattern(name) {
    return name.endsWith('*') ? new RegExp('^' + name.slice(0, -1).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) : name;
}
