# Relief 3D — symbologie OTAN (APP-6 / milsymbol)

## Contexte

Overwatch Beta Relief 3D affichait les contacts en pastilles colorées, peu lisibles pour un opérateur formé à la symbologie OTAN.

## Symptôme

Cadres non standard, pas de distinction de rôle, pas de vecteur de mouvement, pas d’atténuation des contacts périmés, alerte médicale peu visible.

## Cause

La couche deck.gl utilisait des `ScatterplotLayer` génériques. `milsymbol` et `NatoSidcIcons` existaient déjà pour la carte 2D ATAK / Overwatch classique, mais n’étaient pas chargés ni branchés sur Overwatch Beta 3D.

## Correctif

- Chargement de `milsymbol.js` + `milstd-catalog.js` sur Overwatch Beta.
- Module `OverwatchGlSymbols.js` : SIDC, cache d’icônes, fraîcheur, vecteur, alerte médicale.
- `IconLayer` billboard taille constante, sans test de profondeur.
- Labels monospace declutterés ; sélection en anneau ; clusters numérotés.

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/js/overwatch-gl/OverwatchGlSymbols.js`
- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`
- tests + UPDATE #00722

## Vérification

Ctrl+F5 → Relief 3D : contacts en cadres OTAN (bleu/rouge/vert/jaune), vecteur si en mouvement, anneau médical si blessé/inconscient.

## Statut

Corrigé.
