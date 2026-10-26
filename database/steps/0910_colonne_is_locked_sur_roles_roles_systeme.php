<?php

declare(strict_types=1);

/**
 * Étape « Colonne is_locked sur roles (rôles système non modifiables par admin org) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Colonne is_locked sur roles (rôles système non modifiables par admin org)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles' AND COLUMN_NAME = 'is_locked'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne roles.is_locked...\n";
    $pdo->exec("ALTER TABLE roles ADD COLUMN is_locked tinyint(1) DEFAULT 0 AFTER is_system");
}
