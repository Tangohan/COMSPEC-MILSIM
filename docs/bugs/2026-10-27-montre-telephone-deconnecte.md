# Montre : téléphone marqué déconnecté

## Contexte

Le joueur a le téléphone ouvert (carte, batterie) et la montre Garmin en objet divers dans le sac. L’écran Téléphone de la montre dit « Téléphone déconnecté » et « Bluetooth du téléphone éteint ».

## Symptôme

La montre s’allume (l’objet dans le sac est donc bien reconnu) mais refuse le téléphone visible à côté.

## Cause

La liaison exigeait le partage Bluetooth du téléphone (`COMSPEC_ATAK_Bt`, coupé tant qu’on ne l’a pas allumé dans l’app Bluetooth). Ce réglage sert aux échanges avec d’autres appareils, pas au lien entre sa montre et son téléphone. Le téléphone était bien utilisable.

## Correctif

La montre se considère reliée dès que le téléphone est porté et allumé. Le mode furtif coupe toujours la liaison. L’objet divers dans le sac était déjà accepté.

## Fichiers touchés

- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/garmin/fn_watch.sqf`

## Vérification

À faire en jeu après avoir quitté Arma complètement : téléphone allumé, montre dans le sac, écran Téléphone de la montre → « ATAK connecté » et la batterie.

## Statut

Corrigé dans les sources (2.9.2). Pas encore confirmé en jeu.
