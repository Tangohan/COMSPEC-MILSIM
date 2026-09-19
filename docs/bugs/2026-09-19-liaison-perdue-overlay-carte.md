# Overlay Liaison perdue sur la carte ATAK

## Contexte

Pendant une coupure simulée, un panneau « Liaison perdue / Reconnexion dans … s » recouvrait la carte du téléphone et bloquait les clics.

## Symptôme

Impossible d’utiliser la carte (marquer, déplacer, lire) tant que le panneau est affiché.

## Cause

L’overlay terminal traitait la coupure comme un écran plein cadre, alors que les barres de signal de la barre d’état suffisent.

## Correctif

Plus de panneau sur la carte. Les barres de signal (à côté de l’heure et de la batterie) passent au rouge, avec une petite icône et une infobulle de reconnexion.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateDeviceOverlay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateAtakLinkChrome.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_injectRoleplayEffectsInBrowser.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateLinkStrip.sqf`

## Vérification

Quitter Arma. Recharger Overwatch 1.5.94 et Athena 1.0.151. Déclencher une coupure : la carte reste utilisable, les barres de signal en haut à droite deviennent rouges.

## Statut

corrigé
