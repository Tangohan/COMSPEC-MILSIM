<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_mini_articles » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$tenantMiniArticlesMigrate = require $root . '/bootstrap/tenant_mini_articles_migration.php';
try {
    $tenantMiniArticlesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] tenant_mini_articles : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('tenant_mini_articles', $e);
}
