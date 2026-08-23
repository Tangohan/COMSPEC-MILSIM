# Photos ATAK — file_not_found malgré NotifyNewPhoto OK

## Contexte

Journal du 23/08 ~04:29–04:32 : fiche personne et FRS passent, mais les photos
échouent. L’opérateur prend des clichés depuis le terminal ATAK ; Athena les
met en file puis ne trouve pas le fichier.

## Symptôme

- `NotifyNewPhoto OK · COMSPEC_1238_46274.png` puis `PhotoUpload ÉCHEC · file_not_found`
  (`name_only|dirs=3|newest_5h`)
- SEEK visage : `UploadSsePhoto — ERR|file_not_found` avec un stem
  `COMSPEC_SSE_Face_1.1141e+06.png`
- File SSE `SendSSE` `not_a_tactical_report` (hors périmètre photo)

## Cause

1. **Quota Screenshots Arma (250 Mo)** : `screenshot` échoue sans écrire un
   nouveau PNG. Les dossiers scannés ont encore des fichiers (~5 h) mais pas
   le nom annoncé.
2. **HDR trop bas** : même commande échoue silencieusement (wiki BI, HDR ≥ Moyen).
3. **Notation scientifique SQF** : `format ["%1", floor (diag_tickTime * 1000)]`
   produit `1.1141e+06` — le fichier disque n’a jamais ce nom.
4. Vue casque « temps réel » (aperçus périodiques / flux) saturait le quota
   et n’était pas fiable.

## Correctif

- Noms PNG via `toFixed 0` (SEEK, ATAK, fiches) ; refus de `screenshot` si
  le jeu renvoie `false` (message HDR).
- DLL : ménage des PNG `COMSPEC_*` si le dossier dépasse ~180 Mo, relevé de
  `maxScreenShotFolderSizeMB` dans le profil, recherche `-profiles=`,
  normalisation `1.1141e+06` → entier, attente d’écriture allongée.
- ATAK web : bandeau « vue casque temps réel pas au point » ; flux casque
  désactivé. Les clichés ATAK restent l’onglet Photos.

## Fichiers touchés

- `connect/functions/fn_captureReconImage.sqf`
- `connect/functions/fn_sseCaptureFacePhoto.sqf`
- `connect/functions/fn_ssePersonDialogSubmit.sqf`
- `connect/functions/fn_intelNoteSubmit.sqf`
- `atak_athena/functions/fn_athena_onHelmetMediaRequest.sqf`
- `atak_athena/functions/fn_athena_snapshotVideoFeed.sqf`
- `COMSPECExtension/Extension.cs`
- `views/atak.php`, `public/assets/js/atak-cams.js`, `atak-unit-menu.js`
- `app/Controllers/Api/AtakApiController.php`

## Vérification

1. Rebuild Native AOT (`dotnet publish`) + PBO `connect` et `atak_athena`.
2. **Quitter Arma complètement** (DLL déjà chargée sinon).
3. Options d’affichage : qualité HDR au moins **Moyen**.
4. Prendre une photo depuis l’app Photos d’ATAK → un `COMSPEC_*.png` **nouveau**
   dans `Documents\Arma 3 - Other Profiles\<profil>\Screenshots`.
5. Journal : `PhotoUpload OK` ; onglet Photos du portail à jour.
6. Onglet Cams : bandeau « pas encore au point » ; menu contact : flux casque grisé.

## Statut

Corrigé côté sources / DLL (à valider in-game après relance Arma).
Le journal 04:28–04:41 du 23/08 est le même incident (quota + stem scientifique SEEK).
