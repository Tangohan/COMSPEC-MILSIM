# Overwatch Beta — tuiles de carte en 401

## Contexte

28 septembre 2026. Overwatch Beta (Altis) via le proxy same-origin `/api/atak/tiles`.

## Symptôme

Rafale `401 Unauthorized` sur `GET /public/api/atak/tiles?u=https://jetelain.github.io/Arma3Map/...`. Fond de carte vide / cassé.

## Cause

En production, tout `/api/atak/*` exige une clé ATAK (ou une session navigateur). Leaflet charge les tuiles en `<img crossorigin="anonymous">` : ni cookie de session, ni en-tête de clé → 401. Le contrôleur est pourtant conçu comme public (hôtes filtrés par `AtakRemoteTileGuard`).

## Correctif

Ajouter `/api/atak/tiles` à `atak_exempt_paths` dans `config/tactical_api.php`.

## Fichiers touchés

- `config/tactical_api.php`
- `tests/Unit/AtakTilesProxyExemptTest.php`

## Vérification

Recharger Overwatch Beta sur Altis : les tuiles Jetelain chargent en 200. Un `u=` hors allowlist reste 404.

## Statut

corrigé (à déployer sur le VPS)
