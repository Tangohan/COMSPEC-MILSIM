<?php

declare(strict_types=1);

/**
 * Étape « Comptes « Modération automatique » insérés autrefois dans chaque communauté sans servir » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Comptes « Modération automatique » insérés autrefois dans chaque communauté sans servir.
$systemModeratorCleanup = require $root . '/bootstrap/system_moderator_cleanup_migration.php';
$migrationRunner->step('system_moderator_cleanup', static function () use ($systemModeratorCleanup, $pdo): void {
    echo "Nettoyage des comptes techniques system.moderation@internal.local inutilisés...\n";
    $systemModeratorCleanup($pdo);
});
