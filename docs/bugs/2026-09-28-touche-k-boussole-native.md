# Touche K — boussole native bloquée

**Date :** 2026-09-28  
**Statut :** corrigé

## Contexte

Le raccourci CBA `comspec_menu_hub` était assigné par défaut à **K** (DIK `0x25`) pour ouvrir le téléphone ATAK / la tablette Overwatch.

## Symptôme

Appuyer sur K n’affiche plus la boussole native d’Arma 3. La touche ouvre à la place le téléphone ATAK (ou la tablette).

## Cause

`CBA_fnc_addKeybind` avec défaut `[0x25, [false, false, false]]` et code de descente qui renvoie `true` : la touche est consommée, le jeu ne reçoit plus l’action boussole.

## Correctif

- Défaut du raccourci hub passé à « aucune touche ».
- Migration ponctuelle de profil (`COMSPEC_KeybindHubKFreed_v1`) : écrase une fois l’ancien binding K.
- Textes d’aide / carnet / hub mis à jour (plus de « touche K »).
- Ctrl+K et Ctrl+Shift+K conservés pour messagerie / apps.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/display_hub.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_chatDialogOnLoad.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getPhoneConnectInfo.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateLinkDiary.sqf`

## Vérification

1. Rebuild PBO `connect`.
2. Relancer Arma (nouveau profil ou profil existant après migration).
3. K → boussole native visible.
4. Options → Contrôles → Extension Addon → « Téléphone ATAK / tablette » sans touche (ou raccourci choisi librement).

## Statut

Corrigé.
