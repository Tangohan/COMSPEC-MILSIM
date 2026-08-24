# Plan de mission — lots de cadrage manquants

## Contexte

Le cadrage reliait planification → ATAK → Arma → prévu/réel → AAR. La première livraison posait le plan web, pas la lecture in-game, ni la reprise d’effectif, ni le compte rendu à la clôture.

## Symptôme

- Le téléphone Athena n’affichait pas l’ordre (objectifs, LD, H).
- Sur la carte : présence et chronologie, pas le prévu vs réel (repères atteints).
- Clôture : tableau des effectifs figé, aucun compte rendu ouvert.
- Événement lié : le roster n’était pas repris.

## Cause

Lecture du plan absente côté Overwatch. Aucun rapprochement BFT / repères. `setStatus('closed')` n’ouvrait pas l’AAR existant. Import événement non branché.

## Correctif

- Overwatch : `GetMissionPlan` (lecture seule) + poll 30 s + onglet Ordre Athena.
- Snapshot live : repère à « terminé » si une unité en liaison est à moins de 80 m.
- Clôture : brouillon de compte rendu (`pending`), publication manuelle.
- Création : organigramme réel + type, ou gabarit ; inscrits d’événement repris.

## Fichiers touchés

`MissionPlanningService.php`, `MissionPlanningAtakService.php`, `MissionPlanningController.php`, `AarReportRepository.php`, vues planification, `Extension.cs`, `fn_pollMissionPlan.sqf`, panneau Athena.

## Vérification

PHP `-l` sur les services/contrôleur. Rebuild Overwatch (`connect` 1.4.54, Athena 1.0.44). Recette in-game : publier un plan, ouvrir Athena → onglet Ordre.

## Statut

corrigé
