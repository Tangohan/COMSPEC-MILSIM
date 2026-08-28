# Photo de visage SEEK envoyée au mauvais canal

## Contexte

Capture de visage depuis le terminal SEEK (fichier `COMSPEC_SSE_Face_*.png`). Journal Overwatch après le passage VPS.

## Symptôme

La photo de visage échoue en deux temps :

1. Envoi vers le canal reconnaissance → refus serveur.
2. Envoi vers la fiche (le bon canal) → refus (format ou fichier non reçu).

La fiche reste sans portrait.

## Cause

Le surveillant de captures du poste de jeu traite **toute** nouvelle image du dossier Screenshots comme un cliché de reconnaissance. Une capture SEEK est donc d’abord poussée au mauvais endroit. Le canal fiche, lui, n’acceptait que quelques types d’image trop stricts (PNG Arma parfois vu comme fichier brut).

## Correctif

- Ne plus envoyer `COMSPEC_SSE_Face_*` au canal reconnaissance (poste de jeu + serveur qui ignore s’il en reçoit encore).
- Sur la fiche : reconnaître PNG/JPEG par le contenu du fichier, et enregistrer même si le déplacement classique échoue.
- IceMan / capture reconnaissance : ne pas relancer un envoi reconnaissance pour une capture visage.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `app/Support/TerrainUploadedImage.php`
- `app/Controllers/Api/AtakApiController.php`
- `app/Controllers/Api/SseApiController.php`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`

## Vérification

Tests unitaires `TerrainUploadedImageTest` et `SseFacePhotoRoutingAssetTest`. Déploiement portail immédiat ; pack jeu (extension 1.17.8) pour ne plus tenter le mauvais canal.

## Statut

corrigé (portail) — pack jeu à reconstruire pour le filtre côté poste
