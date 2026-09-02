# Carte ATAK — boussole et outils carte recouverts

## Contexte

Sur le téléphone, la carte IceMan a une boussole en haut à gauche et un menu d’outils carte en bas à gauche. Les cartouches COMSPEC (cap, zoom, grille, unité) étaient collés dans ces deux coins.

## Symptôme

- Carré sombre en haut à gauche, par-dessus la boussole.
- Menu Distance / Mark Houses / Height etc. coincé sous les cartouches d’indicatif et de grille.

## Cause

- Un fond sombre était appliqué aux commandes natives de boussole.
- Un second cartouche de cap et les boutons de zoom étaient posés au même endroit.
- Les cartouches curseur et unité étaient calés en bas à gauche, pile sur les outils carte.

## Correctif

- Boussole native : fond transparent, plus de cartouche de cap par-dessus.
- Zoom plus / moins déplacé en haut à droite.
- Cartouches grille et unité calés à droite, en laissant le bas gauche aux outils carte.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

- Tests d’assets HUD : boussole non recouverte, cartouches à droite, zoom à droite.
- Rebuild du pack Overwatch.

## Statut

corrigé
