# Photos ATAK — gel à cause d’un second cliché PNG

## Contexte

Le pack SOAR remonte les photos ATAK vers Discord (bot « Secur_Device ») **sans**
figer le jeu. Athena, sur le même cliché BCE, gelait encore le client.

## Symptôme

Prise de photo depuis ATAK : hitch d’une fraction de seconde à plusieurs
secondes (pire en 4K), alors que le même JPEG partait vers Discord sans à-coup.

## Cause

Ce n’est pas Athena ni un PBO « photos Discord ». Le pack SOAR utilise :

1. `Arma_ScreenShot_Extension` — JPEG D3D, dossier `Screenshot` du mod
2. `DiscordMessageAPI` — envoi HTTP **asynchrone** (multipart), hors thread jeu

Athena recevait déjà ce JPEG (IceMan / BCE), puis `fn_captureReconImage`
relançait un `screenshot "….png"` **synchrone** sur le thread jeu « au cas où »
le dossier JPEG serait mort (`srcdir_missing`). C’est ce second cliché qui
fige, pas l’upload vers le portail (déjà en file côté DLL).

## Correctif

- Pont IceMan : si le fichier est un `.jpg` / `.jpeg`, `skipArmaShot=true`.
- Notification du JPEG tel quel ; un seul report différé (~0,45 s) si le
  fichier n’est pas encore sur disque ; **un** PNG de repli seulement si le
  JPEG reste introuvable, hors de la frame du clic.

Les aperçus casque / drone (pas de fichier JPEG) continuent d’utiliser
`screenshot` PNG — limite moteur Arma.

## Fichiers touchés

- `connect/functions/fn_captureReconImage.sqf` (1.4.49)
- `atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf` (1.0.42)

## Vérification

1. Rebuild PBO `connect` + `atak_athena`, quitter Arma, recopier le Workshop.
2. Boot : journal `connect v1.4.49`.
3. Photo ATAK (app Photos / IceMan) : **pas** de gel PNG au clic ; photo
   visible dans l’onglet Photos Athena.
4. Si le JPEG BCE est vraiment absent : un hitch PNG ~0,6 s plus tard, pas au
   clic.
5. Discord SOAR inchangé (hors Overwatch).

## Statut

`corrigé à vérifier en jeu`
