# Fausse « Photo remontée » in-game, rien sur le web

## Contexte

ATAK Enhanced affichait « Photo remontée automatiquement » / « Visible sur ATAK web » pour des clichés (ex. `2026_07_22_15_28_34.jpg`), alors que l’onglet Photos du portail restait vide.

## Symptôme

- In-game : détail photo vert « remontée automatiquement »
- Web ATAK (onglet Photos) : aucune image
- Journal : `NotifyNewPhoto` en `OK|queued` sans preuve d’ACK HTTP

## Cause

1. Dès `NotifyNewPhoto` → `OK|queued`, SQF marquait la photo dans `COMSPEC_Athena_PhotoUploaded` **avant** l’upload réel.
2. Le worker DLL enfilait un POST fire-and-forget ; le callback `PhotoUpload` n’était **pas géré** côté SQF.
3. Sur échec (`file_not_found` sur chemins Photo Library morts, HTTP 4xx/5xx), le dédup restait verrouillé ~5 min → retries en `OK|duplicate` (encore pris pour un succès).

## Correctif

- DLL : attendre le POST `/api/recon/images`, callback `OK|uploaded|…` / `ERR|…`, libérer le dédup en cas d’échec.
- SQF : file = `PhotoPending` ; succès web = `PhotoUploaded` via callback `PhotoUpload` ; échec = `PhotoFailed` + message clair.
- Panneau : « Envoi… » / « recue sur ATAK web » / « Echec d'envoi » selon l’état réel.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/.../connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/.../atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/.../atak_athena/functions/fn_athena_updatePanel.sqf`

## Vérification

- [ ] Rebuild DLL + PBO `connect` + `atak_athena`
- [ ] Nouvelle capture récente : panneau « Envoi… » puis « recue sur ATAK web » + image dans l’onglet Photos
- [ ] Ancien cliché chemin mort : panneau « Echec d'envoi », journal `PhotoUpload|fail|file_not_found`
- [ ] Web : onglet Photos du même tenant / même session Athena

## Statut

corrigé — déploiement DLL/PBO requis
