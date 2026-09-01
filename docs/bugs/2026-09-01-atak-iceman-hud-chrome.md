# 2026-09-01 — Chrome HUD tablette ATAK Enhanced

## Contexte

La tablette IceMan / BCE se lisait en gris clair, avec trois lignes d’indicatif en bas à droite et presque rien sous le curseur. Le rendu de référence (charbon, cyan, cartouches GRID / unité) n’existait pas. Un recouvrement tiroir / carte avait déjà été corrigé (Check_Layout).

## Symptôme

Les chiffres de grille, distance et cap étaient pâles ou absents. Les cases indicatif IceMan pouvaient se coller au tiroir d’applications quand le panneau s’ouvrait.

## Cause

COMSPEC thématise le tiroir (apps Athena) mais ne restylait pas le chrome carte IceMan. Les OSD curseur cTab (grille / altitude / distance) ne sont pas instanciés sur le téléphone Android.

## Correctif

Cartouches COMSPEC posées **dans le rectangle carte** : curseur (grille, distance, altitude, gisement, portée, écart d’altitude) et unité suivie (groupe, indicatif, grille, altitude, vitesse, heure). Cap en degrés vrais et zoom plus / moins. Tiroir, Drone Ops et fenêtres caméra déjà présentes passent en charbon / cyan. Pas de faux poste MQ-9.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_mapHudZoom.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/atak_theme.hpp`

## Vérification

- Lecture des positions : cartouches calées sur `ctrlPosition` de la carte, jamais sur le groupe 4660 (tiroir).
- Aperçu statique `tmp-verify-atak-iceman-hud.html`.
- Tests d’assets Athena 1.0.58.

## Statut

corrigé (Athena 1.0.58)
