<?php

declare(strict_types=1);

/**
 * Étape « Migration catalogue d’organisation (modèles et applications) » (Référentiels).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$organizationCatalogMigrate = require $root . '/bootstrap/organization_catalog_migration.php';
try {
    echo "Migration catalogue d’organisation (modèles et applications)...\n";
    $organizationCatalogMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] organization_catalog : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('organization_catalog', $e);
}
