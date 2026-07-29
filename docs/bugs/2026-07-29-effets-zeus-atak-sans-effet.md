# Effets Zeus ATAK (écran cassé, brouillage…) sans effet visible

## Contexte

Actions Zeus **COMSPEC ATAK** (ZEN / ACE) : éteindre, casser l’écran, brouiller, capturer, réparer.

## Symptôme

Le Zeus choisit une action sur un joueur ; côté cible, l’ATAK reste normal (pas d’overlay, pas de coupure liaison, pas de message).

## Cause

1. **Multijoueur** : `remoteExecCall` vers le client cible sans **`CfgRemoteExec`** → la fonction n’était pas exécutée sur la machine du joueur.
2. **UI** : l’overlay « écran cassé / éteint » ne existait que sur l’ancien **Hub** (`COMSPEC_Hub_Display`), pas sur **cTab / ATAK Enhanced** (`cTab_Android_dlg`) utilisé en jeu.
3. **Brouillage Zeus** : `isNetworkDisconnected` exigeait le roleplay admin activé avant de lire `COMSPEC_NetworkDisconnectState` → le jam Zeus ne bloquait pas la transmission.

## Correctif

- `CfgRemoteExec` + relais serveur `relayZeusAtakEffect` (Zeus → serveur → client cible).
- Nouvel overlay `updateDeviceOverlay` sur cTab et Hub (PFH 1 s).
- Brouillage / coupure forcée lue même sans roleplay portail.
- Rafraîchissement UI + effets visuels à la fin de chaque action Zeus.

## Fichiers touchés

- `connect/config.cpp` (1.4.9)
- `connect/functions/fn_applyZeusAtakEffect.sqf`
- `connect/functions/fn_relayZeusAtakEffect.sqf` (nouveau)
- `connect/functions/fn_updateDeviceOverlay.sqf` (nouveau)
- `connect/functions/fn_isNetworkDisconnected.sqf`
- `connect/functions/fn_zeusShowPlayerAtak.sqf`
- `connect/functions/fn_registerZenAtakPlayerActions.sqf`
- `connect/functions/fn_captureEnemyAtak.sqf`
- `connect/functions/fn_startSyncLoops.sqf`

## Vérification

1. Rebuild mod **1.4.9**, relancer Arma MP (Zeus + joueur cible).
2. Zeus → joueur → **Casser l’écran** : overlay « ÉCRAN ENDOMMAGÉ » sur la tablette + hint + pas d’envoi position complète.
3. **Brouiller 45 s** : overlay « LIAISON ATAK PERDUE », reprise auto après délai.
4. **Réparer / rétablir** : overlay disparaît, ATAK normal.

## Statut

Corrigé (mod 1.4.9 — rebuild requis).
