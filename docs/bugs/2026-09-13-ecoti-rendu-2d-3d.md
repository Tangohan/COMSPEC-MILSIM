# Affichage situation — double mode de rendu des pastilles

## Contexte

Demande opérateur : pouvoir choisir un rendu 3D monde ou un rendu 2D écran (anti-chevauchement), depuis Paramètres.

## Symptôme

Le seul rendu disponible était le 3D monde : pastilles difficiles à démêler quand plusieurs points se croisent.

## Cause

`drawIcon3D` ne gère pas la collision entre libellés ni un fond/bordure de pastille propre.

## Correctif

- Réglage **Rendu des pastilles** (Paramètres ATAK + CBA) : `world3d` | `screen2d`
- Mode 2D : calque HUD, `worldToScreen`, décalage vertical si chevauchement, fondu avec la distance
- Silhouettes bâtiments inchangées (lignes 3D)

## Fichiers touchés

- `fn_ecotiDrawBadge.sqf`, `fn_ecotiDraw.sqf`
- `fn_ecotiHudEnsure/Hide/Render.sqf`, `fn_ecotiApplyRenderModeSetting.sqf`
- `settings_page.hpp`, `fn_athena_updateSettings/ecotiRenderModeSave/settingsSave.sqf`
- `XEH_preInit/postInit`, `config.cpp` (RscTitles)

## Vérification

Pack **1.5.66 / 1.0.113**. Paramètres → Rendu des pastilles → basculer sous JVN.

## Statut

corrigé
