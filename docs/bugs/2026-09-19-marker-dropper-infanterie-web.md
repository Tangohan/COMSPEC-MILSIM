# Marker Dropper : losange infanterie absent du poste

## Contexte

19 septembre 2026. Téléphone ATAK, outil Marker Dropper. Un losange d’infanterie adverse (rouge) est posé près du parcours / des zones de tir. La carte du poste (Overwatch Beta, session en direct) montre déjà les libellés de mission (Hangar, TA1, Parcours Bleu) mais pas ce losange.

## Symptôme

Le symbole est visible sur le téléphone, pas sur la carte du poste.

## Cause

1. IceMan verrouille la fonction de pose : Athena croyait l’avoir enveloppée, mais l’envoi immédiat après pose ne partait pas.
2. Le type du losange (infanterie adverse) est appliqué seulement en local sur le téléphone. Le premier envoi partait trop tôt, sans le bon symbole.
3. Une coupure de liaison simulée bloquait encore l’envoi, alors que la carte du téléphone restait utilisable.

## Correctif

- Dès qu’un symbole Widget / Dropper est posé, il est envoyé au poste même si la liaison est momentanément dégradée.
- Un clic ou double-clic sur la carte du téléphone relance l’envoi des symboles tout autour.
- Le losange réellement dessiné sur la carte du téléphone sert de référence si la liste interne n’a pas le bon type.

Pack : Overwatch **1.5.95** · Athena **1.0.152**. Relancer Arma complètement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncMapMarker.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isSyncableMapMarker.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_syncNearbyMapMarkers.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_hasTerminal.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_installReachMap.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeCtabMarkers.sqf`
- `public/assets/js/arma-map-markers.js`

## Vérification

1. Quitter Arma. Recharger Overwatch 1.5.95 et Athena 1.0.152.
2. Ouvrir le téléphone, Marker Dropper, poser un losange d’infanterie adverse.
3. Sur Overwatch Beta (Direct), le losange rouge apparaît au même endroit en quelques secondes.

## Statut

corrigé
