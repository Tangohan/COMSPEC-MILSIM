# Spam UploadReconImage / freeze à chaque screen

## Contexte

Remontée auto Photo Library (Iceman) → Athena. Journal session 2026-08-06 ~12:46–12:54, puis redesign sidecar DLL.

## Symptôme

- Flood `UploadReconImage` / `file_not_found` / `srcdir_missing` toutes les ~1–2 s
- Client Arma qui freeze à chaque capture / au boot
- Chemins Photo Library vers un mod disparu :
  `…\!Workshop\@# S.O.A.R - FN + CHR + OBJ\Screenshot\2026_07_22_….jpg` (dossier inexistant)
- Fichiers absents aussi de `@# S.O.A.R - FN\Screenshot`

## Cause

1. `fn_athena_pollIcemanPhotos` ne marquait une photo comme traitée **qu’en cas de succès**. Échec `file_not_found` → retrait d’inflight → **retry infini**.
2. Chaque tentative appelait l’extension **synchrone** avec Sleep + scan Screenshots → freeze sur le thread jeu.
3. Dépendance aux records Iceman (chemins morts) plutôt qu’aux fichiers réellement écrits sous `Screenshots` / `Screenshot`.

## Correctif (v1 anti-spam)

- Poll : abandon définitif (`PhotoSeen`) après `file_not_found` / `srcdir_missing` ; 1 photo max / cycle ; intervalle 6 s.
- Extension : échec rapide si dossier parent absolu manquant.

## Correctif (v2 sidecar — redesign)

Architecture « un seul brick natif, comportement sidecar » :

1. **SQF** : `NotifyNewPhoto` uniquement (retour immédiat `OK|queued` / `OK|duplicate`). Plus de retries spawn, poll Photo Library ralenti à 30 s, records marqués vus dès le signal.
2. **DLL** : file `PhotoJobs` + worker async (resolve + `QueueMultipartUpload`) hors `callExtension`.
3. **FileSystemWatcher** sur dossiers `Screenshots` / `Screenshot` (profil + Workshop) → enqueue avec pose mémorisée (`UpdatePosition`).
4. **Dédup** path + identité fichier (taille / mtime) pour éviter double envoi watcher + SQF.
5. Auth / session Athena inchangées (`_apiKey`, `_sessionToken`, `_steamUid`).

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/…/atak_athena/functions/fn_athena_pollIcemanPhotos.sqf`
- `mod/UptoDate/Sources/…/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/…/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/…/connect/functions/fn_captureReconImage.sqf`
- (+ rebuild DLL / `connect.pbo` / `atak_athena.pbo`)

## Vérification

- [x] Rebuild + deploy Workshop (DLL + `connect` / `atak_athena`) — SHA256 sync 4 racines, 2026-08-06
- [x] Staging `publisher\@COMSPECOverwatch` resynchronisé (était périmé 2026-07-29)
- [ ] Relancer Arma : plus de spam `UploadReconImage` / `NotifyNewPhoto` sur vieux clichés
- [ ] Nouvelle capture récente apparaît sur Athena (via signal SQF **ou** watcher)
- [ ] Journal extension : `PhotoWatcher|OK|watching|N`, `PhotoUpload|OK|queued|…`

## Statut

corrigé — déployé Workshop (recette in-game à confirmer)

