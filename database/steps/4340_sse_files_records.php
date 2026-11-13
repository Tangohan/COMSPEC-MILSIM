<?php

declare(strict_types=1);

/**
 * Étape « Fichiers SSE : fiche sujet, recherche avancée, recoupements » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_files_records_migration.php, rejouée sans effet si tout est en place.
 */

$sseFilesRecordsMigrate = require $root . '/bootstrap/sse_files_records_migration.php';
try {
    echo "Migration sse_files_records (signalement, alias, liens, photos, notes, recherches, recoupements, surveillances)…\n";
    $sseFilesRecordsMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_files_records : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_files_records', $e);
}
$migrationEnsurePdo();
