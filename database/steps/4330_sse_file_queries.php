<?php

declare(strict_types=1);

/**
 * Étape « Passage aux fichiers » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_file_queries_migration.php, rejouée sans effet si tout est en place.
 */

$sseFileQueriesMigrate = require $root . '/bootstrap/sse_file_queries_migration.php';
try {
    echo "Migration sse_file_queries (journal des consultations, mentions aux fichiers, fiches SEEK II)…\n";
    $sseFileQueriesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_file_queries : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_file_queries', $e);
}
$migrationEnsurePdo();
