# Photo terrain — HTTP 400 missing_image

## Contexte

Cliché trouvé sur le PC (`NotifyNewPhoto` OK) puis envoi vers le poste.

## Symptôme

`PhotoUpload` échoue en HTTP 400. Le poste indique qu’aucune image n’a été reçue.

## Cause

Le corps multipart partait en flux chunké. PHP ne remplissait pas `$_FILES`.

## Correctif

Envoi bufferisé (`ByteArrayContent` + `Content-Length`) sous le champ `image`. Journal serveur si le fichier manque encore.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Controllers/Api/AtakApiController.php`
- `app/Support/TerrainUploadedImage.php`
- `tests/Unit/OverwatchPhotoNotConnectedSpamAssetTest.php`

## Vérification

Une photo prise depuis le téléphone apparaît au poste. Journal sans 400 `missing_image`.

## Statut

corrigé
