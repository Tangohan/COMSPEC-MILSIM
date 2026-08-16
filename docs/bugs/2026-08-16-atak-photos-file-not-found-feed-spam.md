# Photos — file_not_found / spam COMSPEC_AthenaFeed

## Contexte

Journal Overwatch : `NotifyNewPhoto OK` puis `PhotoUpload file_not_found`
pour `COMSPEC_AthenaFeed` et des JPG Iceman (`srcdir_missing`). Aucune
capture du jour dans `Documents\Arma 3 - Other Profiles\NewPI\Screenshots`
(dernier fichier : 29/07).

## Cause

1. Aperçus auto / capture vide utilisaient le stem fixe `COMSPEC_AthenaFeed`
   via `screenshot` moteur — **aucun fichier écrit** sur ce profil.
2. `OK|duplicate` était traité comme succès → spam log toutes les ~40 s.
3. JPG Photo Library pointent vers un dossier SOAR Workshop absent.

## Correctif

- Capture : **BCE_fnc_screenShot** en priorité, sinon stem unique + attente 2,2 s.
- Backoff 5 min après `file_not_found` (`COMSPEC_FeedSnapFailUntil`).
- `OK|queued` seul = succès ; duplicate ignoré.
- DLL : recherche « newest since enqueue » pour captures async.

## Fichiers

- `connect/functions/fn_captureReconImage.sqf`
- `connect/functions/fn_extensionCallback.sqf`
- `atak_athena/functions/fn_athena_snapshotVideoFeed.sqf`
- `COMSPECExtension/Extension.cs`

## Vérification utilisateur

1. Rebuild PBO `connect` + `atak_athena` **et** DLL `COMSPECExtension`.
2. CBA : désactiver « Aperçus caméra automatiques » si inutile.
3. Vérifier réglage BCE **chemin des captures** (dossier existant).
4. Prendre une photo via app Photos ATAK (BCE) — un `.jpg` doit apparaître
   sur le disque, puis sur Athena web.
5. Les anciens JPG (22/07, chemins SOAR morts) ne remonteront pas.

## Statut

corrigé en sources — rebuild PBO + DLL requis
