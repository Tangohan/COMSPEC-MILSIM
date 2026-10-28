# Montre Garmin — carte décentrée et vue agrandie sans clics

## Contexte

Vue agrandie de la montre (Ctrl+Maj+O), écran Carte. Capture du 27/10/2026 : cadran avec bandes noires haut/bas, point pas au centre, pas de gestion à la souris.

## Symptôme

- La carte ne remplit pas le cadran (bandes noires) et mon point n’est pas centré.
- En vue agrandie, impossible de cliquer sur LIGHT / UP / DOWN / START / BACK ni d’utiliser la molette.

## Cause

1. Carte trop petite dans le cercle (`0,8` de rayon) → fond noir visible.
2. `ctrlMapAnimAdd` avec durée `0,1` s relancé chaque tick : l’animation ne finissait jamais, le centre restait décalé.
3. Vue agrandie = calque titre (`idd=-1`) : aucun clic ni molette.

## Correctif

- Carte plus grande que le cadran (masquée par le boîtier).
- Recentrage en durée 0 avec correction d’écart écran (comme la carte du téléphone).
- Vue agrandie = dialogue cliquable : zones sur les boutons, molette (zoom carte / changement d’écran), Échap pour revenir au poignet.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/garmin/fn_watch.sqf`
- `app/Support/DevDispatchCatalog.php` (UPDATE #1178)

## Vérification

Quitter Arma complètement, relancer. Ctrl+Maj+O : carte remplit le cadran, point au centre. Clic START / UP / DOWN / BACK / LIGHT. Molette sur la carte = zoom. Échap = poignet.

## Variante — boussole saccadée (même jour)

La couronne de la boussole ne suivait le cap que toutes les 0,1 s : le cadran sautait. Un passage dédié (~30 images/s) avec cap lissé fait tourner les repères sans à-coups.

## Statut

corrigé
