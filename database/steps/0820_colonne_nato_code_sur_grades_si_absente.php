<?php

declare(strict_types=1);

/**
 * Étape « Colonne nato_code sur grades (si absente) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Colonne nato_code sur grades (si absente)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'nato_code'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonne grades.nato_code...\n";
    $pdo->exec("ALTER TABLE grades ADD COLUMN nato_code varchar(10) DEFAULT NULL");
}
