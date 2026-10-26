<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_chat_channels + channel_key (canaux radio) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakChatChannelsMigrate = require $root . '/bootstrap/atak_chat_channels_migration.php';
try {
    echo "Migration atak_chat_channels + channel_key (canaux radio)...\n";
    $atakChatChannelsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_chat_channels : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_chat_channels', $e);
}
$migrationEnsurePdo();
