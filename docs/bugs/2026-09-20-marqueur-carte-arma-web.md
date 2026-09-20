# Marqueur carte Arma absent du poste

## Contexte

20 septembre 2026. Un opérateur pose un point sur la carte d’Arma 3 (canal global, près d’Athira). La carte du poste (Overwatch Beta, Direct) montre déjà les libellés de mission (TA1, Z Ent, Bikini Blast) mais pas ce nouveau point.

## Symptôme

Le point est visible en jeu (infobulle : posé par le joueur, canal global, heure). Il n’apparaît pas sur Overwatch Beta, même après plusieurs secondes.

## Cause

Les points posés à la main sur la carte Arma n’étaient pas renvoyés de façon fiable :

1. Le nom interne contient un dièse ; l’envoi vers le poste pouvait échouer, et le jeu considérait ensuite le point comme déjà traité.
2. La confirmation du point a souvent lieu à la fermeture de la carte ; rien n’était prévu à ce moment-là.
3. Un premier essai trop tôt (position encore nulle) bloquait les essais suivants.

## Correctif

- À la fermeture de la carte en jeu, les points posés à la main sont renvoyés au poste (deux essais rapides).
- Un rattrapage toutes les huit secondes si le premier envoi a manqué.
- Un essai raté n’empêche plus les suivants.
- Un point sans nom s’affiche au poste comme « Repère ».

Pack : Overwatch **1.6.5** · Athena **1.0.160**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncUserMapMarkers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_resolveMarkerEhName.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_resyncAllMapMarkers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `app/Support/ArmaMarkerLabel.php`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.6.5 et Athena 1.0.160.
2. Ouvrir la carte en jeu, poser un point (canal global) près d’Athira, fermer la carte.
3. Sur Overwatch Beta (Direct), le même point apparaît en quelques secondes, libellé « Repère » s’il n’a pas de nom.

## Statut

corrigé (Overwatch 1.6.5)
