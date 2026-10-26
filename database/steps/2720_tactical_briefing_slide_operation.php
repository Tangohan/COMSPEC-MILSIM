<?php

declare(strict_types=1);

/**
 * Étape « Migration tactical_briefing_slide_operation (briefing — rattachement diapositive ↔ opération) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tacticalBriefingSlideOperationMigrate = require $root . '/bootstrap/tactical_briefing_slide_operation_migration.php';
try {
    echo "Migration tactical_briefing_slide_operation (briefing — rattachement diapositive ↔ opération)…\n";
    $tacticalBriefingSlideOperationMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tactical_briefing_slide_operation : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tactical_briefing_slide_operation', $e);
}
$migrationEnsurePdo();
