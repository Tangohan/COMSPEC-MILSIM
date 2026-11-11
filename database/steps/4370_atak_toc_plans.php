<?php

declare(strict_types=1);

/**
 * Étape « Poste TOC web et plans d'opération » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_toc_plans_migration.php, rejouée sans effet si tout est en place.
 */

$atakTocPlansMigrate = require $root . '/bootstrap/atak_toc_plans_migration.php';
try {
    echo "Migration atak_toc_plans (plans d'opération, phases, versions, partage, salle TOC web)…\n";
    $atakTocPlansMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_toc_plans : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_toc_plans', $e);
}
$migrationEnsurePdo();
