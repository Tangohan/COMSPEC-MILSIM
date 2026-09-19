# Dépannage liaison : pas d’essais réels

**Date :** 2026-09-19  
**Statut :** corrigé (Overwatch 1.5.88)

## Contexte

Le dépannage liaison rallumait chaque fonction 55 secondes, avec un bandeau à l’écran. L’opérateur voyait « Messages » ou « Formes du poste » sans qu’aucun essai ne parte vers le poste.

## Symptôme

Pendant le dépannage : débit à 0, aucun message, aucun repère, aucune photo au poste. Impossible de savoir si l’envoi fonctionne, seulement si le jeu reste ouvert.

## Cause

Les étapes se contentaient d’autoriser les boucles d’écoute. Rien n’appelait l’envoi d’un message, d’un repère ou d’une photo.

## Correctif

Trois étapes actives, juste après la position :

1. **Message de test** — un message part vers le poste.
2. **Repère de test** — un point jaune « Dépannage » est posé et transmis.
3. **Photo et transmission** — une capture est prise et envoyée au poste.

Le bandeau affiche si l’essai est parti. Un second essai a lieu quelques secondes plus tard si la liaison n’était pas encore prête.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagIsolateCatalog.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagIsolateProbe.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagIsolateStart.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_diagIsolateHud.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.5.88)

## Vérification

Contrôles automatiques : catalogue, envois, version 1.5.88. En jeu : lancer le dépannage avec le téléphone ; aux trois étapes d’essai, le poste doit recevoir le message, le repère et la photo. Relancer Arma complètement (Overwatch 1.5.88).

## Statut

Corrigé.
