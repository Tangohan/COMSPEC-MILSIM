# Athena se superpose aux autres applications du téléphone ATAK

## Contexte

Sur le téléphone ATAK, ouvrir Comptes-rendus, RENS, TASK ou une autre application laissait l’écran Athena visible par-dessus.

## Symptôme

Les libellés Athena (journal, alerter, rapporter) restaient dessinés sur TIC, Eagle Down, RENS, etc. Le formulaire du dessous devenait illisible. Sur le bureau, Athena pouvait aussi rester collée.

## Cause

Le téléphone n’éteint pas toujours la page précédente. Athena continuait de se redessiner une à deux secondes après le changement d’application, et prenait l’écran Comptes-rendus pour le sien (mêmes numéros de champs).

## Correctif

À chaque changement d’application, une seule page COMSPEC / Comptes-rendus reste visible. Athena ne se redessine plus si l’opérateur a déjà quitté cet écran. Comptes-rendus n’affiche plus qu’un formulaire à la fois (TIC, Eagle Down, bilan, FRAGO ou SALUTE).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_hideForeignPages.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onOpened.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updatePanel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_fixReportsLayout.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_Check_Layout.sqf`

## Vérification

Tests `AtakWindowsIsolationAssetTest` et `AtakReportsLayoutAssetTest`. Contrôle visuel en jeu après reconstruction du pack : Athena disparaît en quittant l’app ; TIC n’empile plus Eagle Down ni le bilan.

## Statut

Corrigé (pack à reconstruire).
