# Photo terrain — HTTP 400 missing_image

## Contexte

Cliché trouvé sur le PC (`NotifyNewPhoto` OK) puis envoi vers le poste.

## Symptôme

`PhotoUpload` échoue en HTTP 400. Le poste indique qu’aucune image n’a été reçue.

## Cause

Seule la partie image était bufferisée. Selon le handler HTTP .NET disponible sur le poste,
le conteneur multipart complet pouvait encore partir en flux chunké. PHP ne remplissait alors
pas `$_FILES`.

## Correctif

Envoi du multipart complet en `ByteArrayContent`, avec son type (boundary inclus), son
`Content-Length` réel et le transfert chunké explicitement désactivé. L'image reste sous le
champ `image`. Journal serveur si le fichier manque encore.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Controllers/Api/AtakApiController.php`
- `app/Support/TerrainUploadedImage.php`
- `tests/Unit/OverwatchPhotoNotConnectedSpamAssetTest.php`

## Vérification

Une photo prise depuis le téléphone apparaît au poste. Journal sans 400 `missing_image`.

## Statut

corrigé
