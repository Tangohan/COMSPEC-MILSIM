# Page Athena — boutons superposés

## Contexte

Application Athena dans le tiroir du téléphone ATAK.

## Symptôme

Journal, Alerter, Rapporter, Poste, photos et connexion se chevauchaient. Une grande zone vide occupait le bas de l’écran.

## Cause

Tous les boutons étaient déclarés aux mêmes coordonnées, masqués par `show = 0` et repositionnés au runtime. Si le groupe IceMan n’était pas prêt, tout restait empilé.

## Correctif

Quatre écrans exclusifs (`RscControlsGroup` 9770–9773). Un seul visible. Coordonnées HPP non superposées.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/athena_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_pageCtrl.sqf`
- `tests/Unit/AtakAthenaPanelLayoutTest.php`

## Vérification

Ouvrir Athena : quatre onglets, aucun chevauchement. Relancer Arma complètement.

## Statut

corrigé
