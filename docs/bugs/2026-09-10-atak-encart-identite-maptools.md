# Encart d’identité manquant sur la carte ATAK

## Contexte

10 septembre 2026. Carte du téléphone ATAK. L’opérateur doit voir un encart superposé, comme les outils carte, avec indicatif, nom, groupe, fonction et position.

## Symptôme

Aucun encart d’identité n’apparaît sur la carte. L’indicatif, le nom, le groupe, la fonction et la position ne sont plus lisibles. Le bandeau sous l’heure, trop fin, disparaît ou passe inaperçu.

## Cause

L’identité native du téléphone (trois lignes empilées) était masquée. Le remplacement COMSPEC était une bande d’une ligne calée sous l’heure, trop petite, souvent hors zone utile. Groupe, nom et fonction n’y figuraient pas.

## Correctif

Un encart superposé reprend le style des outils carte : cinq lignes (indicatif, nom, groupe, fonction, position), en bas à gauche, au-dessus des outils, hors du tiroir.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateMapHud.sqf`
- `tests/Unit/AtakIcemanHudAssetTest.php`
- `tests/Unit/AtakMapUiArchitectureAssetTest.php`
- `app/Support/DevDispatchCatalog.php`

## Vérification

Ouvrir la carte du téléphone : encart en bas à gauche, cinq lignes lisibles, outils carte visibles en dessous. Relancer Arma complètement après le pack.

## Statut

corrigé (pack à recharger, quitter Arma complètement)
