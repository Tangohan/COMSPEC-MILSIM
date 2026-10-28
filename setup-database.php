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
 * Enchaînement : run-migrations.php charge .env, se connecte, puis exécute dans l’ordre les étapes du registre
 * database/steps/registry.php (`php run-migrations.php --list` pour les voir) :
 *
 *  - Schéma : migrations/schema.sql, colonnes critiques manquantes, fichiers migrations/*.sql en attente ;
 *  - Migrations : migrations PHP de bootstrap/ (plateforme, RBAC, LMS, ATAK, SSE, courrier…) ;
 *  - Référentiels : données indispensables seulement (cartes ATAK, grades, formats courrier, communauté système
 *    « default » et premier administrateur — mot de passe via ATHENA_FIRST_ADMIN_PASSWORD, jamais « admin ») ;
 *  - Maintenance : rattrapages et nettoyages de données.
 *
 * Plus aucun contenu de démonstration n’est injecté (parcours de formation, modèles CERBERE, renommages ponctuels).
 * Chaque étape est notée dans schema_migration_runs ; l’interface web /run-migrations.php l’affiche.
 *
 * Idempotent : relancer met à jour ce qui manque sans tout casser. Les fichiers migrations/*.sql
 * sont suivis dans la table `comspec_schema_migrations` (voir bootstrap/migration_runner.php) ;
 * `php run-migrations.php --status` liste ceux en attente ou en échec.
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
