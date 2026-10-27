<?php

declare(strict_types=1);

/**
 * Étape « Colonne default_map_slug sur tenant_atak_config (si absente) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Colonne default_map_slug sur tenant_atak_config (si absente)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_atak_config' AND COLUMN_NAME = 'default_map_slug'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne tenant_atak_config.default_map_slug...\n";
    $pdo->exec("ALTER TABLE tenant_atak_config ADD COLUMN default_map_slug varchar(50) DEFAULT 'altis' AFTER instructions");
}
