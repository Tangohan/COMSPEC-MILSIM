# Bug — photo overlay caméra envoyée depuis le mauvais point de vue

## Contexte

26/08/2026. Un opérateur (indicatif NewPI, groupe N-10) ouvre une vue caméra plein écran ATAK (overlay « Press Espace Exit Camera », réticule, nom « Nox » au centre, vision nocturne intérieure : cages, casiers). Il prend une photo. Le poste affiche l’entrée dans **Photos reçues**.

## Symptôme

L’image reçue n’est **pas** la pièce intérieure. C’est une vue nocturne **extérieure**, plus loin (bâtiment, poteau, collines). L’opérateur photographiait bien l’intérieur.

## Cause

La vue plein écran téléphone (`BCE_PhoneCAM_View`) créait une caméra scriptée rendue vers une **texture** (`rttN`), puis appelait `switchCamera` sur cet objet. La commande `screenshot` et l’extension de cliché enregistrent le **framebuffer de la scène principale** — en pratique la vue du soldat (dehors) — pas la caméra overlay.

La grille / position envoyées avec la photo venaient aussi du joueur, pas de la caméra regardée.

Même mécanisme pour un flux casque ou tourelle affiché en incrustation (`rendertarget9`) : l’image dans l’ATAK n’est pas celle que le moteur cliche.

## Correctif

- Caméra overlay rendue en **vue scène** (plus de `rttN`), comme le casque plein écran.
- Avant le cliché : bascule sur la caméra réellement regardée, une courte pause, capture, éventuellement restauration du flux ATAK.
- Position / grille prises sur cette caméra.
- La touche photo souris fonctionne aussi en vue casque plein écran.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_FullScreenCamera.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_ATAK_TakePicture.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.51)
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getActiveCaptureCam.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_promoteCaptureCam.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_restoreCaptureCam.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.85)

## Vérification

1. Rebuild pack Overwatch (`connect` 1.4.85 + `atak_athena` 1.0.51), quitter Arma, recopier.
2. Ouvrir la caméra overlay sur une vue intérieure (casque d’un autre opérateur ou pièce).
3. Prendre une photo. **Photos reçues** doit montrer **cette** pièce, pas le paysage derrière le soldat.
4. Vérifier une photo « normale » (sans overlay) : toujours la vue de l’opérateur.

Non vérifié en jeu dans cette session (pas de client Arma ici).

## Statut

`corrigé à vérifier en jeu` — pack Overwatch à reconstruire. Branche `split/overwatch-liaison-1.4.84` (PR 226).
