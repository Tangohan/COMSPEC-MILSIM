<?php

declare(strict_types=1);

/**
 * Étape « Identité réelle + couverture, photo transmise au jeu » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : délègue à
 * bootstrap/sse_identity_dual_photo_migration.php, rejouée sans effet si tout est en place.
 */

$sseIdentityDualPhotoMigrate = require $root . '/bootstrap/sse_identity_dual_photo_migration.php';
try {
    echo "Migration sse_identity_dual_photo (identité réelle avec la couverture, photo pour le jeu)…\n";
    $sseIdentityDualPhotoMigrate($pdo, $sseCliLog);
} catch (Throwable $e) {
    echo '  [ATTENTION] sse_identity_dual_photo : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('sse_identity_dual_photo', $e);
}
$migrationEnsurePdo();
