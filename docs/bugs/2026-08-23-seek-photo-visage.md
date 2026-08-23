# Photos visage SEEK introuvables / absentes de la fiche

## Contexte

Le bouton **Photo du visage** du terminal SEEK (Overwatch) et l’action ACE
« Photo faciale » (SSE) ne faisaient pas remonter d’image utilisable sur Athena.

## Symptôme

- Message « photo capturée » en jeu, mais la fiche identité reste sans visage.
- Journal : `UploadSsePhoto — ERR|file_not_found`.
- Action ACE « Photo faciale » : message de succès sans aucune image.

## Cause

1. **Capture derrière le terminal** : `screenshot` clichait tout de suite, sans
   cacher l’interface ni cadrer la tête. Le PNG pouvait ne jamais s’écrire
   (quota Screenshots / HDR trop bas).
2. **Envoi trop tôt** : `UploadSsePhoto` cherchait le fichier en synchrone
   après 1,25 s. Un nom seul (`COMSPEC_SSE_Face_….png`) n’attendait pas le
   flush disque (contrairement aux photos ATAK, ~12 s en file).
3. **ACE SSE** : `fn_captureFace.sqf` posait seulement un drapeau `facePhoto`,
   sans capture ni envoi.
4. **Fiche Athena** : même après un envoi réussi, la page identité ne lisait
   que `primary_photo`, jamais renseigné quand la liste `photos` était chargée.

## Correctif

- Capture SEEK : masquage du terminal, caméra sur la tête, nom PNG stable,
  restauration de l’interface.
- DLL 2.0.3 : `UploadSsePhoto` rejoint la file photo (attente fichier, POST
  hors thread jeu, rappel `SsePhotoUpload`).
- ACE « Photo faciale » : déclenche la même capture Overwatch.
- Fiche identité : `primary_photo` dérivé de la liste des photos.

## Fichiers touchés

- `connect/functions/fn_sseCaptureFacePhoto.sqf`
- `connect/functions/fn_ssePersonDialogShow.sqf`
- `connect/functions/fn_ssePersonDialogSubmit.sqf`
- `connect/functions/fn_extensionCallback.sqf`
- `COMSPECExtension/Extension.cs`
- `mod/@COMSPEC_SSE/addons/biometrics/functions/fn_captureFace.sqf`
- `app/Repositories/SsePersonRepository.php`

## Vérification

1. Rebuild Native AOT (`dotnet publish`) + PBO `connect` et `comspec_sse_biometrics`.
2. **Quitter Arma complètement** (DLL déjà chargée sinon).
3. Options d’affichage : qualité HDR au moins **Moyen**.
4. Ouvrir SEEK sur une personne → **Photo du visage** : l’écran disparaît un
   instant, puis « PHOTO ARMÉE ».
5. Transmettre la fiche → journal `UploadSsePhoto OK` puis
   « Photo du visage reçue au poste de commandement ».
6. Portail SSE / onglet Personnes : le visage s’affiche sur la fiche.

## Statut

Corrigé (à valider in-game après relance Arma).
