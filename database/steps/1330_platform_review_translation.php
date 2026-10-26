<?php

declare(strict_types=1);

/**
 * Étape « Migration platform_reviews (avis plateforme et traductions) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$platformReviewTranslateMigrate = require $root . '/bootstrap/platform_review_translation_migration.php';
try {
    echo "Migration platform_reviews (avis plateforme et traductions)...\n";
    $platformReviewTranslateMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] platform_reviews : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('platform_reviews', $e);
}
