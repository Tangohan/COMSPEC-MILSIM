# 2026-09-01 — Route du poste et vibration au chargement

## Contexte

Le guidage GPS existait en jeu et côté serveur. L’outil Route de la carte web ne faisait qu’un profil de relief. Au chargement du poste, le navigateur bloquait aussi une vibration sans geste.

## Symptôme

- Route ne transmettait pas d’itinéraire aux opérateurs.
- Console : vibration refusée tant qu’aucun clic n’avait eu lieu ; journaux de bibliothèque et d’effets au démarrage.

## Cause

Route appelait l’analyse de relief. Les itinéraires GPS n’étaient pas créés depuis la carte. `navigator.vibrate` partait au premier son de démarrage, avant tout geste.

## Correctif

Brancher Route sur la création d’itinéraire (points numérotés, transmission, points atteints en gris). Ne vibrer qu’après un geste. Couper les journaux de démarrage inutiles.

## Fichiers touchés

- `public/assets/js/atak-gps-routes.js`
- `public/assets/js/atak-map-tools.js`
- `public/assets/js/map/atak-c2-bridge.js`
- `public/assets/js/atak-sounds.js`
- `public/assets/js/atak-roleplay-effects.js`
- `public/assets/js/atak-roleplay-ctab.js`
- `views/atak.php`

## Vérification

- Tests d’assets `AtakGpsRoutesAssetTest`.
- Recharger `/atak`, tracer deux points, transmettre. Pas d’avertissement vibration avant le premier clic.

## Statut

Corrigé.
