<?php

declare(strict_types=1);

/**
 * Étape « Emplois : hiérarchie descriptive (personnel_job_role_structure) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : migrations/20261029223000_personnel_job_role_structure.sql (CREATE TABLE IF NOT EXISTS).
 */

$jobStructureSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($jobStructureSchema->tableExists('personnel_job_role_structure')) {
    echo "  [OK] Table personnel_job_role_structure déjà présente.\n";
} else {
    $jobStructureSchema->runSqlFile($root . '/migrations/20261029223000_personnel_job_role_structure.sql');
    echo "  [OK] Table personnel_job_role_structure créée.\n";
}
