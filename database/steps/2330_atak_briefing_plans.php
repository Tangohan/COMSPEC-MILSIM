<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_briefing_plans (plan de briefing des téléphones sur la carte du site) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Plan de briefing de l'app Plan (ATAK natif) affiché sur la carte du site (COMSPEC Link 2.0.84).
$migrationRunner->step('atak_briefing_plans', static function () use ($pdo): void {
    echo "Migration atak_briefing_plans (plan de briefing des téléphones sur la carte du site)...\n";
    $pdo->exec(\App\Repositories\AtakBriefingPlanRepository::createTableSql());
});
