<?php

declare(strict_types=1);

/**
 * Étape « Messagerie communautaire (community_messaging.sql) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Le compte technique « Modération automatique » (system.moderation@internal.local) n’est plus
// inséré ici dans chaque communauté : il est créé à la demande, la première fois qu’une action
// automatique en a besoin (SystemModeratorAccountService, cron de quarantaine).

$msgSql = $root . '/migrations/community_messaging.sql';
if (is_file($msgSql)) {
    $chk = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_message_threads'");
    if ($chk && !$chk->fetch()) {
        echo "Migration community_messaging.sql...\n";
        $pdo->exec(file_get_contents($msgSql));
    }
}
