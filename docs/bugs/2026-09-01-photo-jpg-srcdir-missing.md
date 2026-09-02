# Bug — Photo terrain : fichier annoncé introuvable

## Contexte

1er septembre 2026. Un opérateur prend une photo (téléphone / casque). Le journal Overwatch confirme la mise en file, puis échoue. Les photos existent sur le PC mais personne ne sait dans quel dossier.

## Symptôme

```
[Tx] → NotifyNewPhoto — 2026_09_01_21_57_21.jpg
[Tx] OK · NotifyNewPhoto — 2026_09_01_21_57_21.jpg
[Tx] ÉCHEC · PhotoUpload — file_not_found · … | srcdir_missing | dirs=3 | newest_3h
```

La galerie du poste ne reçoit pas le cliché. L’opérateur ne trouve pas le fichier : le jeu et les autres outils photo l’écrivent dans des dossiers différents (profil, captures COMSPEC, dossier d’un autre mod).

## Cause

Un autre outil photo annonce un JPEG dont le dossier n’existe pas. Overwatch envoyait parfois ce nom tel quel. La recherche ne regardait que trois dossiers « Screenshots / Captures » : elle ignorait notamment le dossier local Arma, où le jeu pose souvent le PNG. Si le fichier n’y était pas, le plus récent dans les dossiers balayés datait de plusieurs heures (`newest_3h`) et l’envoi échouait. Le bouton Dossier photos listait plusieurs chemins tronqués, donc illisibles.

## Correctif

- Ne plus envoyer le JPEG fantôme : reprendre une capture Arma (PNG) et recopier le fichier dans **Documents\Arma 3 - COMSPEC\Captures**.
- Chercher aussi dans le dossier local Arma et à côté du profil, pas seulement dans Screenshots.
- L’écran Dossier photos n’affiche plus qu’un seul emplacement, copié dans le presse-papiers.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_showPhotoFolder.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/COMSPECExtension/Extension.cs`

## Vérification

Tests d’assets photo + UPDATE 361. Rebuild du pack. En jeu : prendre une photo — un PNG apparaît dans Documents\Arma 3 - COMSPEC\Captures, puis au poste. Dossier photos copie ce chemin. Plus de `srcdir_missing` sur un JPEG daté.

## Statut

corrigé (pack à recharger, quitter Arma complètement)
