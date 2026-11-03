<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY (Lot 6) : atelier de documents, kits types, préparation de mission en masse, briefs de
 * couverture » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_lot6_migration.php (colonnes, index et tables ajoutés seulement s'ils manquent), après
 * le registre (3730), les papiers des membres (3740) et le lot 2 (3750), rejoués sans effet s'ils sont en place.
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
$atakSseIdentityMemberPapersMigrate = require $root . '/bootstrap/atak_sse_identity_member_papers_migration.php';
$atakSseIdentityLot2Migrate = require $root . '/bootstrap/atak_sse_identity_lot2_migration.php';
$atakSseIdentityLot6Migrate = require $root . '/bootstrap/atak_sse_identity_lot6_migration.php';
try {
    echo "Migration atak_sse_identity_lot6 (atelier de documents, kits types, préparation de mission, briefs)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
    $atakSseIdentityMemberPapersMigrate($pdo, $sseCliLog);
    $atakSseIdentityLot2Migrate($pdo, $sseCliLog);
    $atakSseIdentityLot6Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity_lot6 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity_lot6', $e);
}
$migrationEnsurePdo();
