<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_community_feed » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantCommunityFeedMigrate = require $root . '/bootstrap/tenant_community_feed_migration.php';
try {
    $tenantCommunityFeedMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_community_feed : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_community_feed', $e);
}
