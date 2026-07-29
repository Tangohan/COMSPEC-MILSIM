# Bug — doublon photo tablette + aperçu casque

## Contexte

Prise de photo via l’app ATAK Enhanced (BCE) avec caméra casque équipée et module aperçus casque actif.

## Symptôme

Deux entrées identiques dans le panneau Photos :
- **Photo tablette** (`CTAB`) — correcte, avec nom de fichier BCE
- **Caméra casque** (`HELMET`) — « Aperçu casque — … », même image et même horodatage

## Cause

1. `fn_athena_snapshotVideoFeed` (toutes les ~20 s) appelle `captureReconImage` sans chemin → `UploadLatestScreenshot` prenait **le fichier le plus récent** du dossier Screenshots, souvent la photo BCE fraîchement écrite.
2. Repli `bridgeIcemanPhoto` via `screenshot ""` + `UploadLatestScreenshot` pouvait produire le même effet.

## Correctif

- **Mod** : upload des aperçus casque/drone sur le fichier dédié `COMSPEC_AthenaFeed` (plus « latest »).
- **Mod** : pause des aperçus auto 35 s après `bce_took_screenshot` / événements Photo Library ; skip si caméra BCE ouverte.
- **Mod** : repli bridge sans `UploadLatestScreenshot` générique.
- **Web** : dédoublonnage affichage si même auteur/grille/heure CTAB + HELMET « Aperçu casque ».

## Fichiers touchés

- `mod/.../connect/functions/fn_captureReconImage.sqf`
- `mod/.../connect/functions/fn_markBcePhotoCapture.sqf`
- `mod/.../connect/config.cpp`
- `mod/.../atak_athena/functions/fn_athena_snapshotVideoFeed.sqf`
- `mod/.../atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/.../atak_athena/XEH_postInitClient.sqf`
- `public/assets/js/atak-cams.js`

## Vérification

- Prendre une photo ATAK avec casque équipé → une seule entrée **Photo tablette** dans Photos.
- Les aperçus casque manuels / périodiques légitimes continuent d’apparaître dans Caméras quand le module est actif.

## Statut

`corrigé à vérifier en jeu`
