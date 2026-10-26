<?php

declare(strict_types=1);

/**
 * Étape « Catalogue des grades américains : fautes de frappe, doublons et échelle de solde manquante » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Catalogue des grades américains : fautes de frappe, doublons et échelle de solde manquante.
$usRankCatalogFix = require $root . '/bootstrap/us_rank_catalog_fix_migration.php';
$migrationRunner->step('us_rank_catalog_fix', static function () use ($usRankCatalogFix, $pdo): void {
    $usRankCatalogFix($pdo);
});
