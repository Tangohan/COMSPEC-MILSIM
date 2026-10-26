<?php

declare(strict_types=1);

/**
 * Étape « Colonne arma_callsign sur user_profiles (liaison Arma ATAK) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Colonne arma_callsign sur user_profiles (liaison Arma ATAK)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_profiles' AND COLUMN_NAME = 'arma_callsign'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne user_profiles.arma_callsign...\n";
    $pdo->exec("ALTER TABLE user_profiles ADD COLUMN arma_callsign varchar(100) DEFAULT NULL AFTER language");
}
