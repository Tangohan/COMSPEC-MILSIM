# Sons ACRE / jeu — isolation audio COMSPEC

**Date :** 2026-09-28  
**Statut :** corrigé (prévention) / à valider en session ACRE

## Contexte

Signalement : disparition du bip roger ACRE (fin d’émission) avec le pack Overwatch chargé. Les volumes ATAK et `CfgSounds` COMSPEC ne doivent jamais couper l’audio du jeu ni des autres mods.

## Symptôme

Après une émission ACRE, le bip roger de confirmation n’est plus entendu (ou d’autres sons tiers semblent absents), alors que la radio fonctionne encore.

## Cause (analyse)

Dans le code source Overwatch (`connect` / `atak_athena`) :

- Aucun `fadeSound`, `soundVolume`, `enableEnvironment` ni override de classes `Acre_*`.
- Les réglages « Volume général ATAK » ne multiplient que les `playSoundUI` COMSPEC (`COMSPEC_ATAK_*`).
- Risque résiduel : handler CBA `acre_remoteStartedSpeaking` trop fragile (ex. `getChannelData` hashmap vs tableau) pouvant planter et perturber la chaîne d’événements ACRE/CBA.
- `sounds[] = {}` dans `CfgSounds` retiré par précaution (classes nommées uniquement).

## Correctif

- Handler radio proximité ACRE durci (params par défaut, hashmap / tableau, pas d’écriture audio).
- Commentaire explicite dans `CfgSounds` : sons ATAK seulement.
- Libellé CBA du volume maître : précise qu’ACRE / le jeu ne sont pas affectés.
- Moniteur radio : `addSpectatorRadio` appelé avec le seul `radioId` ; canal d’origine sauvegardé et restauré via `unmonitorRadioNet` / `radio:unmonitor`.
- Dashboard Relais : `closeDisplay` au lieu de `closeDialog` (overlay `createDisplay`).
- Correctif ESC dashboard (retour `true` via `exitWith`).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initRadioMonitor.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_monitorRadioNet.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_unmonitorRadioNet.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_webBrowserJSDialog.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_openRelayDashboard.sqf`

## Vérification

1. Rebuild PBO `connect`.
2. Session avec ACRE2 : PTT → relâcher → bip roger audible.
3. Volume ATAK à 0 dans Options : sons ATAK muets, roger ACRE et sons jeu toujours présents.
4. Émission d’un autre joueur : pastilles proximité OK, pas d’erreur script RPT liée à `acre_remoteStartedSpeaking`.

## Statut

Correctifs appliqués — validation terrain ACRE recommandée.
