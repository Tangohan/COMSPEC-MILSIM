# Aimant du polygone inopérant, erreur à chaque case

## Contexte
RPT du 2026-11-08 19:18:59 : `fn_zoneSnap.sqf` ligne 24, « Error * : Type Tableau, Nombre attendu », déclenché par `ZG_MAGO`.

## Symptôme
Tracé aimanté sans effet, tracé interrompu, trait qui ne suit pas la route.

## Cause
SQF ne distingue pas la casse : `private _c` dans `_cell` masquait `_C`, la taille de la case de 64 m. Chaque case absente du cache échouait.

## Correctif
`_C` devient `_cellM`. Entrées mal formées refusées sans erreur. Même contrôle sur les autres fichiers du tracé : voir `atak-zones-remplissage-invisible`.

## Fichiers touchés
`fn_zoneSnap.sqf`, `fn_zoneAction.sqf`, `fn_zoneMap.sqf`.

## Vérification
`tests/Unit/AtakZonesRoutesTraceTest.php`, `hemtt check`. Rien ne peut être testé en jeu ici.

## Statut
corrigé à vérifier en jeu
