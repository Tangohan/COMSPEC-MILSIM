# Bug — Escalade des rapports tactiques : méthode manquante + tâches jamais lancées

## Contexte

Page Administration → Tâches automatiques. La tâche « Escalade des rapports tactiques » passait en échec. Le planificateur du serveur n’exécutait pas non plus les autres travaux récurrents.

## Symptôme

Message : « Erreur : Call to undefined method App\Core\Database::query() ». Statut Échec, déclencheur Administration. Les rapports non acquittés n’étaient jamais escaladés tout seuls.

## Cause

Les dépôts de routage ATAK appellent `$this->db->query(…)->fetch()` / `fetchAll()`, alors que `App\Core\Database` n’exposait que `fetchAll`, `fetchOne`, `execute` et `insert`. Le passage serveur n’était documenté que comme un essai manuel quotidien, sans installation réelle toutes les cinq minutes.

## Correctif

- Ajouter `Database::query()` (prépare, exécute, renvoie l’énoncé) pour tous les dépôts ATAK concernés.
- Installer un passage toutes les cinq minutes (`scripts/install-system-cron.sh` / équivalent Windows), avec un filet si un opérateur ouvre le portail.
- Chaque tâche ne se relance que si elle est due (l’escalade toutes les 5 minutes, les digestifs une fois par jour).

## Fichiers touchés

- `app/Core/Database.php`
- `app/Services/Cron/CronSchedule.php`
- `app/Services/Cron/CronWatchdog.php`
- `app/Services/Cron/CronRunner.php`
- `app/Core/Application.php`
- `app/Controllers/Admin/System/SystemCronController.php`
- `views/admin/system/cron.php`
- `scripts/cron-run.php`
- `scripts/install-system-cron.sh`
- `scripts/install-system-cron.ps1`

## Vérification

- `method_exists(Database, query)` et cadence `CronSchedule` (tests unitaires).
- Le script d’installation contient `cron-run.php` et `*/5`.
- La page d’administration parle d’un passage toutes les cinq minutes.

## Statut

corrigé
