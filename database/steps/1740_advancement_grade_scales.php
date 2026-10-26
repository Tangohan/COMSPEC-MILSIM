<?php

declare(strict_types=1);

/**
 * Étape « Échelles d’avancement des communautés » (Maintenance).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

require_once $root . '/bootstrap/autoload.php';

try {
    echo "Échelles d’avancement des communautés déjà créées...\n";
    $scaleSvc = new \App\Services\Advancement\GradeScaleTemplateService(
        new \App\Repositories\AdvancementRepository($pdo)
    );
    $seededScales = $scaleSvc->ensureForAllTenants();
    echo '  communautés initialisées=' . (int) $seededScales . "\n";
} catch (Throwable $e) {
    echo '  [ATTENTION] advancement_grade_scale_backfill : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('advancement_grade_scale_backfill', $e);
}
