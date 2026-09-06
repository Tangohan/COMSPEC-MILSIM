# Connexion DEV — écran HTML noir dans IceMan

## Contexte

Pack `@COMSPECOverwatch_DEV`, tuile Athena → vue HTML de connexion.

## Symptôme

À l’ouverture de la tuile, un rectangle noir recouvre la droite de la carte. Aucun formulaire. Barre IceMan (Retour / Enter / Live Feed) encore visible en bas.

## Cause

Le navigateur intégré d’Arma ne s’affiche pas s’il est créé *dans* un groupe d’application IceMan : page blanche/noire, et le rectangle ignore parfois le cadre du panneau. Les boutons natifs Athena étaient masqués en attendant, d’où un panneau vide.

## Correctif

- Navigateur créé sur l’écran IceMan (pas dans le groupe de l’app).
- Page chargée en contenu local (pas seulement un chemin de fichier).
- La vue prend toute la fenêtre du téléphone (carte + panneau).
- Les boutons Athena restent disponibles si l’écran HTML ne part pas.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginShow.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginBind.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginFit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/dev_atak/functions/fn_htmlLoginContentRect.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_applyHomeLayout.sqf`

## Vérification

Rebuild `build_mod_dev.bat`. Tuile Athena : formulaire de connexion visible, à la taille de l’écran du téléphone. Retour ramène à la grille.

## Statut

corrigé
