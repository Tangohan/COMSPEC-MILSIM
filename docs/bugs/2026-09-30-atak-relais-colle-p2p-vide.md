# ATAK — Relais AT collé + P2P vide

Date : 2026-09-30  
Statut : corrigé (Athena 1.0.168)

## Contexte

Téléphone ATAK en jeu. Tuiles Relais AT et P2P — Réseau local.

## Symptôme

1. Après avoir ouvert Relais AT une fois, ouvrir Message / Messagerie (ou une autre app) réaffiche le contenu Relais AT.
2. P2P — Réseau local affiche un écran vide (pas de contacts).

## Cause

1. BCE empile les sous-pages sans masquer les sœurs. Relais AT restait `ctrlShow true` ; le change d’app n’appelait pas toujours le masquage COMSPEC.
2. P2P ouvrait la classe `message` retirée du tiroir, ou masquait volontairement son propre groupe au lieu d’initialiser l’écran IceMan.

## Correctif

- Après chaque changement d’app : masquer Relais AT si ce n’est plus la page active.
- P2P passe par `ChangeTool` + init IceMan (`BCE_fnc_ATAK_message_Init`) sur `ATAK_Message`.
- Boucle Relais : à la sortie, cacher le groupe et appeler le masquage des pages.

## Fichiers touchés

- `atak_athena/functions/fn_athena_p2pOnOpened.sqf`
- `atak_athena/functions/fn_athena_messageHubOpenP2P.sqf`
- `atak_athena/functions/fn_athena_relayOnOpened.sqf`
- `atak_athena/functions/fn_athena_updateRelay.sqf`
- `atak_athena/functions/fn_athena_openAtakApp.sqf`
- `atak_athena/XEH_postInitClient.sqf`
- `atak_athena/config.cpp` (1.0.168)

## Vérification

Quitter Arma complètement. Rebuild Athena 1.0.168. Relais AT → Messagerie : plus de fiche Relais. P2P : contacts / fil visibles.

## Statut

corrigé
