# ATAK — 404 `sse-case-overlay` + 503 polls

## Contexte

Console navigateur sur `athena.ttrd.fr/public/atak` (2026-08-16) : boucle d’erreurs réseau.

## Symptôme

1. `atak/sse-case-overlay?mapId=1` → **404** (répété).
2. Nombreux `api/atak/*` (stats, orders, ping, markers…) → **503**.
3. Tuiles `jetelain.github.io/.../2/-1/*.png` → 404 (hors emprise carte).

## Cause

1. **404 overlay** : `atak-sse-layers.js` appelait `ATAK_API_BASE + '/atak/sse-case-overlay'` alors que la route est `/api/atak/sse-case-overlay` et que `ATAK_API_BASE` = préfixe public (`/public`), pas `/public/api`. Résultat : requête vers `/public/atak/sse-case-overlay` (inexistante). Les autres modules utilisent déjà `/api/atak/...`.
2. **503 API** : échec BDD / boot PDO (voir `2026-08-16-atak-perstat-pdo-2002.md`) — correctifs lazy + retry **en code local, pas forcément déployés** sur Hostinger. `ExceptionHandler` renvoie 503 `database_unavailable`.
3. **Tuiles** : zoom/pan hors grille Altis — normal, sans impact fonctionnel.

## Correctif

- `public/assets/js/atak-sse-layers.js` : préfixe `/api/atak/sse-case-overlay`.
- 503 : déployer le correctif PDO lazy + retry déjà documenté.

## Fichiers touchés

- `public/assets/js/atak-sse-layers.js`
- cette note

## Vérification

1. Recharger ATAK (cache). Network : `GET …/public/api/atak/sse-case-overlay?mapId=1` → 200 (ou 401 si déco), plus de 404 sur `/public/atak/sse-case-overlay`.
2. Après déploiement PDO : les polls `api/atak/*` ne doivent plus rester en 503 en continu.

## Statut

404 overlay : corrigé en code — **à déployer**.  
503 : infra / déploiement PDO.
