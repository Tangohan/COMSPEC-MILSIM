<?php

declare(strict_types=1);

/**
 * Étape « Couvertures liées à une identité et à ses papiers » (Schéma + données).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 *  1. bootstrap/sse_cover_export_links_migration.php crée sse_cover_export_links (rejoué sans effet si elle existe) ;
 * Le rattachement des couvertures existantes (ancien point 2) est repris par l'étape 4450, qui fond chaque couverture dans
 * une identité du membre en lisant ces liens : il n'y a plus de couverture à part sur le site.
 */

$sseCoverLinksMigrate = require $root . '/bootstrap/sse_cover_export_links_migration.php';
try {
    echo "Migration sse_cover_export_links (couverture → identité et papiers)…\n";
    $sseCoverLinksMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_cover_export_links : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_cover_export_links', $e);
}
$migrationEnsurePdo();
