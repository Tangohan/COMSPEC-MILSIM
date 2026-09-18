# Overwatch — PreInit / PostInit relancés en boucle

**Date :** 2026-09-18  
**Statut :** corrigé

## Contexte

Recocher Overwatch dans les paramètres d’addons, ou rouvrir la gestion du mod, relisait toute la chaîne de démarrage sans quitter la mission. Journal du 17/09 : trois PreInit / PostInit dans le même fichier, sans nouvelle mission.

## Symptôme

Le jeu s’arrête un peu après un geste d’écran (pause, carte), pas pile au clic. Recocher Overwatch : arrêt souvent immédiat. Sans Overwatch : la position et les messages peuvent continuer, pas les ordres.

## Cause

Chaque relance réinstallait les menus, la liaison et les alertes par-dessus les précédents. Le nettoyage des menus ACE ne retirait pas vraiment les anciennes entrées (mauvais chemin, pas d’héritage). Après plusieurs passages, tout s’empilait.

## Correctif

- Un seul PreInit et un seul PostInit par mission.
- Les événements de réglages et le handshake ne s’enregistrent qu’une fois.
- Le retrait des menus ACE utilise le chemin complet et l’héritage.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInitClient.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_aceAddSelfAction.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_aceSweepPlayerSelfActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_preInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

## Vérification

Journal : une seule ligne `PreInit OK` et une seule `PostInit client` par entrée en mission. Recocher Overwatch ne doit pas réécrire toute la chaîne. Overwatch 1.5.82.

## Statut

corrigé
