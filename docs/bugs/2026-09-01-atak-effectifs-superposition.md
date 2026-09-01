# Tableau des effectifs recouvert sur la carte du poste

## Contexte

Carte du poste ATAK. Le tableau des effectifs est le bandeau bas sous la carte. Le journal d’analyse et le bouton Unités sont superposés à la carte.

## Symptôme

Le tableau des effectifs n’était plus visible. Le journal d’analyse occupait le bas gauche. Le bandeau de contexte (réseau / journal) recouvrait la zone du tableau. Le bouton Unités à gauche de la carte n’ouvrait pas ce tableau.

## Cause

Le calque C2 couvrait toute la colonne carte, y compris le bandeau des effectifs. Le journal d’analyse s’ouvrait dès l’arrivée et restait au-dessus. Unités à gauche ouvrait le tableau tactique plein écran au lieu du bandeau effectifs.

## Correctif

- Le calque C2 s’arrête au-dessus du tableau des effectifs. Le tableau reste au premier plan.
- Le journal d’analyse démarre replié, plus petit, dans la carte.
- Unités à gauche de la carte ouvre ou réaffiche le tableau des effectifs.

## Fichiers touchés

- `public/assets/css/atak-map-c2-live.css`
- `public/assets/js/map/atak-c2-bridge.js`
- `views/atak.php`
- `tests/Unit/AtakEffectifsOverlayAssetTest.php`
- `app/Support/DevDispatchCatalog.php`

## Vérification

Tests unitaires `AtakEffectifsOverlayAssetTest` et entrée catalogue 320. Recette : ouvrir la carte du poste, le tableau des effectifs est sous la carte ; Unités à gauche l’affiche s’il était réduit ; le journal d’analyse ne s’ouvre que sur clic.

## Statut

corrigé
