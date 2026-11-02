<?php

declare(strict_types=1);

/**
 * Étape « Fonctions : abrégé, icône, étiquettes et fonctions du site masquées » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base est vérifié
 * avant toute modification, une relance ne change rien.
 * - personnel_job_roles.abbreviation (code court, unique par communauté) et personnel_job_roles.icon (NULL = rien ne
 *   change à l’affichage des fonctions existantes) ;
 * - tables personnel_job_role_tags, personnel_job_role_tag_links et personnel_job_role_catalog_hidden
 *   (migrations/2026_11_02_010_personnel_job_role_enrichment.sql).
 * Sans la table personnel_job_roles (module des emplois jamais installé), l’étape ne fait rien.
 */

$jobEnrichSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$jobEnrichSchema->tableExists('personnel_job_roles')) {
    echo "  [OK] Table personnel_job_roles absente : rien à faire.\n";
} else {
    foreach (['abbreviation' => 'VARCHAR(8) NULL DEFAULT NULL', 'icon' => 'VARCHAR(40) NULL DEFAULT NULL'] as $jobEnrichColumn => $jobEnrichDefinition) {
        echo $jobEnrichSchema->addColumnIfMissing('personnel_job_roles', $jobEnrichColumn, $jobEnrichDefinition)
            ? "  [OK] Colonne personnel_job_roles.{$jobEnrichColumn} ajoutée.\n"
            : "  [OK] Colonne personnel_job_roles.{$jobEnrichColumn} déjà présente.\n";
    }
    if ($jobEnrichSchema->indexExists('personnel_job_roles', 'uk_pjr_tenant_abbreviation')) {
        echo "  [OK] Index uk_pjr_tenant_abbreviation déjà présent.\n";
    } else {
        // Plusieurs NULL restent permis : seul un abrégé saisi doit être unique dans la communauté.
        $pdo->exec($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'CREATE UNIQUE INDEX `uk_pjr_tenant_abbreviation` ON `personnel_job_roles` (`tenant_id`, `abbreviation`)'
            : 'ALTER TABLE `personnel_job_roles` ADD UNIQUE INDEX `uk_pjr_tenant_abbreviation` (`tenant_id`, `abbreviation`)');
        echo "  [OK] Index uk_pjr_tenant_abbreviation créé.\n";
    }
    $jobEnrichTables = ['personnel_job_role_tags', 'personnel_job_role_tag_links', 'personnel_job_role_catalog_hidden'];
    $jobEnrichMissing = array_values(array_filter($jobEnrichTables, static fn (string $t): bool => !$jobEnrichSchema->tableExists($t)));
    if ($jobEnrichMissing === []) {
        echo "  [OK] Tables des étiquettes et des fonctions masquées déjà présentes.\n";
    } else {
        $jobEnrichSchema->runSqlFile($root . '/migrations/2026_11_02_010_personnel_job_role_enrichment.sql');
        echo '  [OK] Tables créées : ' . implode(', ', $jobEnrichMissing) . ".\n";
    }
}
