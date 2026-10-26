<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_chat_messages.source (origine terrain / poste de commandement) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakChatSourceMigrate = require $root . '/bootstrap/atak_chat_source_migration.php';
try {
    echo "Migration atak_chat_messages.source (origine terrain / poste de commandement)...\n";
    $atakChatSourceMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_chat_messages.source : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_chat_messages.source', $e);
}
$migrationEnsurePdo();
