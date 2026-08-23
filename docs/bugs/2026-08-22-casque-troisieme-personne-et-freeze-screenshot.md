# Bug — aperçu casque en 3e personne et gel à chaque cliché

## Contexte

Photos « Caméra casque » dans ATAK (action ACE *Envoyer aperçu casque* ou demande TOC). Journal session du 22/08 : `NotifyNewPhoto` immédiat, `PhotoUpload file_empty` puis upload ~1 min plus tard ; gel du jeu à chaque capture.

## Symptôme

- L’image casque montre le soldat **de derrière** (vue 3e personne), pas le regard casque.
- Le jeu **se fige** le temps du cliché (plus long en 4K).
- Question : faut-il un fichier image sur le PC pour que ça marche ?

## Cause

1. **3e personne** — la commande Arma `screenshot` capture **la vue affichée**. Aucun `switchCamera` avant le cliché : si le joueur est en externe, Athena reçoit une chase-cam. De plus, `screenshot` dans la **même frame** qu’un changement de caméra cliche encore l’ancienne vue.
2. **Gel** — `screenshot "….png"` encode un PNG **plein écran, synchrone** sur le thread jeu. Pas d’API SQF « capture mémoire ». En 4K le hitch est violent. Un second cliché (chemin HD : `screenshot` puis encore un dans `captureReconImage`) doublait le gel.
3. **Fichier disque** — Arma n’expose pas le framebuffer. Le PNG est **obligatoirement** écrit dans `Documents\Arma 3 […]\Screenshots`. L’extension lit ce fichier puis l’envoie. `file_empty` = notif avant la fin du flush disque.

## Correctif

- Snap manuel / demande TOC casque ou drone : fermer le menu ACE, masquer le HUD, `switchCamera "INTERNAL"` (ou tourelle UAV), attendre ~0,16 s, **un** `screenshot`, restaurer la vue, attendre ~0,55 s avant la notif (moins de `file_empty`).
- Aperçus **périodiques** : pas de vol de caméra (`alignDevicePov=false`) pour ne pas arracher le joueur toutes les 30 s.
- Chemin HD : plus de double `screenshot`.
- Le PNG local reste **nécessaire** (limite moteur). On ne le supprime pas après upload.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.19)
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onHelmetMediaRequest.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_snapshotVideoFeed.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp` (1.0.27)

## Vérification

1. Rebuild PBO `connect` + `atak_athena`, quitter Arma, recopier le Workshop.
2. Boot : journal `connect v1.4.19`.
3. Jouer en 3e personne → *Envoyer aperçu casque* : flash 1re personne, **un** gel, photo regard avant (pas le dos du soldat).
4. Photo tablette : vue actuelle inchangée.
5. Qualité d’affichage HDR ≥ Moyen, sinon `screenshot` n’écrit rien.

## Statut

`corrigé à vérifier en jeu`
