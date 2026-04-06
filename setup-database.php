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
 * Enchaînement :
 *
 *  1. Chargement `.env` et connexion MySQL
 *  2. Import `migrations/schema.sql` (schéma métier)
 *  3. `run_community_platform_migration()` — plans d’abonnement, colonnes `tenants` (facturation / owner)
 *  4. `run_platform_unit_commander_migration()` — invitations, modération, événements, usage,
 *     codes communauté, parrainage (`referral_*`), sécurité, etc.
 *  5. `run_production_import_gap_migrations()` — écarts après import SQL partiel (ex. `u416380327_BDD_PROD.sql`),
 *     refonte courrier, index manquants.
 *  6. `run_rbac_three_layer_migration()` — RBAC 3 couches (rôles site globaux, `role_layer` / `scope`,
 *     table `site_role_assignments`, migration `super_admin` tenant → `community_owner` + rôles site), défini dans
 *     `bootstrap/rbac_three_layer_migration.php` (appelé depuis `run-migrations.php`).
 *  7. ALTERs et tables conditionnelles (enlistments Olympus, ATAK, courrier, …) ; juste après le bootstrap,
 *     migrations LMS sur `training_courses` (thème, vitrine, `enrollment_policy_json`, audio, tables sociales)
 *     pour que les colonnes existent même si la suite du script est longue ou interrompue.
 *  8. Seed : tenant `default`, compte admin, forum, rôles, permissions…
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
