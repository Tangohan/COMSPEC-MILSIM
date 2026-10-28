<?php

declare(strict_types=1);

/**
 * Étape « Users : colonnes référentiel grades (nationalité, format préféré, catégorie pro) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Users : colonnes référentiel grades (nationalité, format préféré, catégorie pro)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_grade_format'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout colonnes users (nationality_code, preferred_grade_format, professional_category_code)...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN nationality_code VARCHAR(10) DEFAULT NULL AFTER email");
    $pdo->exec("ALTER TABLE users ADD COLUMN preferred_grade_format ENUM('classic','otan','hybrid') NOT NULL DEFAULT 'classic' AFTER nationality_code");
    $pdo->exec("ALTER TABLE users ADD COLUMN professional_category_code VARCHAR(50) DEFAULT NULL AFTER grade_id");
    echo "Colonnes users référentiel grades OK.\n";
}
