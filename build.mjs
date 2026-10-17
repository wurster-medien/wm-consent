/**
 * Baut dist/wm-consent.js (ein Skript mit CookieConsent und iframemanager) und dist/wm-consent.css. Das Ergebnis wird
 * committet, damit Statamic-Addon und MODX-Paket ohne Node auskommen.
 */
import { build } from 'esbuild';
import { readFileSync, writeFileSync } from 'node:fs';

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

console.log('dist/wm-consent.js und dist/wm-consent.css gebaut.');
