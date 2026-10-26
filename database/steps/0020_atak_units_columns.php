<?php

declare(strict_types=1);

/**
 * Étape « Colonnes manquantes de atak_units (military_id, pos_x, pos_y) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// CREATE TABLE IF NOT EXISTS ne met jamais à jour une table existante : combler les colonnes critiques.
require_once $root . '/bootstrap/schema_ensure_column.php';
echo "Vérification colonnes manquantes (ensure schema drift)…\n";
$migrationFlush();
$ensureAdded = schema_ensure_columns($pdo, 'atak_units', [
    'military_id' => '`military_id` varchar(32) DEFAULT NULL AFTER `call_sign`',
    'pos_x' => '`pos_x` decimal(15,4) DEFAULT NULL AFTER `heading`',
    'pos_y' => '`pos_y` decimal(15,4) DEFAULT NULL AFTER `pos_x`',
]);
if ($ensureAdded === 0) {
    echo "  [OK] atak_units : military_id, pos_x, pos_y déjà présentes\n";
} else {
    echo "  [OK] atak_units : {$ensureAdded} colonne(s) comblée(s)\n";
}
$migrationFlush();
