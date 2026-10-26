<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_realism_config (réglages réalisme ATAK par communauté) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('atak_realism_config_migration.php', static function () use ($root, $pdo): void {
    echo "Migration atak_realism_config (réglages réalisme ATAK par communauté)...\n";
    require_once $root . '/bootstrap/atak_realism_config_migration.php';
    require_once $root . '/bootstrap/atak_realism_config_seed.php';
    run_atak_realism_config_migration($pdo);
    run_atak_realism_config_seed($pdo);
});
$migrationEnsurePdo();
