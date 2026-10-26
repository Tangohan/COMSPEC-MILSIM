<?php

declare(strict_types=1);

/**
 * Étape « Migration training_certificate_templates » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$trainingCertificateTemplatesMigrate = require $root . '/bootstrap/training_certificate_templates_migration.php';
try {
    $trainingCertificateTemplatesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] training_certificate_templates : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('training_certificate_templates', $e);
}
