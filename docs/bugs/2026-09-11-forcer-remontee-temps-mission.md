# Temps de mission : pas de remontée forcée

## Contexte

11 septembre 2026. Fiche opérateur : « Dernière remontée » figée (ex. 06/09/2026) alors que la liaison Athena fonctionne. Resynch renvoyait position et carte, pas le temps de mission.

## Symptôme

Le cumul « Temps de jeu en mission » n’avançait pas sans attendre l’intervalle automatique (~5 min), et Resynch ne forçait pas l’envoi.

## Cause

Le tracker n’exposait pas de remontée manuelle ; `forceSyncData` n’appelait pas `ReportPlaytime`.

## Correctif

- `forcePlaytimeReport` : envoi immédiat du cumul (min. 1 s pour faire avancer la date).
- Branché sur Resynch, validation Zeus du groupe, bouton Athena « Remonter le temps ».
- Aide sur la fiche portail pour le titulaire du dossier.

## Fichiers touchés

- `fn_forcePlaytimeReport.sqf`, `fn_playtimeTracker.sqf`, `fn_forceSyncData.sqf`, `fn_fillZeusGroupId.sqf`
- `athena_page.hpp`, `fn_athena_applyHomeLayout.sqf`
- `views/personnel/file.php`

## Vérification

Compte lié → Remonter le temps → recharger la fiche : date de dernière remontée à jour.

## Statut

corrigé (pack 1.5.28 / Athena 1.0.84)
