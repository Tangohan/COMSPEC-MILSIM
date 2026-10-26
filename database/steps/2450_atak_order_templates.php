<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_order_templates (modèles d’ordres personnalisés) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakOrderTemplatesMigrate = require $root . '/bootstrap/atak_order_templates_migration.php';
try {
    echo "Migration atak_order_templates (modèles d’ordres personnalisés)...\n";
    $atakOrderTemplatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_order_templates : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_order_templates', $e);
}
$migrationEnsurePdo();
