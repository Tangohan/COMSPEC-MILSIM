<?php

declare(strict_types=1);

/**
 * Étape « Synchronisation Athena ↔ jeu des identités » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_identity_sync_migration.php, rejouée sans effet si tout est en place.
 */

$sseIdentitySyncMigrate = require $root . '/bootstrap/sse_identity_sync_migration.php';
try {
    echo "Migration sse_identity_sync (historique des identités, différence avec le jeu)…\n";
    $sseIdentitySyncMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_identity_sync : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_identity_sync', $e);
}
$migrationEnsurePdo();
