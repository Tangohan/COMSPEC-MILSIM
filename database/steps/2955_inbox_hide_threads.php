<?php

declare(strict_types=1);

/**
 * Étape « Migration messagerie : masquer une conversation (tenant_message_thread_users.hidden_at) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Livrée avec la suppression de conversations (bootstrap/inbox_hide_threads_migration.php). Aussi lancée à la
// demande par TenantMessageRepository ; ici pour que la colonne existe dès le passage des migrations.
$inboxHideThreadsPath = $root . '/bootstrap/inbox_hide_threads_migration.php';
if (is_file($inboxHideThreadsPath)) {
    $inboxHideThreadsMigrate = require_once $inboxHideThreadsPath;
    try {
        echo "Migration messagerie : conversations masquées (tenant_message_thread_users.hidden_at)...\n";
        if (is_callable($inboxHideThreadsMigrate)) {
            $inboxHideThreadsMigrate($pdo);
        } elseif (function_exists('run_inbox_hide_threads_migration')) {
            run_inbox_hide_threads_migration($pdo);
        }
    } catch (Throwable $e) {
        echo '  [ATTENTION] inbox_hide_threads : ' . $e->getMessage() . "\n";
        $migrationRunner->warn('inbox_hide_threads', $e);
    }
    $migrationEnsurePdo();
}
