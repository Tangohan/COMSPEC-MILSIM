<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY : pays réels stylisés pour l'univers réaliste-simulation » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_real_countries_migration.php (colonne real_sim ajoutée si elle manque, anciens pays réels
 * ajoutés d'office passés en pays stylisés), après le registre (3730) et les papiers des membres (3740), rejoués sans
 * effet s'ils sont en place.
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
$atakSseIdentityMemberPapersMigrate = require $root . '/bootstrap/atak_sse_identity_member_papers_migration.php';
$atakSseIdentityRealCountriesMigrate = require $root . '/bootstrap/atak_sse_identity_real_countries_migration.php';
try {
    echo "Migration atak_sse_identity_real_countries (pays réels stylisés, univers réaliste-simulation)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
    $atakSseIdentityMemberPapersMigrate($pdo, $sseCliLog);
    $atakSseIdentityRealCountriesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity_real_countries : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity_real_countries', $e);
}
$migrationEnsurePdo();
