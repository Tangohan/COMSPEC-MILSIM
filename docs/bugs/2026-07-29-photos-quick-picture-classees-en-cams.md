# Bug — photos Quick Picture classées dans CAMS

## Contexte

Remontée web des vues prises depuis l’app photo ATAK / Quick Picture.

## Symptôme

- une vraie photo terrain pouvait apparaître dans `CAMS`
- le simple fait d’avoir une caméra casque équipée changeait la catégorie côté web

## Cause

- la détection de type d’appareil déduisait `HELMET` à partir de l’équipement porté
- cette règle s’appliquait aussi aux photos prises depuis l’app photo, alors qu’elles doivent rester en `CTAB`

## Correctif

- suppression du reclassement automatique en `HELMET` pour les envois photo standard
- seuls les flux explicitement capturés depuis la caméra/casque gardent le type `HELMET`

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_sendPhoto.sqf`

## Vérification

- une photo Quick Picture doit apparaître dans `PHOTOS`
- une capture casque dédiée doit rester dans `CAMS`

## Statut

`corrigé à vérifier en jeu`
