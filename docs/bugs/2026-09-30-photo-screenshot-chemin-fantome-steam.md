# Bug — Photo ATAK : dossier Screenshot fantôme (ancienne install Steam)

## Contexte

30 septembre 2026. Prise de photo depuis le téléphone ATAK. NotifyNewPhoto OK, puis PhotoUpload `file_not_found` / `srcdir_missing`.

## Symptôme

```
[Tx] → NotifyNewPhoto — 2026_09_30_21_17_20.jpg
[Tx] OK · NotifyNewPhoto — 2026_09_30_21_17_20.jpg
[Tx] ÉCHEC · PhotoUpload — file_not_found · … | srcdir_missing | dirs=9 | newest_10d
```

Le chemin annoncé (réglage BCE / Discord) ressemble à :
`C:\Program Files (x86)\Steam\…\@# S.O.A.R - FN + CHR + OBJ\Screenshot`

Ce dossier n’existe pas. Aucun JPEG de la session n’est écrit sur le disque.

## Cause

Le profil Arma conserve `BCE_PicFilePath_edit` pointant vers une ancienne bibliothèque Steam et un nom de pack obsolète (`FN + CHR + OBJ`). L’install réelle est sur `F:\SteamLibrary\…\@# S.O.A.R - FN\Screenshot`.

Le helper de capture ne forçait le dossier réel que si le chemin était « collé » (`Arma 3!Workshop@…`). Un chemin bien formé mais mort était conservé : BCE annonçait un nom de fichier sans jamais l’écrire.

## Correctif

- Toujours privilégier `GetBceScreenshotDir` (DLL) et réaligner `BCE_PicFilePath_edit` au démarrage et à chaque cliché.
- Remapper côté DLL les chemins Workshop fantômes vers le Screenshot réel ; balayer les bibliothèques Steam.
- Nouvelle commande extension `PathExists` pour vérifier un dossier depuis SQF.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bceScreenShot.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

- Profil : `BCE_PicFilePath_edit` contient bien `Program Files (x86)…FN + CHR + OBJ` (inexistant).
- Install Arma : `F:\SteamLibrary\…\@# S.O.A.R - FN\Screenshot` existe.
- Après rebuild : quitter Arma complètement, relancer, prendre une photo → JPEG dans `…\@# S.O.A.R - FN\Screenshot` (ou Captures COMSPEC) puis OK PhotoUpload.

## Statut

corrigé (pack + DLL à recharger, quitter Arma complètement)
