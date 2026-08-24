# ACE — couper le téléphone GPS sur une personne

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

La géolocalisation téléphone se coupait seulement via Zeus / l’éditeur. En mission, un opérateur n’avait pas d’action ACE pour mettre le GPS hors service sur la personne.

## Symptôme

Impossible de désactiver le signal téléphone d’un contact depuis le menu ACE, même à portée.

## Cause

Le suivi `COMSPEC_PhoneTrack` n’était branché que sur les menus Zeus / Eden, pas sur `ACE_MainActions`.

## Correctif

- Menu ACE sur la personne (à moins de 4 m, si un GPS téléphone est actif) : **Couper le téléphone GPS**.
- Menu ACE sur soi : **Couper mon téléphone GPS** si Zeus a activé le suivi sur le joueur.
- Barre de quelques secondes, puis le signal disparaît de la carte.

## Fichiers touchés

- `connect/functions/fn_aceDisablePhoneTrack.sqf`
- `connect/functions/fn_initACE.sqf`
- `connect/config.cpp` (1.4.60)

## Vérification

En jeu : activer un téléphone via Zeus, s’approcher, ACE sur la personne → Couper le téléphone GPS. Le contact ne remonte plus. Relancer Arma après le PBO.

## Statut

corrigé
