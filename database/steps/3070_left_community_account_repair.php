<?php

declare(strict_types=1);

/**
 * Étape « Comptes bloqués par l’ancien « Quitter la communauté » (statut global passé à inactive) » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Comptes bloqués par l’ancien « Quitter la communauté » (statut global passé à inactive).
$leftCommunityRepair = require $root . '/bootstrap/left_community_account_repair_migration.php';
$migrationRunner->step('left_community_account_repair', static function () use ($leftCommunityRepair, $pdo): void {
    echo "Réparation des comptes bloqués après un départ de communauté...\n";
    $leftCommunityRepair($pdo);
});
