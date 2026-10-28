<?php

declare(strict_types=1);

/**
 * Étape « Migration content_tags (tags partagés formations/documentations) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$contentTagsMigrate = require $root . '/bootstrap/content_tags_migration.php';
try {
    echo "Migration content_tags (tags partagés formations/documentations)...\n";
    $contentTagsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] content_tags : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('content_tags', $e);
}
