<?php

declare(strict_types=1);

/**
 * Étape « Compétences : explication, liens vers les autres modules et parcours enregistrés » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base est vérifié
 * avant toute modification, une relance ne change rien.
 * - competency_grade_requirements.description / validation_mode (NULL = rien ne change pour les compétences existantes) ;
 * - tables competency_requirement_links et competency_pathways (migrations/2026_11_01_030_competency_links_pathways.sql).
 */

$competencyLinksSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$competencyLinksTables = ['competency_grade_requirements', 'competency_requirement_links', 'competency_pathways'];
$competencyLinksMissing = array_filter($competencyLinksTables, static fn (string $t): bool => !$competencyLinksSchema->tableExists($t));
if ($competencyLinksMissing === []) {
    echo "  [OK] Tables du référentiel des compétences déjà présentes.\n";
} else {
    $competencyLinksSchema->runSqlFile($root . '/migrations/2026_11_01_030_competency_links_pathways.sql');
    echo '  [OK] Tables créées : ' . implode(', ', $competencyLinksMissing) . ".\n";
}
foreach (['description' => 'TEXT NULL DEFAULT NULL', 'validation_mode' => 'VARCHAR(30) NULL DEFAULT NULL'] as $competencyLinksColumn => $competencyLinksDefinition) {
    echo $competencyLinksSchema->addColumnIfMissing('competency_grade_requirements', $competencyLinksColumn, $competencyLinksDefinition)
        ? "  [OK] Colonne competency_grade_requirements.{$competencyLinksColumn} ajoutée.\n"
        : "  [OK] Colonne competency_grade_requirements.{$competencyLinksColumn} déjà présente.\n";
}
