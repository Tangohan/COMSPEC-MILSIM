# Photos — spam NotifyNewPhoto à la connexion

## Contexte

Journal session 2026-09-01 ~17:56 : à l’entrée en mission, rafale `NotifyNewPhoto` / `ERR|not_connected` (plusieurs fichiers `COMSPEC_*.png` toutes les ~2 s), y compris après « Session Athena prête ». Même famille que 15:32 le même jour.

## Symptôme

- Le journal technique se remplit d’échecs d’envoi de photos dès la connexion.
- De nouveaux clichés sont écrits sur le disque en boucle (stems `COMSPEC_<tick>_<rand>.png`).
- Après l’ouverture de session Athena, les envois échouent encore.

## Cause

1. La file photo de l’extension exigeait encore une clé API. La session Athena (jeton de jeu) suffisait pour le reste, pas pour `NotifyNewPhoto` → `not_connected` après handshake.
2. Un échec relançait la capture : retry ~0,5 s, puis un **nouveau** PNG, puis retry ~2,5 s. Boucle infinie tant que la session n’était pas acceptée.
3. Le balayage Photo Library partait dès `linked` / à chaque `applyBootstrap`, y compris pendant le handshake.

## Correctif

- L’extension accepte la session Athena (jeton) comme la clé historique pour enfiler une photo.
- Pas de cliché ni d’envoi tant que la session n’est pas prête ; un `not_connected` ne relance plus de capture.
- Un seul balayage Photo Library après `ready`, une fois le handshake terminé.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/…/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/…/connect/functions/auth/fn_applyBootstrap.sqf`
- `mod/UptoDate/Sources/…/atak_athena/functions/fn_athena_pollIcemanPhotos.sqf`
- `mod/UptoDate/Sources/…/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/…/atak_athena/XEH_postInitClient.sqf`

## Vérification

- [x] Tests unitaires `OverwatchPhotoNotConnectedSpamAssetTest`
- [ ] Relancer Arma avec le pack reconstruit : plus de rafale `NotifyNewPhoto` / `not_connected` à la connexion
- [ ] Après session prête, une photo ATAK part une fois et apparaît au poste

## Statut

corrigé — pack jeu à reconstruire (extension + `connect` / `atak_athena`)
