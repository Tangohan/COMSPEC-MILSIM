# Audit : dump production vs migrations (SQL / PHP)

**Lecture dans le navigateur** : une fois connecté au portail, ouvrez le **guide du portail**, puis **Références projet**, et choisissez la fiche **Audit export base de données / migrations**.

Ce document implémente la vérification demandée : inventaire Phinx, comparaison de schéma via script, et liste des fichiers `migrations/*.sql` hors pipeline.

## 1. Migrations Phinx (`migrations/*.php`, hors `seeds/`)

Fichier | Classe Phinx | Rôle (résumé)
--- | --- | ---
`20250313000001_create_tenants_and_users.php` | `CreateTenantsAndUsers` | Socle tenants / users
`20250313000002_create_units_and_profiles.php` | `CreateUnitsAndProfiles` | Unités et profils
`20250313000003_create_enlistments.php` | `CreateEnlistments` | Enrôlements
`20250313000004_create_documents.php` | `CreateDocuments` | Documents
`20250313000005_create_training.php` | `CreateTraining` | LMS / formations
`20260404000001_create_app_maintenance.php` | `CreateAppMaintenance` | Maintenance applicative
`20260412000001_create_operational_board_tables.php` | `CreateOperationalBoardTables` | Tables `planning_*` (tableau opérationnel)
`20260412000002_enhance_operational_board_features.php` | `EnhanceOperationalBoardFeatures` | Colonnes / tables planning étendues
`20260412000003_operational_board_advanced_workflows.php` | `OperationalBoardAdvancedWorkflows` | Workflows planning
`20260412000004_units_orbat_mask_mode.php` | `UnitsOrbatMaskMode` | Colonne `units.orbat_mask_mode`
`20260412000004_backfill_roles_label_en.php` | `BackfillRolesLabelEn` | Données `roles.label_en` (PHP / pas de DDL nouveau)
`20260412000005_tenant_email_communications.php` | `TenantEmailCommunications` | `tenant_email_*`, `email_deliveries.campaign_id`
`20260412100001_orbat_display_types_and_media.php` | `OrbatDisplayTypesAndMedia` | `tenant_orbat_chart_types`, colonnes ORBAT sur `units`
`20260412120000_operational_board_manifestation_flash_enum.php` | `OperationalBoardManifestationFlashEnum` | Enum `planning_entries.entry_type` (manifestation / flash_info)

Lancement : [`bootstrap/phinx_runner.php`](../bootstrap/phinx_runner.php) depuis [`run-migrations.php`](../run-migrations.php), table journal `phinxlog` (voir [`config/phinx.php.dist`](../config/phinx.php.dist)).

### Table `phinxlog` dans le dump `u416380327_BDD_PROD.sql`

- **Absente** de l’export analysé : phpMyAdmin n’a pas inclus cette table (ou Phinx n’a jamais été exécuté sur cette base).
- Conséquence : on **ne peut pas** déduire depuis le seul dump quelles versions Phinx ont été appliquées ; il faut interroger la prod (`SELECT * FROM phinxlog ORDER BY version`) ou régénérer un export en incluant `phinxlog`.

### Collision de version Phinx (à surveiller)

Deux fichiers partagent le préfixe **`20260412000004_`** : `units_orbat_mask_mode` et `backfill_roles_label_en`. Selon la façon dont Phinx trie les migrations, l’ordre d’exécution peut être ambigu ; à confirmer sur l’environnement (`phinx migrate` + journaux).

## 2. Comparaison structure dump vs dump (référence)

Script : [`scripts/compare-sql-dump-schemas.php`](../scripts/compare-sql-dump-schemas.php).

- Compare les **noms de tables** déclarés par `CREATE TABLE` / `CREATE TABLE IF NOT EXISTS` dans deux fichiers SQL.
- Ne compare **pas** colonnes, index ni contraintes (évolution possible du script).

### Générer une référence « schéma à jour »

Sur une base **vierge** (ou jetable), après exécution du pipeline complet :

1. Configurer `.env` (`DB_*`).
2. Lancer `php run-migrations.php` (ou `php setup-database.php` selon votre procédure).
3. Exporter la structure seule, par exemple :

```bash
mysqldump -h HOST -u USER -p --no-data --skip-comments NOM_BASE > schema-reference-structure.sql
```

4. Comparer :

```bash
php scripts/compare-sql-dump-schemas.php schema-reference-structure.sql u416380327_BDD_PROD.sql
```

Lister les tables d’un dump :

```bash
php scripts/compare-sql-dump-schemas.php --list-tables u416380327_BDD_PROD.sql
```

**Constat connu (analyse manuelle du dump prod du 2026-04-13)** : absence des tables `planning_*`, des tables `tenant_email_*`, absence de `campaign_id` sur `email_deliveries`, absence des colonnes ORBAT récentes sur `units` — cohérent avec des migrations Phinx d’avril 2026 non appliquées sur la base exportée.

## 3. Fichiers `migrations/*.sql` : branchés vs hors pipeline

### Branchés (chargés par `run-migrations.php` et/ou un `bootstrap/*_migration.php` / `prod_import_gaps`)

- `schema.sql` — `run-migrations.php`
- `personnel_dossier.sql`, `user_profile_display_settings.sql`, `courrier_module.sql`, `grade_referentiel.sql`, `courrier_signatures.sql`, `courrier_document_notifications.sql`, `community_messaging.sql`, `platform_integrations.sql`, `schema_v2_tenant_user_prefs.sql` — `run-migrations.php`
- `courrier_refonte.sql` — [`bootstrap/prod_import_gaps.php`](../bootstrap/prod_import_gaps.php)
- `alerts_system.sql` — [`bootstrap/alerts_migration.php`](../bootstrap/alerts_migration.php)
- `moderation_content.sql` — [`bootstrap/moderation_content_migration.php`](../bootstrap/moderation_content_migration.php)
- `20260408000001_competency_progression_framework.sql`, `20260408000002_competency_progression_logs.sql` — [`bootstrap/competency_progression_framework_migration.php`](../bootstrap/competency_progression_framework_migration.php)
- `personnel_job_roles_system.sql` — [`bootstrap/personnel_job_roles_migration.php`](../bootstrap/personnel_job_roles_migration.php)
- `forum_moderation_bot.sql`, `forum_premium.sql`, `forum_v2.sql` — bootstraps forum
- `enlistment_canned_messages.sql` — [`bootstrap/enlistment_canned_messages_migration.php`](../bootstrap/enlistment_canned_messages_migration.php)

### Hors pipeline automatique (documentation, exécution manuelle, doublon possible avec PHP / Phinx / DDL inline)

Ne pas exiger leur présence telle quelle dans le dump si l’équivalent est appliqué autrement.

- `20260412000001_ops_wall.sql` — mur opérationnel `ops_board_*` (souvent appliqué manuellement ou via autre voie ; le dump prod peut déjà contenir ces tables)
- `2026_04_06_forum_reports_content_kind.sql` — colonne `forum_reports.content_kind`
- `lms_training.sql` — parcours legacy / renommage tables (Phinx `CreateTraining` couvre l’installation moderne)
- `maintenance.sql` — voir Phinx `CreateAppMaintenance`
- `c2_pillars.sql` — mentionné pour exécution manuelle dans [`scripts/check-c2-schema.php`](../scripts/check-c2-schema.php)
- `site_settings.sql`, `setup_system_admin_manual.sql` — procédures / exemples
- `tenant_dashboard_pins.sql`, `tenant_community_feed.sql` — doublons possibles avec DDL dans [`bootstrap/tenant_dashboard_pins_migration.php`](../bootstrap/tenant_dashboard_pins_migration.php) et [`bootstrap/tenant_community_feed_migration.php`](../bootstrap/tenant_community_feed_migration.php)
- `training_course_lms_platform_version.sql`, `training_course_lms_theme.sql`, `training_lms_engagement.sql`, `training_module_lesson_enrichment.sql`, `training_resources_library_document.sql`, `training_lesson_canvas_enum.sql`, `training_lesson_types_quiz_modals.sql` — équivalents en PHP dans les bootstraps `training_*_migration.php` et/ou blocs dans `run-migrations.php`
- `community_events_attendance.sql`, `public_community_showcase.sql`, `document_module_refactor.sql`, `recruitment_enlistment_account.sql`, `recruitment_rp_snapshot.sql`, `alter_personnel_profiles_primary_unit.sql`, `default_tenant_display_name.sql`, `grade_nco_mdr.sql`, `tenant_grade_overrides.sql` — non référencés dans `run-migrations.php` ; partie du schéma peut provenir de `schema.sql` ou de DDL procédural dans `run-migrations.php`

Pour une revue à jour des références exactes, rechercher dans le dépôt : motif `migrations/` + `.sql` dans les fichiers `*.php`.
