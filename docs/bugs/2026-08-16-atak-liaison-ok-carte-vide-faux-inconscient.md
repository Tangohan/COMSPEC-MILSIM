# ATAK — liaison OK mais carte vide + faux « Inconscient »

## Contexte

Session vierge : ATAK web + Overwatch in-game. Journal : CONNEXION puis ACCÈS (« Liaison en jeu réussie — Steam reconnu ») pour N-10. Carte Altis sans marqueur BFT. Panneau Assistances → Détections automatiques : **N-10 Inconscient · 099153** (À secourir). Mod journalisé **Overwatch 1.4.14**.

## Symptôme

- Liaison Athena établie.
- Aucun contact sur la carte / roster effectifs.
- Fausse détection médicale « Inconscient » alors que le joueur est opérationnel.

## Cause

Deux mécanismes distincts se cumulent :

1. **BFT bloqué sans terminal reconnu** — `fn_updatePosition` exige `hasTerminal`. Le réglage CBA `comspec_overwatch_terminal_mode` (slot ItemAndroid / inventaire ItemAndroidMisc / les deux) **n’était pas appliqué** : seule la classe `ItemAndroid` (ou custom) était testée. Un loadout avec **ItemAndroidMisc** (objet cTab porté) → pas de POST position → carte vide, alors que le handshake Steam fonctionne sans terminal.

2. **Faux KO ACE au boot** — `lifeState INCAPACITATED` / spike ACE au spawn pouvait remonter `health=unconscious` et déclencher `reportMedicalAlert` (grille Arma `099153`) sans BFT réel. Les alertes médicales n’étaient pas alignées sur la même garde « terminal ».

## Correctif

- `fn_hasTerminal` : respecte `terminal_mode` (ItemAndroid slot + ItemAndroidMisc inventaire).
- Santé forcée `stable` tant que `!MedicalAlertsArmed` ; confirmation multi-ticks avant KO transmis.
- `fn_getMedicalState` : ne plus traiter `INCAPACITATED` seul comme KO.
- `fn_checkMedicalAlerts` : confirmation + même garde terminal que la position.
- Premier `UpdatePosition` immédiat au démarrage des boucles de sync.
- Carte web : parse des grilles Arma compactes si `pos_x/y` absents.
- Version connect **1.4.15**.

## Fichiers touchés

- `mod/UptoDate/Sources/.../connect/functions/fn_hasTerminal.sqf`
- `fn_updatePosition.sqf`, `fn_getMedicalState.sqf`, `fn_checkMedicalAlerts.sqf`
- `fn_startSyncLoops.sqf`, `fn_onPlayerRespawn.sqf`
- `connect/config.cpp` (1.4.15)
- `public/assets/js/comspec-operational-map.js`

## Vérification

1. Rebuild PBO `comspec_overwatch_connect` (1.4.15) + déployer assets JS Athena.
2. Loadout avec **ItemAndroid** ou **ItemAndroidMisc** selon le mode CBA.
3. Session vierge : après ACCÈS, marqueur N-10 sur la carte sous ~quelques secondes ; pas d’alerte Inconscient si le joueur est debout.
4. Sans terminal + « Exiger un terminal » activé : toujours pas de BFT (attendu) — et plus de faux KO médical.

## Statut

corrigé (sources) — rebuild / déploiement à faire
