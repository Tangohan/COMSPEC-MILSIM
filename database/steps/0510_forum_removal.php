<?php

declare(strict_types=1);

/**
 * Étape « Migration forum_removal » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

try {
    run_forum_removal_post_schema($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] forum_removal : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('forum_removal', $e);
}
