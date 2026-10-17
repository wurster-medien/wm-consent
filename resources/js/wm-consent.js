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
import { texts } from './texts.js';

const configElement = document.getElementById('wm-consent-config');
const config = configElement ? JSON.parse(configElement.textContent || '{}') : {};

/** Dienste dieser Seite: eingetragen plus vom Ausgabefilter gefunden. */
const serviceKeys = [...new Set([...(config.services || []), ...(config.detected || [])])].filter((key) => SERVICES[key]);
const t = texts(config.texts || {}, config);

/** Eigene Einträge der Seite (Einstellung `cookies`, beim Statamic-Addon samt Session- und XSRF-Cookie). */
const siteCookies = Array.isArray(config.cookies) ? config.cookies : [];

applyTheme(config.theme || {}, config.mode || 'auto');

const iframes = window.iframemanager();
const mediaServices = serviceKeys.filter((key) => SERVICES[key].category === 'media');

iframes.run({
    currLang: 'de',
    services: Object.fromEntries(mediaServices.map((key) => [key, {
        embedUrl: '{data-id}',
        iframe: { allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen; clipboard-write' },
        cookie: { name: `wm_consent_${key}` },
        languages: {
            de: {
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
    language: { default: 'de', translations: { de: translation() } },
    onConsent: syncAll,
    onChange: syncAll,
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

        sections.push({ title: info.title, description: info.description + list, linkedCategory: category });
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
            serviceCounterLabel: 'Dienst|Dienste',
            sections,
        },
    };
}

/**
 * Zeilen der Cookie-Tabelle einer Kategorie: Vorlagen der Dienste dieser Seite, eigene Einträge, bei „Notwendig“
 * dazu die Cookies des Banners.
 */
function cookieList(category) {
    const own = category === 'necessary' ? ownCookies(Number(config.cookieDays || 182), mediaServices, (key) => SERVICES[key].label) : [];
    const groups = [];
    const websiteCookies = [...own, ...siteCookies.filter((cookie) => (cookie.category || 'necessary') === category && !cookie.provider)];

    if (websiteCookies.length > 0) {
        groups.push({ title: t.thisWebsite, provider: '', privacy: config.privacyUrl || '', cookies: websiteCookies });
    }

    for (const cookie of siteCookies.filter((cookie) => (cookie.category || 'necessary') === category && cookie.provider)) {
        const group = groups.find((entry) => entry.title === cookie.provider);
        group ? group.cookies.push(cookie) : groups.push({ title: cookie.provider, provider: '', privacy: '', cookies: [cookie] });
    }

    for (const key of serviceKeys.filter((key) => SERVICES[key].category === category)) {
        const service = SERVICES[key];
        groups.push({ title: service.label, provider: service.provider || '', privacy: service.privacy || '', cookies: service.cookies || [] });
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
    const privacy = group.privacy ? `<a href="${escapeHtml(group.privacy)}" target="_blank" rel="noopener">Datenschutz</a>` : '';
    const meta = [group.provider ? escapeHtml(group.provider) : '', privacy].filter(Boolean).join(' · ');
    const rows = group.cookies.map((cookie) => {
        const badges = [cookie.duration, storage[cookie.type]].filter((value) => value && value !== '–')
            .map((value) => `<span class="wm-cookie-badge">${escapeHtml(value)}</span>`).join('');
        const name = cookie.name && cookie.name !== '–' ? `<span class="wm-cookie-name">${escapeHtml(cookie.name)}</span>` : '';

        return `<span class="wm-cookie">${name || badges ? `<span class="wm-cookie-head">${name}${badges}</span>` : ''}`
            + `<span class="wm-cookie-desc">${escapeHtml(cookie.description || '')}</span></span>`;
    }).join('');

    return `<span class="wm-cookie-group"><span class="wm-cookie-service"><span class="wm-cookie-title">${escapeHtml(group.title)}</span>`
        + `${meta ? `<span class="wm-cookie-meta">${meta}</span>` : ''}</span>${rows}</span>`;
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
    style.setAttribute('data-wm-consent-theme', '');
    style.textContent = [
        `:root{--wm-consent-font:${body.fontFamily || 'inherit'};--wm-consent-font-size:${fontSize}px;--im-font-family:var(--wm-consent-font);--wm-consent-heading-font:${headingFont || 'inherit'};--wm-consent-blur:${Math.max(0, Math.min(30, Number(config.blurStrength ?? 8)))}px}`,
        `:root{${variables(theme.light || {}, false)}}`,
        `:root.cc--darkmode{${variables(theme.dark || {}, true)}}`,
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

function variables(colors, dark) {
    const bg = colors.background || (dark ? '#161a1c' : '#ffffff');
    const text = colors.text || (dark ? '#ebf3f6' : '#2c2f31');
    const accent = colors.highlight || '#14b8a6';
    const onAccent = colors.highlightText || readableOn(accent);
    const mix = (amount, base = bg, other = text) => `color-mix(in srgb, ${other} ${amount}%, ${base})`;
    /* Grautöne der Seite, wenn angegeben (muted: Nebenflächen, mutedText: Nebentext, border: Linien), sonst gemischt. */
    const muted = colors.muted || mix(8);
    const mutedHover = colors.muted ? `color-mix(in srgb, ${text} 8%, ${colors.muted})` : mix(15);
    const mutedText = colors.mutedText || mix(75);
    const border = colors.border || mix(12);

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
        `--cc-toggle-off-bg:${colors.mutedText || mix(35)}`,
        `--cc-toggle-on-knob-bg:${bg}`,
        `--cc-toggle-off-knob-bg:${bg}`,
        `--cc-toggle-readonly-bg:${colors.muted ? mutedHover : mix(20)}`,
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
        `--cc-overlay-bg:${colors.overlay || (dark ? 'rgba(0,0,0,.45)' : 'rgba(0,0,0,.3)')}`,
        `--im-bg:${muted}`,
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
        `--wm-consent-heading:${colors.heading || text}`,
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
