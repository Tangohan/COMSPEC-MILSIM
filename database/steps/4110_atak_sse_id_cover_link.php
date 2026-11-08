<?php

declare(strict_types=1);

/**
 * Étape « Lien Athena des couvertures » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_id_cover_link_migration.php, rejoué sans effet si les tables sont en place.
 */

$atakSseIdCoverLinkMigrate = require $root . '/bootstrap/atak_sse_id_cover_link_migration.php';
try {
    echo "Migration atak_sse_id_cover_link (couvertures reliées à Athena)…\n";
    $atakSseIdCoverLinkMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_id_cover_link : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_id_cover_link', $e);
}
$migrationEnsurePdo();
