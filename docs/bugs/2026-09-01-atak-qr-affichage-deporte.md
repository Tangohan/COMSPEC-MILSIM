# 2026-09-01 — QR Affichage déporté illisible

## Contexte
Sur la carte du poste, Affichage déporté propose d’ouvrir le module sur le téléphone en scannant un code.

## Symptôme
Le code à scanner était collé sur le dessin du téléphone, trop petit et trop contrasté pour l’appareil photo.

## Cause
Le visuel du téléphone ne laisse qu’une petite zone d’écran. Le code y était superposé, à une taille trop faible.

## Correctif
Le code s’affiche à part, en grand, sur un carré blanc avec une marge. Le téléphone reste un aperçu, plus un support de scan.

## Fichiers touchés
- `views/partials/atak_qr_phone_preview.php`
- `public/assets/css/atak-c2-shell.css`
- `public/assets/css/atak.css`
- `tests/Unit/AtakQrPhoneDetachAssetTest.php`

## Vérification
Le code occupe environ 260 px de côté, fond blanc. Contrôle visuel après rechargement du poste : Affichage déporté → Fenêtre détachée sur téléphone.

## Statut
Corrigé
