<?php

declare(strict_types=1);

/**
 * Étape « Couvertures générées et téléchargées » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_cover_exports_migration.php, rejoué sans effet si la table est en place.
 */

$sseCoverExportsMigrate = require $root . '/bootstrap/sse_cover_exports_migration.php';
try {
    echo "Migration sse_cover_exports (couvertures générées pour le jeu)…\n";
    $sseCoverExportsMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_cover_exports : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_cover_exports', $e);
}
$migrationEnsurePdo();
