# Pipeline migrations — PDO 2014 + DELETE catégories emplois

## Contexte

Exécution du pipeline Athena (web / `run-migrations.php`) sur une base déjà peuplée (rejeu idempotent).

## Symptôme

1. Après `recruitment_invite_codes.sql`, cascade d’erreurs `SQLSTATE[HY000]: General error: 2014 Cannot execute queries while other unbuffered queries are active` sur tous les SQL suivants.
2. Échec fatal non capturé sur `function_based_access_v2_migration.php` (même 2014) → statut « Terminé avec erreurs ».
3. En parallèle (pipeline principal) : `[ATTENTION] … Table 'c' is specified twice` sur les purges d’emplois (`unit_derived_job_roles`, `unused_*`, `community_access_roles_purge_v1`).

## Cause

1. **PDO 2014** : le rejeu complémentaire exécutait `EXECUTE stmt` via `PDO::exec()`. Quand la branche « colonne déjà présente » était un `SELECT … AS message`, le result set restait ouvert. Les instructions suivantes (et FBAC v2) échouaient.
2. **MariaDB 1093** : `DELETE c FROM personnel_job_role_categories c … NOT EXISTS (… FROM personnel_job_role_categories ch …)` — même table cible et source dans une sous-requête.

## Correctif

- `bootstrap/migrations_full_post.php` : traiter `EXECUTE` / `CALL` comme des instructions à result set (drain + `closeCursor`) ; reconnexion si 2014 ; exclure les `*_manual.sql`.
- `migrations/recruitment_invite_codes.sql` : branche no-op en `DO 0` (sans result set).
- `UnitJobRoleSyncService::deleteEmptyCategories` : SELECT des ids puis DELETE par liste.
- `run-migrations.php` : `migrationEnsurePdo` après les SQL complémentaires ; FBAC v2 en try/catch.

## Fichiers touchés

- `bootstrap/migrations_full_post.php`
- `bootstrap/migration_pdo.php`
- `migrations/recruitment_invite_codes.sql`
- `app/Services/Personnel/UnitJobRoleSyncService.php`
- `run-migrations.php`
- `tests/Unit/MigrationsBackOfficeAssetTest.php`
- `tests/Unit/UnitJobRoleSyncServiceTest.php`

## Vérification

- Relancer le pipeline migrations : plus d’échec fatal 2014 en fin de course.
- Les purges d’emplois affichent un compteur (ou 0) sans ATTENTION 1093.
- `setup_system_admin_manual.sql` n’apparaît plus dans la liste des SQL complémentaires.

## Statut

corrigé
