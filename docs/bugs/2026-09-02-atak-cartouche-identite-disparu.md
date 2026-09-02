# Cartouche indicatif / rôle / groupe disparu sur la carte ATAK

## Contexte

Téléphone ATAK en jeu. Un cartouche charbon en bas à gauche affiche Indicatif, Rôle, Groupe et Grille (exemple : TA1, Breacher, TA1 · 24th STS Gold Team).

## Symptôme

Le cartouche d’identité n’apparaissait plus sur la carte. L’opérateur ne voyait plus son indicatif, son rôle ni son groupe.

## Cause

Pour dégager la boussole et les outils carte, les cartouches avaient été calés au bord droit de la carte. Ce bord tombe sous le tiroir d’applications : le cartouche d’identité n’était plus visible.

## Correctif

Le cartouche d’identité revient en bas à gauche de la carte visible. Le cartouche curseur (grille, distance) reste à droite, mais calé sur la zone non recouverte par le tiroir.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `tests/Unit/AtakIcemanHudAssetTest.php`
- `app/Support/DevDispatchCatalog.php`

## Vérification

Tests d’assets HUD et UPDATE 392. Recette : ouvrir la carte du téléphone, le cartouche Indicatif / Rôle / Groupe est en bas à gauche, lisible, hors du tiroir.

## Statut

corrigé (Athena 1.0.76, pack à recharger)
