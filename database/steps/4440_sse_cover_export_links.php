<?php

declare(strict_types=1);

/**
 * Étape « Couvertures liées à une identité et à ses papiers » (Schéma + données).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente :
 *  1. bootstrap/sse_cover_export_links_migration.php crée sse_cover_export_links (rejoué sans effet si elle existe) ;
 *  2. App\Support\SseCoverLinkMatcher::backfill rattache les couvertures existantes quand c'est sans ambiguïté (même
 *     membre, même nom et même date de naissance qu'une seule de ses identités ; papiers retrouvés par type, pays et
 *     numéro). Les autres restent « non rattachées ». Seules les couvertures sans lien sont lues : rejouable.
 * Aucun fichier de couverture n'est réécrit.
 */

$sseCoverLinksMigrate = require $root . '/bootstrap/sse_cover_export_links_migration.php';
try {
    echo "Migration sse_cover_export_links (couverture → identité et papiers)…\n";
    $sseCoverLinksMigrate($pdo, $sseCliLog);
    \App\Support\SseCoverLinkMatcher::backfill($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_cover_export_links : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_cover_export_links', $e);
}
$migrationEnsurePdo();
