<?php

declare(strict_types=1);

/**
 * Étape « Emplois : prérequis et dérogations » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : seules les tables absentes sont
 * créées (migrations/2026_11_02_020_personnel_job_role_prerequisites.sql), une relance ne change rien. Aucun emploi
 * ni aucune attribution existante n’est modifié : un emploi sans prérequis reste attribuable comme avant.
 * Sans la table personnel_job_roles (module des emplois jamais installé), l’étape ne fait rien.
 */

$jobPrereqSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$jobPrereqSchema->tableExists('personnel_job_roles')) {
    echo "  [OK] Table personnel_job_roles absente : rien à faire.\n";
} else {
    $jobPrereqTables = ['personnel_job_role_requirements', 'personnel_job_role_waivers'];
    $jobPrereqMissing = array_values(array_filter($jobPrereqTables, static fn (string $t): bool => !$jobPrereqSchema->tableExists($t)));
    if ($jobPrereqMissing === []) {
        echo "  [OK] Tables des prérequis et des dérogations déjà présentes.\n";
    } else {
        $jobPrereqSchema->runSqlFile($root . '/migrations/2026_11_02_020_personnel_job_role_prerequisites.sql');
        echo '  [OK] Tables créées : ' . implode(', ', $jobPrereqMissing) . ".\n";
    }
}
