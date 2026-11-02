<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY : registre des identités, papiers et contrôles du SSE » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_migration.php (CREATE TABLE IF NOT EXISTS, pays fictifs ajoutés seulement s'ils
 * manquent), aussi appelée par les dépôts SseIdentityRepository & co au premier usage.
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
try {
    echo "Migration atak_sse_identity (registre des identités, papiers et contrôles)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity', $e);
}
$migrationEnsurePdo();
