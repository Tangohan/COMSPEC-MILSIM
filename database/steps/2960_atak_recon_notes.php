<?php

declare(strict_types=1);

/**
 * Étape « Migration recon_notes (notes de reconnaissance) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$atakReconNotesMigrate = require $root . '/bootstrap/atak_recon_notes_migration.php';
try {
    echo "Migration recon_notes (notes de reconnaissance)...\n";
    $atakReconNotesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] recon_notes : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('recon_notes', $e);
}
$migrationEnsurePdo();
