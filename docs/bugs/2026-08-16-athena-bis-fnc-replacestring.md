# Athena — BIS_fnc_replaceString indéfini

## Contexte

À l’ouverture / rafraîchissement du panneau Athena, une erreur script bloquait l’affichage.

## Symptôme

Console : `Error Variable indéfinie dans une expression: bis_fnc_replacestring`  
Fichier : `fn_athena_updatePanel.sqf` ligne ~275.

## Cause

Appel à `BIS_fnc_replaceString`, fonction non disponible dans ce contexte (Functions Library absente / non compilée à temps).

## Correctif

Remplacement par la commande native SQF `replaceString` dans :

- `fn_athena_updatePanel.sqf`
- `fn_athena_bridgeIcemanAlert.sqf`
- `fn_athena_bridgeIcemanBda.sqf`
- `fn_athena_onOrderReceived.sqf`
- `fn_zeusShowPlayerAtak.sqf` (connect)

Addon `atak_athena` → 1.0.21.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanAlert.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanBda.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onOrderReceived.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_zeusShowPlayerAtak.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`

## Vérification

Rebuild PBO `atak_athena` (+ `connect` si déployé). Relancer → ouvrir Athena sans erreur console.

## Relance (12:19)

Erreur console encore `BIS_fnc_replaceString` L275 alors que les **sources**
utilisent déjà `replaceString` natif. Cause : session Arma avec l’ancien PBO
en mémoire (ou launcher pointant un autre `@COMSPECOverwatch`).

Correctif : rebuild `atak_athena` **1.0.22** + `comspec_overwatch_atak_athena.pbo`.
**Quitter Arma complètement** avant de retester.

## Statut

Corrigé dans sources + PBO 1.0.22 — relance Arma obligatoire
