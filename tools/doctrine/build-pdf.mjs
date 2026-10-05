/**
 * Imprime une doctrine HTML (gabarit docs/doctrine/print/doctrine-print.css) en PDF.
 *
 * Paged.js découpe le document en pages A4 (en-têtes, pagination « - n - »,
 * notes de bas de page, renvois du sommaire), puis Chromium imprime le résultat.
 *
 * Installation ponctuelle (hors package.json du site) :
 *   npm i --no-save pagedjs@0.4.3 playwright-core
 * Usage :
 *   node tools/doctrine/build-pdf.mjs docs/doctrine/fm-athena-ren-01/fm-athena-ren-01.html storage/documents/doctrine/ren-proc-2026-001.pdf
 * Navigateur : CHROME=/chemin/vers/chrome si Playwright ne trouve pas le sien.
 */
import { createRequire } from 'node:module';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright-core';

const [, , input, output] = process.argv;
if (!input || !output) {
    console.error('Usage : node tools/doctrine/build-pdf.mjs <source.html> <sortie.pdf>');
    process.exit(1);
}

const require = createRequire(import.meta.url);
// Le paquet n'exporte pas dist/ : on part de son point d'entrée.
const pagedPath = join(dirname(require.resolve('pagedjs')), '..', 'dist', 'paged.polyfill.js');

const candidates = [process.env.CHROME, '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'].filter(Boolean);
const executablePath = candidates.find((p) => existsSync(p));

const browser = await chromium.launch(executablePath ? { executablePath } : {});
const page = await browser.newPage();
await page.goto(pathToFileURL(resolve(input)).href, { waitUntil: 'load' });
await page.evaluate(() => document.fonts.ready);

// Paged.js relit les feuilles liées par fetch, refusé en file:// : on les insère en ligne.
const sheets = await page.evaluate(() => [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => l.href));
for (const href of sheets) {
    const css = readFileSync(new URL(href), 'utf8');
    await page.evaluate(([h, c]) => {
        const link = [...document.querySelectorAll('link[rel="stylesheet"]')].find((l) => l.href === h);
        const style = document.createElement('style');
        style.textContent = c;
        link.replaceWith(style);
    }, [href, css]);
}

await page.evaluate(() => {
    window.PagedConfig = { auto: false };
});
await page.addScriptTag({ path: pagedPath });
await page.evaluate(async () => {
    await window.PagedPolyfill.preview();
});

const pages = await page.evaluate(() => document.querySelectorAll('.pagedjs_page').length);
await page.pdf({ path: output, preferCSSPageSize: true, printBackground: true });
await browser.close();
console.log(`${output} : ${pages} pages`);
