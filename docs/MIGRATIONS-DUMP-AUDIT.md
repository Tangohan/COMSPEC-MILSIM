# Audit : dump production vs migrations (SQL / PHP)

**Lecture dans le navigateur** : une fois connecté au portail, ouvrez le **guide du portail**, puis **Références projet**, et choisissez la fiche **Audit export base de données / migrations**.

Ce document implémente la vérification demandée : inventaire des extensions de schéma, comparaison de schéma via script, et liste des fichiers `migrations/*.sql` hors pipeline.

## 1. Extensions DDL (remplace l’ancien outil Phinx)

Les anciens fichiers `migrations/*.php` (Phinx) ont été **retirés**. L’équivalent est dans  
`[bootstrap/core_schema_extensions_migration.php](../bootstrap/core_schema_extensions_migration.php)`  
(fonction `run_core_schema_extensions_migration`), appelée depuis `[run-migrations.php](../run-migrations.php)` après l’import de `migrations/schema.sql`.

Couverture typique : `app_maintenance` / audit, tableau opérationnel (`planning_*`, etc.), ORBAT (`tenant_orbat_chart_types`, colonnes `units.orbat_*`), e-mails tenant (`tenant_email_*`, `email_deliveries.campaign_id`), libellés anglais des rôles (`TenantDefaultRoleDefinitions::applyCanonicalEnglishLabels`).

La table **`phinxlog`** peut encore exister sur d’anciennes bases ; le projet ne l’alimente plus.

## 2. Comparaison structure dump vs dump (référence)

Script : `[scripts/compare-sql-dump-schemas.php](../scripts/compare-sql-dump-schemas.php)`.

- Compare les **noms de tables** déclarés par `CREATE TABLE` / `CREATE TABLE IF NOT EXISTS` dans deux fichiers SQL.
- Ne compare **pas** colonnes, index ni contraintes (évolution possible du script).

### Générer une référence « schéma à jour »

Sur une base **vierge** (ou jetable), après exécution du pipeline complet :

1. Configurer `.env` (`DB_`*).
2. Lancer `php run-migrations.php` (ou `php setup-database.php` selon votre procédure).
3. Exporter la structure seule, par exemple :

```bash
mysqldump -h HOST -u USER -p --no-data --skip-comments NOM_BASE > schema-reference-structure.sql
```

1. Comparer :

```bash
php scripts/compare-sql-dump-schemas.php schema-reference-structure.sql u416380327_BDD_PROD.sql
```

Lister les tables d’un dump :

```bash
php scripts/compare-sql-dump-schemas.php --list-tables u416380327_BDD_PROD.sql
```

**Constat connu (analyse manuelle du dump prod du 2026-04-13)** : absence des tables `planning_`*, des tables `tenant_email_*`, absence de `campaign_id` sur `email_deliveries`, absence des colonnes ORBAT récentes sur `units` — cohérent avec l’absence d’exécution de `run_core_schema_extensions_migration` / `setup-database.php` sur cette base.

## 3. Fichiers `migrations/*.sql` : branchés vs hors pipeline

### Branchés (chargés par `run-migrations.php` et/ou un `bootstrap/*_migration.php` / `prod_import_gaps`)

- `schema.sql` — `run-migrations.php`
- `personnel_dossier.sql`, `user_profile_display_settings.sql`, `courrier_module.sql`, `grade_referentiel.sql`, `courrier_signatures.sql`, `courrier_document_notifications.sql`, `community_messaging.sql`, `platform_integrations.sql`, `schema_v2_tenant_user_prefs.sql` — `run-migrations.php`
- `courrier_refonte.sql` — `[bootstrap/prod_import_gaps.php](../bootstrap/prod_import_gaps.php)`
- `alerts_system.sql` — `[bootstrap/alerts_migration.php](../bootstrap/alerts_migration.php)`
- `moderation_content.sql` — `[bootstrap/moderation_content_migration.php](../bootstrap/moderation_content_migration.php)`
- `20260408000001_competency_progression_framework.sql`, `20260408000002_competency_progression_logs.sql` — `[bootstrap/competency_progression_framework_migration.php](../bootstrap/competency_progression_framework_migration.php)`
- `personnel_job_roles_system.sql` — `[bootstrap/personnel_job_roles_migration.php](../bootstrap/personnel_job_roles_migration.php)`
- `forum_moderation_bot.sql`, `forum_premium.sql`, `forum_v2.sql` — bootstraps forum
- `enlistment_canned_messages.sql` — `[bootstrap/enlistment_canned_messages_migration.php](../bootstrap/enlistment_canned_messages_migration.php)`

### Hors pipeline automatique (documentation, exécution manuelle, doublon possible avec DDL inline)

Ne pas exiger leur présence telle quelle dans le dump si l’équivalent est appliqué autrement.

- `20260412000001_ops_wall.sql` — mur opérationnel `ops_board_*` (souvent appliqué manuellement ou via autre voie ; le dump prod peut déjà contenir ces tables)
- `2026_04_06_forum_reports_content_kind.sql` — colonne `forum_reports.content_kind`
- `lms_training.sql` — parcours legacy / renommage tables (schéma moderne : `schema.sql` + pipeline PHP)
- `maintenance.sql` — doublon partiel possible avec `core_schema_extensions_migration` (`app_maintenance`)
- `c2_pillars.sql` — mentionné pour exécution manuelle dans `[scripts/check-c2-schema.php](../scripts/check-c2-schema.php)`
- `site_settings.sql`, `setup_system_admin_manual.sql` — procédures / exemples
- `tenant_dashboard_pins.sql`, `tenant_community_feed.sql` — doublons possibles avec DDL dans `[bootstrap/tenant_dashboard_pins_migration.php](../bootstrap/tenant_dashboard_pins_migration.php)` et `[bootstrap/tenant_community_feed_migration.php](../bootstrap/tenant_community_feed_migration.php)`
- `training_course_lms_platform_version.sql`, `training_course_lms_theme.sql`, `training_lms_engagement.sql`, `training_module_lesson_enrichment.sql`, `training_resources_library_document.sql`, `training_lesson_canvas_enum.sql`, `training_lesson_types_quiz_modals.sql` — équivalents en PHP dans les bootstraps `training_*_migration.php` et/ou blocs dans `run-migrations.php`
- `community_events_attendance.sql`, `public_community_showcase.sql`, `document_module_refactor.sql`, `recruitment_enlistment_account.sql`, `recruitment_rp_snapshot.sql`, `alter_personnel_profiles_primary_unit.sql`, `default_tenant_display_name.sql`, `grade_nco_mdr.sql`, `tenant_grade_overrides.sql` — non référencés dans `run-migrations.php` ; partie du schéma peut provenir de `schema.sql` ou de DDL procédural dans `run-migrations.php`

Pour une revue à jour des références exactes, rechercher dans le dépôt : motif `migrations/` + `.sql` dans les fichiers `*.php`.