<?php

declare(strict_types=1);

/**
 * Étape « Migration community_showcase_vitrine (places unités / agenda public) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$communityShowcaseVitrineMigrate = require $root . '/bootstrap/community_showcase_vitrine_migration.php';
try {
    echo "Migration community_showcase_vitrine (places unités / agenda public)...\n";
    $communityShowcaseVitrineMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] community_showcase_vitrine : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('community_showcase_vitrine', $e);
}
