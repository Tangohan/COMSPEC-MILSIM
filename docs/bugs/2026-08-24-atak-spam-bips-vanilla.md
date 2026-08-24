# Overlays ATAK — spam des bips vanilla Arma

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Coupures / gel / écran endommagé / brouillage du terminal.

## Symptôme

En jeu, enchaînement de bips « Arma 3 » (échec d’objet, checkpoint, cible) à chaque micro-coupure, comme un spam d’erreurs.

## Cause

Les états ATAK jouaient les sons vanilla (`FD_CP_Not_Clear_F`, `AddItemFailed`, `beep_target.wss`, alarme). Le hub les rejouait chaque seconde, et le brouilleur bascule hors-ligne toutes les 2–7 s.

## Correctif

- Canal ATAK uniquement (`COMSPEC_ATAK_Disconnect` / démarrage / vibration).
- Anti-spam 8 s par famille de son.
- Plus de son sur le rafraîchissement 1 Hz du hub.
- Micro-coupures brouilleur : pas de bip de rétablissement.

## Fichiers touchés

- `connect/functions/fn_playAtakEnhancedSound.sqf`
- `connect/functions/fn_playRoleplaySound.sqf`
- `connect/functions/fn_updateAtakEnhancedRoleplay.sqf`
- `connect/functions/fn_refreshLinkState.sqf`
- `connect/functions/fn_checkAtakDamage.sqf`
- `connect/functions/fn_applyZeusAtakEffect.sqf`
- `connect/functions/fn_repairAtak.sqf`

## Vérification

Relancer Arma (connect 1.4.62). Entrer dans une zone brouillée / couper la liaison : un son terminal, pas les bips vanilla en boucle.

## Statut

corrigé
