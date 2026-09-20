# ATAK — Relais AT absent du chevron

Date : 2026-09-20  
Statut : corrigé (Athena 1.0.159)

## Contexte

Demande d’une application **Relais AT** dans le tiroir (mât le plus proche : position, débit, fiabilité, identité, adresse, passerelle, certificat, places, puissance), à la place de Wave Relay.

## Symptôme

Le chevron montre Message, P2P, Missions en première ligne. Relais AT n’apparaît pas. Il fallait scroller tout en bas, et encore : l’entrée était souvent reprise par Wave Relay.

## Cause

Relais AT était accroché à Wave Relay, tout en bas du menu. Un autre pack recharge Wave Relay après coup et remet cette tuile en fin de liste, avec un autre écran.

## Correctif

Relais AT est une application à part, en première ligne (à côté de P2P). Elle ouvre la fiche du mât le plus proche. Wave Relay n’est plus doublonnée dans le chevron.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_filterDrawerApps.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_openRelay.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_layoutAppDrawer.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/XEH_postInitClient.sqf`

## Vérification

Quitter Arma complètement. Recharger Athena 1.0.159. Chevron, première ligne : Message, P2P, Relais AT. Ouvrir Relais AT : fiche du mât, ou « Aucun mât à proximité » s’il n’y en a pas.

## Statut

corrigé
