# Overwatch Beta — Relief 3D vide (worker MapLibre bloqué)

## Contexte

Overwatch Beta, `Vue de la carte` = Relief 3D (athena.ttrd.fr). MapLibre 4.7.1 vendored.

## Symptôme

Après le basculement Relief 3D, la carte reste un écran vert sombre. Barre Nord / Unité / Sol visible, pas de sol ni de bâtiments. Console :

> Creating a worker from 'blob:https://athena.ttrd.fr/…' violates the following Content Security Policy directive: "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com". Note that 'worker-src' was not explicitly set, so 'script-src' is used as a fallback.

Pile : `maplibre-gl.js` → `OverwatchGlMap.js` `startGl` (constructeur `maplibregl.Map` / `setStyle`).

## Cause

MapLibre (et deck.gl) créent des Web Workers depuis une URL `blob:`. La CSP du portail n’avait pas `worker-src` : le navigateur retombe sur `script-src`, qui n’autorise pas `blob:`. En production, `APP_CSP` est défini et ne contenait pas non plus cette directive.

## Correctif

`SecurityHeadersMiddleware` pose `worker-src 'self' blob:` sur la CSP par défaut, et l’ajoute (ou complète) si `APP_CSP` est défini sans `blob:` — sauf `worker-src 'none'`. `script-src` n’est pas élargi.

## Fichiers touchés

- `app/Middleware/SecurityHeadersMiddleware.php`
- `tests/Unit/SecurityHeadersCspWorkerTest.php`
- `.env.example`
- `docs/technique/overwatch-vue-3d-maplibre.md`

## Vérification

Tests unitaires `ensureWorkerSrc` (défaut, `APP_CSP` sans worker, `worker-src 'self'`, `'none'`). Après déploiement : Overwatch Beta, Relief 3D, Ctrl+F5 — le sol et les bâtiments du relevé s’affichent, plus d’erreur worker dans la console.

## Statut

corrigé
