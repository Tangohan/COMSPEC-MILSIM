# PANIC et opérateurs à terre absents du poste

## Contexte

Téléphone ATAK en mission, liaison Athena active (vibration et position OK). L’opérateur appuie sur PANIC. Un KO ACE ou une mort devrait aussi remonter.

## Symptôme

Le téléphone affichait bien le signal (liste PANIC / Eagle Down, indicatif ou nom de profil, grille). Le journal de liaison du poste ne montrait rien : ni opérateur à terre, ni inconscient, ni hors combat.

## Cause

- Le pont téléphone → poste n’envoyait le signal que si l’unité d’émission était exactement le joueur local. Le téléphone utilise parfois une autre unité (tablette). Le signal restait sur l’appareil, sans partir.
- Après une liaison Athena tardive (connexion après le lancement), les alertes médicales n’étaient jamais réarmées : le suivi s’arrêtait au premier échec de session.
- Un contrôle interne liait « spawn stabilisé » à cet armement : même une fois en liaison, un KO pouvait rester bloqué.

## Correctif

- Un signal d’urgence (PANIC / à terre) part dès qu’il est émis depuis cet ordinateur, et n’est plus soumis au réglage optionnel des alertes téléphone.
- La connexion en cours de mission réarme le suivi médical et les boucles vers le poste.
- L’inconscience et la mort sont de nouveau évaluées dès que le joueur est réellement en mission.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanAlert.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_isPlayerSpawnStable.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sendTacticalAlert.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportMedicalAlert.sqf`

## Vérification

Tests d’assets. Pack Overwatch 1.5.9. En jeu : liaison active → PANIC → ligne au poste. KO ACE → assistance médicale au poste.

## Statut

corrigé
