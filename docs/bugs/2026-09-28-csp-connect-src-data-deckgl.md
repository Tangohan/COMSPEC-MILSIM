# Overwatch Beta — CSP bloque les icônes deck.gl (data:)

## Contexte

28 septembre 2026. Relief 3D / pastilles contacts (deck.gl).

## Symptôme

Console : `connect-src 'self' https: wss:` refuse `data:image/svg+xml…`. Pastilles / symboles incomplets.

## Cause

deck.gl charge des SVG en `data:` via `fetch()`. `img-src` autorisait déjà `data:`, pas `connect-src`. En prod, `APP_CSP` sans `data:` dans `connect-src`.

## Correctif

`SecurityHeadersMiddleware` : `data:` dans `connect-src` (défaut + `ensureConnectSrcData` si `APP_CSP` est défini).

## Fichiers touchés

- `app/Middleware/SecurityHeadersMiddleware.php`
- `tests/Unit/SecurityHeadersCspWorkerTest.php`

## Vérification

Ctrl+F5 Overwatch Beta Relief 3D : plus d’erreur CSP `data:image/svg+xml` ; pastilles visibles.

## Statut

corrigé (à déployer)
