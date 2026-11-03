<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY (Lot 2) : contrôles reliés aux fiches FRM, page Contrôle ID du téléphone, tableau de bord
 * de l'opération » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_lot2_migration.php (colonne et index ajoutés seulement s'ils manquent), après le
 * registre (3730) et les papiers des membres (3740), rejoués sans effet s'ils sont déjà en place.
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
$atakSseIdentityMemberPapersMigrate = require $root . '/bootstrap/atak_sse_identity_member_papers_migration.php';
$atakSseIdentityLot2Migrate = require $root . '/bootstrap/atak_sse_identity_lot2_migration.php';
try {
    echo "Migration atak_sse_identity_lot2 (contrôles et fiches FRM, Contrôle ID du téléphone, tableau de bord)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
    $atakSseIdentityMemberPapersMigrate($pdo, $sseCliLog);
    $atakSseIdentityLot2Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity_lot2 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity_lot2', $e);
}
$migrationEnsurePdo();
