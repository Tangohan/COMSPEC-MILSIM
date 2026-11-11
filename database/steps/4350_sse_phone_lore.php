<?php

declare(strict_types=1);

/**
 * Étape « Fichiers SSE : téléphones du jeu et lore de session » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_phone_lore_migration.php, rejouée sans effet si tout est en place.
 */

$ssePhoneLoreMigrate = require $root . '/bootstrap/sse_phone_lore_migration.php';
try {
    echo "Migration sse_phone_lore (liaison des téléphones, extractions, sessions de lore)…\n";
    $ssePhoneLoreMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_phone_lore : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_phone_lore', $e);
}
$migrationEnsurePdo();
