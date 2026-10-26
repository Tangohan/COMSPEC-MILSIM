<?php

declare(strict_types=1);

/**
 * Étape « Colonne steam_id sur users (liaison Steam ATAK) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Colonne steam_id sur users (liaison Steam ATAK)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'steam_id'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne users.steam_id...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN steam_id varchar(20) DEFAULT NULL AFTER callsign");
}
