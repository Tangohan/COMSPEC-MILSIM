# Connexion Athena refusée hors SESSION_EXPIRED

## Contexte

Téléphone COMSPEC ATAK, bouton Connexion Athena. Restauration de session via l’extension, puis Steam si besoin.

## Symptôme

Si la restauration échouait pour une autre raison qu’une session expirée (réponse inattendue, jeton absent formulé autrement), le téléphone n’essayait pas l’association Steam et restait hors liaison.

## Cause

Le SQF ne relançait Steam que lorsque le texte d’erreur contenait `SESSION_EXPIRED`.

## Correctif

Toute restauration en échec, sauf Steam non lié, serveur injoignable ou version refusée, enchaîne sur l’association Steam. La position et la messagerie ne démarrent qu’après passage en mode Athena.

## Fichiers touchés

- `mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_networkConnectAthena.sqf`

## Vérification

Syntaxe SQF relue. Compte Steam non lié : message dédié, pas de mode Athena. Session déjà enregistrée : restauration inchangée.

## Statut

corrigé
