<?php

declare(strict_types=1);

/**
 * Étape « Migration tactical_briefing_slide_enrichment (briefing — détail diapositive, commentaires) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tacticalBriefingSlideEnrichmentMigrate = require $root . '/bootstrap/tactical_briefing_slide_enrichment_migration.php';
try {
    echo "Migration tactical_briefing_slide_enrichment (briefing — détail diapositive, commentaires)…\n";
    $tacticalBriefingSlideEnrichmentMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tactical_briefing_slide_enrichment : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tactical_briefing_slide_enrichment', $e);
}
$migrationEnsurePdo();
