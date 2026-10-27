<?php

declare(strict_types=1);

/**
 * Étape « Schéma de base (migrations/schema.sql) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// ----- Schéma (exécution statement par statement : PDO::exec ne gère qu'une requête) -----
// Pas de limite : un arrêt au milieu laisserait des migrations SQL jamais appliquées.
set_time_limit(0);
$schemaPath = $root . '/migrations/schema.sql';
echo "Exécution du schéma...\n";
$migrationFlush();

try {
    run_forum_removal_pre_schema($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] forum_removal (avant schéma) : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('forum_removal_pre_schema', $e);
}

if (!$migrationRunner->runSchema($schemaPath)) {
    exit(1);
}
