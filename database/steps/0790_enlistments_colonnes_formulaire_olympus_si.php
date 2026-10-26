<?php

declare(strict_types=1);

/**
 * Étape « Enlistments : colonnes formulaire Olympus (si table existante sans ces colonnes) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Enlistments : colonnes formulaire Olympus (si table existante sans ces colonnes)
$stmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'age'");
if ($stmt && !$stmt->fetch()) {
    echo "Ajout des colonnes formulaire Olympus à enlistments...\n";
    $alters = [
        "ALTER TABLE enlistments ADD COLUMN age smallint unsigned DEFAULT NULL AFTER notes",
        "ALTER TABLE enlistments ADD COLUMN timezone varchar(100) DEFAULT NULL AFTER age",
        "ALTER TABLE enlistments ADD COLUMN weekly_availability varchar(255) DEFAULT NULL AFTER timezone",
        "ALTER TABLE enlistments ADD COLUMN system_config varchar(500) DEFAULT NULL AFTER weekly_availability",
        "ALTER TABLE enlistments ADD COLUMN microphone_quality varchar(20) DEFAULT NULL AFTER system_config",
        "ALTER TABLE enlistments ADD COLUMN past_milsim_experience text AFTER microphone_quality",
        "ALTER TABLE enlistments ADD COLUMN ace_acre_level varchar(50) DEFAULT NULL AFTER past_milsim_experience",
        "ALTER TABLE enlistments ADD COLUMN motivation_why_join text AFTER ace_acre_level",
        "ALTER TABLE enlistments ADD COLUMN motivation_accountability text AFTER motivation_why_join",
        "ALTER TABLE enlistments ADD COLUMN commitment_effort varchar(20) DEFAULT NULL AFTER motivation_accountability",
        "ALTER TABLE enlistments ADD COLUMN availability_wed_sat varchar(20) DEFAULT NULL AFTER commitment_effort",
        "ALTER TABLE enlistments ADD COLUMN no_ai_confirmed tinyint(1) DEFAULT 0 AFTER availability_wed_sat",
    ];
    foreach ($alters as $alter) {
        $pdo->exec($alter);
    }
    echo "Colonnes Olympus OK.\n";
}
