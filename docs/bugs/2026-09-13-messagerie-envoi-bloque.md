# Messagerie — envoi bloqué + textes illisibles

## Contexte

App Messagerie ATAK. Journal : `linkState=linked`, `athenaReady=false`, puis canal poste rouvert / sync OK. Photos OK. Envoi message impossible.

## Symptôme

- Envoyer ne part pas (notification « Liaison Athena requise » ou silence).
- Fil / canaux : texte petit, peu contrasté.

## Cause

`fn_athena_commsSend` et `fn_sendIntel` exigeaient `COMSPEC_AthenaReady` seul, alors que `canStartSync` peut déjà être vrai (session prête + canal OK) sans ce drapeau — cas typique après reprise Appairer / Steam non lié.

## Correctif

- Porte d’envoi / création de canal alignée sur `canStartSync`.
- `reopenTransmitChannel` aligne `AthenaReady` quand le canal est utilisable.
- UI Messagerie : tailles et contrastes relevés.

## Fichiers

- `fn_athena_commsSend.sqf`, `fn_athena_commsCreateChannel.sqf`, `fn_athena_updateComms.sqf`, `comms_page.hpp`
- `fn_sendIntel.sqf`, `fn_reopenTransmitChannel.sqf`
- Overwatch 1.5.61 · Athena 1.0.111

## Statut

corrigé
