# Arma — Variable indéfinie `_detail` dans `fn_logAtakEvent`

## Contexte

Journalisation d’événements ATAK (état, Zeus, médical…).

## Symptôme

> Error Variable indéfinie dans une expression: `_detail`  
> `fn_logAtakEvent.sqf`, ligne 22

## Cause

`params` définit `["_detail", nil]`. Les appels courants ne passent que 4 arguments → `_detail` reste nil.  
L’expression `[_level, _channel, _message, _detail]` évalue `_detail` et plante en SQF.

## Correctif

Brancher sur `isNil "_detail"` avant l’appel à `fnc_log` (3 ou 4 args).

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_logAtakEvent.sqf`

## Vérification

- [x] Rebuild `connect.pbo` + deploy Workshop (SHA256 sync 4 racines, 2026-08-06)
- [ ] Plus d’erreur `_detail` en jeu

## Statut

corrigé — PBO déployé ; confirmation in-game à faire
