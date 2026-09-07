# Fiche membre — variables de parcours non initialisées

## Contexte

Lors du branchement du parcours RH et de l’activité Arma sur la fiche membre, le rendu recevait `phaseChecklist`, `phaseTransitions` et `armaSessionActivity` sans les calculer.

## Symptôme

Ouverture d’une fiche membre : variables PHP non définies, bloc parcours / activité Arma absent ou en erreur.

## Cause

Le tableau passé à la vue a été enrichi avant que les valeurs correspondantes soient calculées dans `PersonnelController::show`.

## Correctif

Calcul des trois variables (try/catch si les tables n’existent pas encore), puis insertion du bloc Parcours / Activité Arma dans `views/personnel/file.php`.

## Fichiers touchés

- `app/Controllers/Web/PersonnelController.php`
- `views/personnel/file.php`

## Vérification

La fiche n’envoie plus de variables non définies ; le bloc Parcours n’apparaît que si une étape suivante existe.

## Statut

Corrigé
