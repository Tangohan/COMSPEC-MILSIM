<?php

declare(strict_types=1);

/**
 * Étape « Emplois : catalogues de communauté » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : seules les tables absentes sont
 * créées (migrations/2026_11_02_060_job_catalog_collections.sql), une relance ne change rien. Aucun emploi existant
 * n’est modifié : les catalogues officiels par pays restent servis depuis config/job_catalog.
 */

$jobCatalogSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$jobCatalogTables = ['job_catalog_collections', 'job_catalog_collection_entries'];
$jobCatalogMissing = array_values(array_filter($jobCatalogTables, static fn (string $t): bool => !$jobCatalogSchema->tableExists($t)));
if ($jobCatalogMissing === []) {
    echo "  [OK] Tables des catalogues d’emplois de communauté déjà présentes.\n";
} else {
    $jobCatalogSchema->runSqlFile($root . '/migrations/2026_11_02_060_job_catalog_collections.sql');
    echo '  [OK] Tables créées : ' . implode(', ', $jobCatalogMissing) . ".\n";
}
