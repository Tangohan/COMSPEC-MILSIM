# Menus Zeus ATAK : erreur isNull sur un tableau

## Contexte

En Zeus (curateur), clic droit / menus ZEN « ATAK — Infos joueur », « Balise GPS », « IA alliée ». Overwatch 1.4.51, ZEN incomplet (stub `zen_attributes_fnc_addAttribute`).

## Symptôme

Erreur script : `isNull` reçoit un tableau au lieu d’un objet. Les menus GPS / téléphone / IA alliée ne s’appliquent pas.

## Cause

`zen_context_menu_selected` reprend parfois toute la structure `curatorSelected` (`[[objets],[groupes],…]`). Le code faisait `isNull _x` / `count` sur ces sous-tableaux.

## Correctif

Helper `fn_curatorSelectedObjects.sqf` : aplatit la sélection et ne garde que les objets. Menus Zeus branchés dessus.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_curatorSelectedObjects.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenAtakPlayerActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTrackActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`

## Vérification

Contrôle du code : plus aucun `isNull` sur `zen_context_menu_selected` / `curatorSelected` brut. À retester en jeu après rebuild PBO 1.4.52.

## Statut

Corrigé (sources) — rebuild mod requis
