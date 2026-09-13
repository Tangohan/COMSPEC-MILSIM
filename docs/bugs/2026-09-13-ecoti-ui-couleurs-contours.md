# ECOTI — UI sombre et cadre bâtiment « boîte »

## Contexte

Affichage situation sous JVN : badges noirs illisibles, pas de surbrillance personnes, silhouette bâtiment = cadre AABB type Eden.

## Symptôme

- Icônes / textes noirs sous vision nocturne.
- Contour bâtiment décollé du volume réel.
- Pas de choix de couleurs ; pas de découpe « au regard ».

## Cause

- Couleurs / plaques trop sombres pour le post-process JVN.
- Silhouette basée uniquement sur `boundingBoxReal`.
- Pas de thème ni d’action ACE de découpe ponctuelle.

## Correctif

Thèmes de couleurs, badges contrastés, contour personnes, empreinte bâtiment échantillonnée, ACE « Découper à la hauteur regardée », réglage ATAK/CBA.

## Fichiers touchés

- `connect/functions/fn_ecoti*.sqf` (badge, draw, building, unit outline, theme, cutAtLook, footprint…)
- `connect/XEH_preInit.sqf` / `XEH_postInit.sqf` / `fn_initACE.sqf` / `config.cpp`
- `atak_athena/ui/settings_page.hpp`, `fn_athena_ecotiThemeSave.sqf`, `fn_athena_updateSettings.sqf`

## Vérification

Rebuild Overwatch 1.5.60 + Athena 1.0.108, activer HUD, tester thème + découpe ACE.

## Statut

corrigé (limite murs non ouverts conservée)
