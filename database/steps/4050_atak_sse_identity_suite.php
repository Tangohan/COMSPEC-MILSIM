<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY : clé biométrique des fiches et identités, carte militaire » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_suite_migration.php, après le registre, rejoué sans effet s'il est en place.
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
$atakSseIdentitySuiteMigrate = require $root . '/bootstrap/atak_sse_identity_suite_migration.php';
try {
    echo "Migration atak_sse_identity_suite (clé biométrique, carte militaire)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
    $atakSseIdentitySuiteMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity_suite : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity_suite', $e);
}
$migrationEnsurePdo();
