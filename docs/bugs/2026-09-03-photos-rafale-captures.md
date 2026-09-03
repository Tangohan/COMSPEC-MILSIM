# Photos en rafale dans le dossier Captures

## Contexte

Photos prises depuis le téléphone ATAK (caméra IceMan / overlay). Le dossier `Documents\Arma 3 - COMSPEC\Captures` se remplissait de fichiers `COMSPEC_XXXX_YYYYY.png` quasi identiques.

## Symptôme

Une seule prise de vue produisait une rafale (souvent 8 à 80 copies de la même vue fusil / hangar / cellule). Le poste pouvait aussi recevoir plusieurs fois le même cliché.

## Cause

Chaque JPEG IceMan déclenchait un PNG Arma. Ce PNG était ensuite vu comme une nouvelle photo, ce qui relançait un cliché, et ainsi de suite. L’overlay téléphone forçait un nouveau cliché même pour un PNG déjà pris. Un envoi refusé relançait encore un cliché.

## Correctif

Un PNG déjà nommé `COMSPEC_*.png` n’est plus recliché. Moins de trois secondes entre deux déclenchements : on réutilise le dernier fichier. L’overlay ne recliche que pour un JPEG fantôme, pas pour un PNG. Un échec d’envoi n’ouvre plus l’obturateur. Relancer Arma complètement (Overwatch 1.5.16, Athena 1.0.80). Les copies déjà sur le disque restent ; les supprimer à la main si besoin.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `tests/Unit/OverwatchPhotoBurstCapturesAssetTest.php`
- `tests/Unit/OverwatchPhotoNotConnectedSpamAssetTest.php`
- `tests/Unit/AtakQuickPictureAssetTest.php`

## Vérification

Prendre une photo depuis le téléphone : un seul fichier apparaît dans Captures. Une deuxième photo quelques secondes plus tard produit un second fichier distinct, pas une rafale.

## Statut

corrigé (Overwatch 1.5.16)
