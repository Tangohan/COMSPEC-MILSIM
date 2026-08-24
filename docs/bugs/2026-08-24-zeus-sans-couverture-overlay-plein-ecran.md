# Zone Zeus « Sans couverture ATAK » visible hors du téléphone

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Le module Zeus **Zone sans couverture ATAK** doit couper la liaison **sur le terminal** (écran du téléphone ATAK Enhanced). Le monde 3D et le cadre autour du téléphone doivent rester normaux.

## Symptôme

Grain, aberration et voile sur **toute** l’interface Arma. Autour du téléphone : bandeaux hors cadre (échelle de carte, etc.). L’écran ATAK lui-même continuait souvent d’afficher la carte comme si de rien n’était.

## Cause

1. `applyZoneEffects` appliquait des `ppEffect` (grain, aberration, colorimétrie) sur le viewport entier.
2. L’overlay « pas de signal » ne se déclenchait pas pour une zone sans couverture (`isAtakFunctional` ignore les zones).
3. Si l’overlay ne trouvait pas l’écran du téléphone, il tombait sur tout l’écran (`safeZone`).

## Correctif

- Plus d’effet visuel monde pour les zones / le roleplay : nettoyage des `ppEffect` restants.
- Overlay « Aucune couverture » **uniquement** sur l’écran du téléphone / tablette.
- Refus de coller l’overlay sur un rectangle quasi plein écran.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyZoneEffects.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_applyRoleplayPpEffects.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateDeviceOverlay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateAtakEnhancedRoleplay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.59)

## Vérification

Rebuild `connect.pbo` 1.4.59. Relancer Arma. Zeus : poser **Zone sans couverture ATAK**. Hors téléphone : vue 3D normale. Ouvrir l’ATAK : message « Aucune couverture » **dans** l’écran du terminal, pas autour.

## Statut

Corrigé.
