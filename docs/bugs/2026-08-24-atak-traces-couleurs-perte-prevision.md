# Traces ATAK — couleurs, pertes, doute, distance possible

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Les fils de déplacement sur la carte (téléphones, véhicules, unités) étaient tous cyan, pleins, sans indiquer une coupure de liaison ni la distance encore possible pendant un silence.

## Symptôme

Impossible de distinguer un téléphone d’un véhicule, ni de voir où la localisation s’est arrêtée, ni jusqu’où le contact a pu aller depuis le dernier point.

## Cause

Les traces d’unités étaient un seul trait uniforme, sans type, sans trou, sans marqueur de perte.

## Correctif

- Couleurs : téléphone (cyan), véhicule (ambre), à pied (vert), aérien (bleu).
- Tirets dès qu’il y a un doute (silence trop long, liaison retardée).
- Croix rouge à la perte de liaison ou de position.
- Cercle / trait en pointillés : distance plausible depuis la dernière position connue.

## Fichiers touchés

- `app/Services/Tactical/AtakMotionMath.php`
- `public/assets/js/atak-motion.js`
- `public/assets/js/atak-motion-map.js`
- `public/assets/js/atak-sse-layers.js`
- `public/assets/css/atak-motion.css`
- `views/atak.php`
- `tests/Unit/AtakMotionMathTest.php`

## Vérification

Smoke PHP `reachRadiusM` + drapeaux `gap` / `uncertain` sur la trace. Carte : traces colorées, croix à la coupure, cercle qui grandit si le téléphone ou le véhicule ne remonte plus.

## Statut

corrigé
