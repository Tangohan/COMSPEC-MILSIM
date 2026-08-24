# Pack sons ATAK — web et jeu

**Date :** 2026-08-24  
**Statut :** corrigé

## Contexte

Les alertes ATAK (carte web et terminal en mission) utilisaient un bip court agressif, le son « stalker », le roger radio et un signal médical unique. Les fichiers fournis devaient remplacer ces clips, avec un câblage distinct par usage.

## Symptôme

Le bip d’activité était désagréable. Le style « Ambiance tension » et le roger d’ordre n’étaient plus adaptés. Le signal médical ne se répétait pas. L’acceptation d’un ordre et l’envoi d’une fiche de renseignement n’avaient pas de son dédié. Sur le web, les alertes appareil appelaient un objet son inexistant (`AtakSounds`).

## Cause

Les `CfgSounds` et `atak-sounds.js` pointaient vers d’anciens OGG (`sound_1_stalker`, `roger_simple`, `atak_no_activyt_health`). La réception d’ordre Athena rejouait encore la vibration cTab. L’acceptation et le renseignement n’appelaient pas `playAtakNotification`. `atak-intel-view.js` utilisait le mauvais nom d’objet.

## Correctif

- Conversion des clips fournis en OGG (web + addon `connect`).
- Bip d’activité : `atak_beep.ogg`. Style tension / roger : carillon UI. Réception d’ordre : carillon mission. Acceptation et renseignement : carillon grave. Démarrage : clip respawn. Signal médical : panic Motorola, trois lectures.
- Événements `order_ack` / `intel` / `beep` côté SQF et JS.

## Fichiers touchés

- `public/assets/sounds/*.ogg`
- `public/assets/js/atak-sounds.js`, `atak-orders.js`, `atak-chat.js`, `atak-intel-view.js`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_playAtakNotification.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_showNotification.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_orderRespond.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_intelNoteSubmit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_onOrderReceived.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_taskRespond.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_soundAction.sqf`

## Vérification

- Fichiers OGG présents dans `public/assets/sounds` et `connect/sounds`, durées cohérentes (panic ~2,1 s × 3).
- `node --check` sur les JS ATAK touchés.
- Rebuild PBO `connect` + `atak_athena`.

## Statut

Corrigé.
