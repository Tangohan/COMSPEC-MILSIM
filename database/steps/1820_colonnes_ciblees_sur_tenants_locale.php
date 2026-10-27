<?php

declare(strict_types=1);

/**
 * Étape « Migration tenants.default_timezone / default_locale / country_code » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Colonnes ciblées sur tenants (locale / pays) — idempotent
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'default_timezone'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration tenants.default_timezone / default_locale / country_code...\n";
    $pdo->exec("ALTER TABLE tenants ADD COLUMN default_timezone varchar(64) DEFAULT 'Europe/Paris'");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN default_locale char(5) DEFAULT 'fr-FR'");
    $pdo->exec("ALTER TABLE tenants ADD COLUMN country_code char(2) DEFAULT NULL");
}
