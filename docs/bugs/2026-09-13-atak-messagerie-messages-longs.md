# Messagerie ATAK — messages longs sans retour à la ligne

**Date :** 2026-09-13  
**Statut :** corrigé

## Contexte

Dans le téléphone ATAK, l’app Messagerie affichait canaux et fil sur le même écran. Un message très long (sans espace) restait sur une seule rangée.

## Symptôme

Le texte long ne passait pas à la ligne. L’opérateur ne voyait qu’un extrait coupé. Un message exceptionnellement long pouvait aussi faire échouer Arma (mémoire insuffisante à l’affichage).

## Cause

La liste des messages n’affichait qu’une ligne par entrée, sans retour visuel, et l’envoi n’avait pas de limite de longueur.

## Correctif

- Ouverture sur la liste des canaux, avec le nombre de messages non lus.
- Clic sur un canal : fil complet, texte qui passe à la ligne, date, couleurs auteur / canal.
- Envoi limité à 100 caractères.
- Section Création en bas de la liste des canaux.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/comms_page.hpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateComms.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsSelectChannel.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsBack.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_commsSend.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_tabletChatSend.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_pollChatMessages.sqf`

## Vérification

1. Relancer Arma, ouvrir le téléphone → Messagerie : liste des canaux, pas le fil.
2. Envoyer un message de 100 caractères : il passe à la ligne, date visible, couleur d’auteur.
3. Un second canal reçoit un message : la case de non-lus s’incrémente. L’ouvrir remet le compteur à zéro.
4. Section Création en bas : nom + Créer.

## Statut

corrigé — pack **1.5.70** / Athena **1.0.117**
