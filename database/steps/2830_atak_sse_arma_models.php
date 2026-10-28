<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_sse_arma_models (SSE — modèles mission Arma) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakSseArmaModelsMigrate = require $root . '/bootstrap/atak_sse_arma_models_migration.php';
try {
    echo "Migration atak_sse_arma_models (SSE — modèles mission Arma)…\n";
    $atakSseArmaModelsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_arma_models : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_arma_models', $e);
}
$migrationEnsurePdo();
