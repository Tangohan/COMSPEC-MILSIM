<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY (Lots 4 et 5) : BOLO et HVT nés en jeu, remontés sur Athena » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_lot45_migration.php (colonnes et index ajoutés seulement s'ils manquent), après le
 * registre d'identité (3730), rejoué sans effet ; la table des listes de surveillance vient du registre SSE (2620).
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
$atakSseIdentityLot45Migrate = require $root . '/bootstrap/atak_sse_identity_lot45_migration.php';
try {
    echo "Migration atak_sse_identity_lot45 (BOLO et HVT nés en jeu)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
    $atakSseIdentityLot45Migrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity_lot45 : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity_lot45', $e);
}
$migrationEnsurePdo();
