# Modules roleplay / menus Zeus et Eden absents

## Contexte

Après les rebuilds Overwatch / SSE du 23 août, les zones roleplay ATAK ne
s’appliquaient plus en jeu, et les menus COMSPEC disparaissaient de Zeus et d’Eden.

## Symptôme

- Catégories **COMSPEC Roleplay** et **COMSPEC SSE** vides dans Zeus Enhanced.
- Attributs Eden cassés ou absents.
- Journal : `iteminfo.side`, icônes PNG introuvables, « Modules zone ZEN ignorés ».

## Cause

1. **SSE Zeus** redéfinissait `Module_F: Logic` avec `AttributesBase` — ça pollue
   `CfgVehicles` (ACE / ZEN / Eden).
2. **Anti-doublon ZEN** : si les modules config existaient, ils n’étaient plus
   enregistrés dans Zeus Enhanced. Or ZEN ne les affichait pas (icônes PNG
   manquantes) → menus vides.
3. Icônes `comspec_icon_*.png` référencées mais absentes du PBO.
4. `exitWith` au milieu d’un `if/else` et `ppEffectDestroy` mal appelé : les
   effets roleplay ne se chargeaient plus.
5. Deux fois les mêmes classes FRS dans `CfgFunctions` (connect 1.4.38).
6. PBO / dossiers extraits en double dans `@COMSPECOverwatch/addons`.

## Correctif

- SSE Zeus : simple `class Module_F;` (comme Overwatch).
- Zones roleplay : icônes vanilla, Eden seulement (`scopeCurator = 0`),
  enregistrement ZEN toujours actif.
- SQF roleplay / marqueurs BCE : syntaxe corrigée.
- Connect 1.4.39 : plus de doublon FRS dans `CfgFunctions`.
- Nettoyage des PBO et dossiers extraits en trop.

## Fichiers touchés

- `mod/@COMSPEC_SSE/addons/zeus/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_roleplay_zone.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_sse.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/eden_sse_attributes.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenRoleplayModules.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenSseModules.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateAtakEnhancedRoleplay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyRoleplayPpEffects.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_resolveBceMarkerText.sqf`

## Vérification

Rebuild `connect.pbo` 1.4.39 + `comspec_sse_zeus.pbo`. Relancer Arma :
catégories COMSPEC dans Zeus Enhanced et Eden ; poser une zone roleplay
applique bien l’effet en jeu.

## Statut

Corrigé.
