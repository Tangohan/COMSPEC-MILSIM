# Fiche personnel : analytics distances absentes du résumé

## Contexte

Fiche membre (`/personnel/…?onglet=resume`), bloc attendu temps de jeu / distances / montre.

## Symptôme

Sur le résumé, les distances parcourues (et le reste des analytics de jeu) manquaient ou n’apparaissaient que dans une petite carte de synthèse, uniquement s’il existait déjà des lignes en base. Sans session remontée, le bloc disparaissait entièrement. Un lecteur sans droit « encadrement » (ou après anonymisation forçant `canStaffView` à faux) ne voyait plus le cumul ni les semaines.

## Cause

- Les distances n’étaient rendues que si `summaryForUser` renvoyait des données (`null` = rien à l’écran).
- Temps de jeu / stats 7–30 j / activité 30 j étaient conditionnés à `$isSelf || $canStaffView`, alors que l’anonymisation force `canStaffView = false` et qu’un membre sans ce droit peut malgré tout ouvrir une fiche nominative.
- Distances et montre vivaient hors du partial « Temps de jeu », donc hors de la zone analytics.

## Correctif

- Chargement des métriques de jeu pour toute fiche non anonymisée (`$canSeePlayMetrics`).
- Distances + montre intégrées au partial `file_playtime.php`, avec état vide explicite si le schéma est prêt mais sans session.
- Retrait des doublons dans la grille Synthèse.

## Fichiers touchés

- `app/Controllers/Web/PersonnelController.php`
- `views/partials/personnel/file_playtime.php`
- `views/personnel/file.php`
- `tests/Unit/AtakPlaytimeSheetTest.php`
- `app/Support/DevDispatchCatalog.php` (UPDATE 1018)

## Vérification

- `php -l` sur les fichiers PHP/vues touchés.
- Test unitaire de câblage du partial mis à jour (titre, `#distances`, message d’attente).

## Statut

Corrigé (à déployer sur le portail).
