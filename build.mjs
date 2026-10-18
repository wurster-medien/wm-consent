/**
 * Baut dist/wm-consent.js (ein Skript mit CookieConsent und iframemanager), dist/wm-consent.css und dist/cp.json (für
 * das Control Panel: Dienste mit Cookies auf Deutsch aus resources/js/services.js, Standardtexte aus texts.js). Das
 * Ergebnis wird committet, damit Statamic-Addon und MODX-Paket ohne Node auskommen.
 */
import { build } from 'esbuild';
import { readFileSync, writeFileSync } from 'node:fs';
import { SERVICES, CATEGORIES } from './resources/js/services.js';
import { DEFAULTS as texts } from './resources/js/texts.js';

const banner = '/*! wm-consent – Wurster Medien. Enthält vanilla-cookieconsent 3.1.0 und iframemanager 1.3.0 (beide MIT, Orest Bida). */';

await build({
    entryPoints: ['resources/js/wm-consent.js'],
    bundle: true,
    minify: true,
    format: 'iife',
    target: ['es2020'],
    outfile: 'dist/wm-consent.js',
    banner: { js: banner },
    legalComments: 'none',
});

const css = [
    readFileSync('node_modules/vanilla-cookieconsent/dist/cookieconsent.css', 'utf8'),
    readFileSync('node_modules/@orestbida/iframemanager/dist/iframemanager.css', 'utf8'),
    readFileSync('resources/css/wm-consent.css', 'utf8'),
].join('\n');

const minified = await build({ stdin: { contents: css, loader: 'css' }, minify: true, write: false, legalComments: 'none' });
writeFileSync('dist/wm-consent.css', banner + '\n' + minified.outputFiles[0].text);

/* Texte je Sprache ({ de, en, fr }) auf Deutsch, Werte ohne Sprachen bleiben. */
const de = (value) => (value && typeof value === 'object' ? value.de : value) ?? '';
const services = Object.fromEntries(Object.entries(SERVICES).map(([key, service]) => [key, {
    label: service.label,
    category: service.category,
    provider: de(service.provider),
    cookies: service.cookies.map((cookie) => ({ name: cookie.name, duration: de(cookie.duration), description: de(cookie.description), more: Boolean(cookie.more) })),
}]));
const categories = Object.fromEntries(Object.entries(CATEGORIES).map(([key, category]) => [key, de(category.title)]));
writeFileSync('dist/cp.json', JSON.stringify({ categories, services, texts }, null, 1) + '\n');

console.log('dist/wm-consent.js, dist/wm-consent.css und dist/cp.json gebaut.');
