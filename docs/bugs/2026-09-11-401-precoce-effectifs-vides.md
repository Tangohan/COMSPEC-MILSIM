# 401 précoce au boot : Tx coupées, effectifs vides malgré Appairer OK

**Date :** 2026-09-11  
**Statut :** corrigé

## Contexte

Pack joué **1.5.23** / liaison **2.0.19**. Journal : code accepté, « Connexion établie — TA1 », marqueur TA1, mais « Aucun contact en liaison » et en jeu `unauthorized` en rafale sur fiche opérateur / chat.

## Symptôme

- Portail : liaison OK + marqueur, effectifs vides.
- Jeu : « Liaison réussie » puis Tx `unauthorized` ; « Session refusée — transmissions arrêtées ».
- Handshake annonce « Session Athena prête » puis aussitôt « Session Athena absente ».

## Cause

1. Compte restauré en **READY** avec erreur **C2_*** → démarrage Tx trop tôt → 401.
2. `AuthInvalidated` freinait **10 min** et laissait l’état C2 pourri.
3. Après Appairer réussi, la DLL ne remettait pas READY propre ; les boucles déjà stoppées ne redémarraient pas.

## Correctif

- Tx seulement si READY **et** canal C2 OK (`canStartSync`).
- Après Appairer / Entrer : `reopenTransmitChannel` (clear backoff, relance boucles).
- Redeem / LinkBySteam : `SetGameAuth(READY)` après client-init OK.
- AuthInvalidated : pause 45 s, ignore pendant handshake quiet.

## Vérification

Rebuild **1.5.26** + liaison **2.0.22**. Quitter Arma. Nouveau code Appairer. Position doit apparaître dans effectifs sous ~30 s. Pied journal : plus de rafale unauthorized après liaison.
