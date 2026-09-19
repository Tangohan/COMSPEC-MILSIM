# Menu d’applications : icônes empilées, fond gris clair

**Date :** 2026-09-19  
**Statut :** corrigé (Athena 1.0.141)

## Contexte

Téléphone ATAK, chevron du menu d’applications. Premier signal : tuiles empilées, fond gris clair. Second signal le même jour : fond sombre revenu, mais grille cassée (icônes Video / Photo / Tâches décalées, cases vides, plus de noms).

## Symptôme

Les applications du chevron ne forment plus une grille régulière. Certaines icônes se superposent ou se décalent, d’autres cases restent vides, les noms disparaissent.

## Cause

À l’ouverture, le tiroir part d’une largeur nulle le temps de l’animation. Le téléphone calcule alors la grille sur cette largeur nulle.

Le premier correctif (1.0.139) recaléait les tuiles **et changeait leur taille**. Les icônes et les noms du téléphone sont dessinés pour la taille d’origine : une fois redimensionnées, elles se décalent et les noms disparaissent.

## Correctif

Le fond reste gris sombre. Les tuiles gardent leur taille d’origine. Seul l’emplacement (trois colonnes) est recalé sur la largeur réelle du tiroir, comme le menu d’origine.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_layoutAppDrawer.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.141. Ouvrir le téléphone, chevron : grille 3 colonnes, icônes et noms lisibles, pas de chevauchement, fond sombre.

## Statut

corrigé (Athena 1.0.141)
