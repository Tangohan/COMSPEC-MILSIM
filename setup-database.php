<?php

declare(strict_types=1);

/**
 * =============================================================================
 * COMSPEC / ATHENA — Initialisation complète de la base de données
 * =============================================================================
 *
 * **Point d’entrée unique** : un seul script à lancer pour tout mettre en place.
 * (Le détail technique reste dans `run-migrations.php`, appelé ci-dessous.)
 *
 * Production : copier les données avant d’exécuter ce script :
 *   php scripts/data-snapshot.php create --label=avant-migration
 *
 * Enchaînement :
 *
 *  1. Chargement `.env` et connexion MySQL
 *  2. Import `migrations/schema.sql` (schéma métier)
 *  2b. Ensure colonnes critiques absentes sur tables déjà existantes
 *     (`bootstrap/schema_ensure_column.php`, ex. `atak_units.military_id` / `pos_x` / `pos_y`)
 *  3. `run_core_schema_extensions_migration()` — DDL étendue (`bootstrap/core_schema_extensions_migration.php`, ex. tableau opérationnel, ORBAT)
 *  4. `run_community_platform_migration()` — plans d’abonnement, colonnes `tenants` (facturation / owner)
 *  5. `run_platform_unit_commander_migration()` — invitations, modération, événements, usage,
 *     codes communauté, parrainage (`referral_*`), sécurité, etc.
 *  6. `run_production_import_gap_migrations()` — écarts après import SQL partiel (ex. `u416380327_BDD_PROD.sql`),
 *     refonte courrier, index manquants.
 *  7. `run_rbac_three_layer_migration()` — RBAC 3 couches (rôles site globaux, `role_layer` / `scope`,
 *     table `site_role_assignments`, migration `super_admin` tenant → `community_owner` + rôles site), défini dans
 *     `bootstrap/rbac_three_layer_migration.php` (appelé depuis `run-migrations.php`).
 *  8. ALTERs et tables conditionnelles (enlistments Olympus, ATAK, courrier, …) ; juste après le bootstrap,
 *     migrations LMS sur `training_courses` (thème, vitrine, `enrollment_policy_json`, audio, tables sociales)
 *     pour que les colonnes existent même si la suite du script est longue ou interrompue.
 *  9. Seed : tenant `default`, compte admin, forum, rôles, permissions…
 *
 * Idempotent : relancer met à jour ce qui manque sans tout casser.
 *
 * Usage CLI :
 *   php setup-database.php
 *
 * Usage web (sortie texte) :
 *   public/setup-database.php
 *
 * Alias historiques (même pipeline) :
 *   php migrate.php
 *   public/migrate.php
 *   public/run-migrations.php
 */

require __DIR__ . '/run-migrations.php';
