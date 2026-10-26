<?php

declare(strict_types=1);

/**
 * Étape « Migration personnel_profiles.rank_display_override » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Grade affiché : override explicite (texte libre déprécié au profit du référentiel)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND COLUMN_NAME = 'rank_display_override'");
if ($stmt && !$stmt->fetch()) {
    echo "Migration personnel_profiles.rank_display_override...\n";
    $pdo->exec('ALTER TABLE personnel_profiles ADD COLUMN rank_display_override varchar(100) DEFAULT NULL COMMENT \'Exception métier; sinon grades + overrides\' AFTER rank_display');
}
