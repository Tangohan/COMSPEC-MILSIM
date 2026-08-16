# ATAK — marqueurs absents alors que le journal les affiche

## Contexte

Carte ATAK : seule l’unité BFT visible ; journal d’activité avec `Marqueur placé — sse_poi_…`.

## Symptôme

Repères créés côté Arma (activité OK) invisibles sur la carte web.

## Cause

1. `AtakDataRepository::getMarkers()` avalait les erreurs BDD et renvoyait `[]`.
2. `pollMarkers()` traitait toute réponse non-tableau comme no-op **mais** une liste vide « succès » **supprimait** tous les marqueurs déjà affichés.
3. Couplé aux 503 / micro-coupures PDO → la carte se vidait alors que le journal (écrit au upsert) montrait encore les placements.
4. Bonus : `createMapMarkers` SSE créait des `sse_poi_*` locaux synchronisables (préfixe sans `_`) → IDs techniques peu utiles sur Athena.

## Correctif

- `getMarkers` : propage l’exception ; `markersIndex` → 503 JSON si BDD down.
- `pollMarkers` : exige `r.ok` ; en erreur, **conserve** les marqueurs affichés.
- `createMapMarkers` : préfixe `_comspec_sse_…` (hors sync Athena).

## Fichiers touchés

- `app/Repositories/AtakDataRepository.php`
- `app/Controllers/Api/AtakApiController.php`
- `public/assets/js/atak-map.js`
- `mod/@COMSPEC_SSE/addons/intel/functions/fn_createMapMarkers.sqf`

## Vérification

1. Déployer PHP + JS.
2. Avec marqueurs en base : recharger ATAK → icônes présentes.
3. Simuler 503 markers : les icônes déjà affichées ne disparaissent plus.

## Statut

corrigé en code — **à déployer**
