<?php

declare(strict_types=1);

/**
 * Étape « Migration arsenal_wardrobe (wardrobes ACE Arsenal + collections d’équipement) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$arsenalWardrobeMigrate = require $root . '/bootstrap/arsenal_wardrobe_migration.php';
try {
    echo "Migration arsenal_wardrobe (wardrobes ACE Arsenal + collections d’équipement)...\n";
    $migrationFlush();
    $arsenalWardrobeMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] arsenal_wardrobe : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('arsenal_wardrobe', $e);
}
$migrationEnsurePdo();
