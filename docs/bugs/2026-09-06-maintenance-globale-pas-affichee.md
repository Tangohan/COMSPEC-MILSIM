# Maintenance globale présente en base, portail resté ouvert

## Contexte

Une règle `app_maintenance` id 4 est activée, portée globale, du 6 septembre 15 h au 7 septembre 20 h. Le site public restait l’accueil normal.

## Symptôme

Le portail s’affichait comme d’habitude. Pas de page d’intervention, même sans être connecté.

## Cause

Deux filets se combinaient :

- le passage « administrateur » regardait les droits encore en mémoire du serveur, pas la personne de *cette* visite ;
- si la lecture de session ou des droits plantait, toute la règle était ignorée.

## Correctif

Chaque requête repart sans droits hérités. Le passage administrateur n’existe que pour une personne réellement connectée sur cette visite. Une erreur de session n’empêche plus d’afficher la page d’intervention.

## Fichiers touchés

- `app/Core/Gate.php`
- `app/Support/MaintenanceService.php`
- `app/Support/MaintenanceGuard.php`
- `app/Repositories/MaintenanceRepository.php`
- `public/index.php`

## Vérification

- Test `MaintenanceBypassTest`
- Fenêtre privée, sans compte : l’accueil doit montrer la page d’intervention
- Arma et le téléphone ATAK restent ouverts

## Statut

Corrigé (à déployer).
