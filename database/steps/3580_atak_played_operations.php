<?php

declare(strict_types=1);

/**
 * Étape « Parties détectées en jeu : types, catégories, règles d'exclusion, rattachement aux opérations planifiées » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : les tables ne sont créées que si elles
 * manquent (migrations/2026_11_02_130_atak_played_operations.sql, CREATE TABLE IF NOT EXISTS) et chaque colonne de
 * atak_debrief_ops n'est ajoutée que si elle manque (bootstrap/atak_played_ops_migration.php). Aucune ligne n'est créée,
 * modifiée ni supprimée.
 */

require_once $root . '/bootstrap/atak_played_ops_migration.php';
run_atak_played_ops_migration($pdo);
