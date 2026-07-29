# Liaison bloquée « Hors liaison » après fin de brouillage

## Contexte

Brouillage Zeus, coupure réseau roleplay ou zone jammer : une fois l’effet levé, le panneau **État ATAK** peut rester afficher **Hors liaison** avec 0 paquet envoyé, alors qu’Athena est prêt.

## Symptôme

- Fin du brouillage (Zeus réparer, timer écoulé, sortie de zone) mais **Hors liaison** persistant.
- **0 envoyés / 0 reçus** dans la fenêtre paquets, pas de resync web.
- Bouton **Actualiser** ou reconnexion extension sans effet tant que `COMSPEC_LinkState` reste `offline`.

## Cause

1. `fn_isNetworkDisconnected` expirait la coupure simulée **sans** remettre `COMSPEC_LinkState` à `linked` (contrairement à `fn_simulateNetworkDisconnect`).
2. Aucun recalcul central après levée du brouillage : l’état affiché restait celui du moment de la coupure.
3. Tant que la liaison restait `offline`, `fn_updatePosition` ne renvoyait pas de position → boucle bloquée.

## Correctif

- Nouvelle fonction **`fn_refreshLinkState`** : recalcule linked / degraded / offline selon coupure réseau, zone, terminal et Athena prêt.
- Appelée à l’expiration d’une coupure, à la fin du brouillage Zeus, à la réparation, à la sortie de zone roleplay, dans **État ATAK** (Actualiser) et après autorisation TX dans `fn_canTransmit`.

## Fichiers touchés

- `mod/.../connect/functions/fn_refreshLinkState.sqf` (nouveau)
- `mod/.../connect/functions/fn_isNetworkDisconnected.sqf`
- `mod/.../connect/functions/fn_getNetworkDisconnectInfo.sqf`
- `mod/.../connect/functions/fn_canTransmit.sqf`
- `mod/.../connect/functions/fn_applyZeusAtakEffect.sqf`
- `mod/.../connect/functions/fn_applyZoneEffects.sqf`
- `mod/.../connect/functions/fn_simulateNetworkDisconnect.sqf`
- `mod/.../atak_athena/functions/fn_athena_updateStatus.sqf`
- `mod/.../connect/config.cpp`

## Vérification

1. Rebuild PBO `connect` + `atak_athena`.
2. Zeus → Brouiller un opérateur → Réparer (ou attendre la fin).
3. Attendu : **En liaison** ou **Liaison dégradée**, paquets qui repartent, visible web.
4. Si zone jammer Eden encore active : dégradé ou hors liaison **normal** tant que le joueur est dedans.

## Statut

Corrigé (mod 1.4.11+)
