<?php

declare(strict_types=1);

/**
 * Étape « COMSPEC IDENTITY : images des papiers (signature du titulaire, fond du gabarit personnalisé) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/atak_sse_identity_images_migration.php, après le registre et le lot 6, rejoués sans effet s'ils sont en
 * place.
 */

$atakSseIdentityMigrate = require $root . '/bootstrap/atak_sse_identity_migration.php';
$atakSseIdentityLot6Migrate = require $root . '/bootstrap/atak_sse_identity_lot6_migration.php';
$atakSseIdentityImagesMigrate = require $root . '/bootstrap/atak_sse_identity_images_migration.php';
try {
    echo "Migration atak_sse_identity_images (signature, fond de gabarit)…\n";
    $atakSseIdentityMigrate($pdo, $sseCliLog);
    $atakSseIdentityLot6Migrate($pdo, $sseCliLog);
    $atakSseIdentityImagesMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] atak_sse_identity_images : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('atak_sse_identity_images', $e);
}
$migrationEnsurePdo();
