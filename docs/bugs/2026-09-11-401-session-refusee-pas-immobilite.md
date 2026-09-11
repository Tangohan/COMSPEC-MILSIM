# 401 précoces puis « Session refusée » — pas un kick d’immobilité

## Contexte

11 septembre 2026. Journal Overwatch 1.5.26 / liaison 2.0.22. L’opérateur voit des 401 puis « Session refusée par le poste — transmissions arrêtées », et se demande si rester immobile coupe la liaison.

## Symptôme

- Chat / manifeste de vol en 401 juste autour du handshake.
- Message « Session refusée… transmissions arrêtées ».
- Plus tard, certaines Tx (fiche) repartent.
- Suspicion d’un kick si on ne bouge pas.

## Cause

1. Des envois partent dès que l’état compte est READY, avant que le jeton jeu soit vraiment utilisable → 401.
2. La DLL confirme le 401 via client-init et envoie AuthInvalidated → coupe AthenaReady.
3. L’alerte « immobile » n’est qu’un avis de suivi (environ 3 min sans mouvement) : elle ne refuse pas la session.

## Correctif

- Quiet 20 s après READY ; gate chat/intel sur canal réellement ouvert.
- Grace DLL 25 s après READY avant AuthInvalidated.
- Tentative de rouverture automatique ~48 s après un refus temporaire.

## Vérification

Boot ≥ Overwatch 1.5.29 / liaison 2.0.24. Journal : plus de « Session refusée » juste après « Session Athena prête ». Rester immobile ne doit pas couper la liaison.

## Statut

corrigé (pack à recharger — quitter Arma complètement)
