# Photo terrain — refusée alors que le cliché existe

## Contexte

Photo prise depuis le téléphone. Le journal indique que le cliché a bien été trouvé, puis que l’envoi vers le poste a échoué.

## Symptôme

La photo reste sur le PC. Le poste n’affiche rien dans Photos. Le journal montre un refus immédiat, y compris en renvoyant le même cliché.

## Cause

L’envoi partait incomplet (taille inconnue, ou attente d’un accusé avant le fichier). Le poste ouvrait la requête trop tôt et ne voyait pas l’image.

Un premier correctif ne remplissait que la partie image : le formulaire entier restait découpé. Insuffisant.

## Correctif

Toute la photo part d’un seul bloc, sans attente préalable. Si le poste reçoit encore le corps brut, il reconstitue l’image. Liaison 1.18.10 — relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Support/TerrainUploadedImage.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Controllers/Api/SseApiController.php`
- `app/Controllers/Api/SseFieldNoteApiController.php`
- `app/Support/ComspecApiKeyAuth.php`
- `tests/Unit/OverwatchPhotoNotConnectedSpamAssetTest.php`
- `tests/Unit/TerrainUploadedImageTest.php`

## Vérification

Une photo prise depuis le téléphone apparaît dans Photos au poste. Le journal n’affiche plus le refus immédiat.

## Statut

corrigé (liaison 1.18.10)
