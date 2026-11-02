<?php

declare(strict_types=1);

/**
 * Étape « Emplois : qualifications reliées des catalogues de communauté » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Ajoute la colonne qualification_links_json
 * (texte cité par l’emploi => qualification du référentiel de la communauté) aux emplois des catalogues de
 * communauté. Idempotente : une colonne déjà présente n’est pas modifiée ; aucune donnée existante ne change.
 */

$jobLinksSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$jobLinksSchema->tableExists('job_catalog_collection_entries')) {
    echo "  [OK] Pas encore de catalogues de communauté : colonne ajoutée avec leur étape.\n";
} elseif ($jobLinksSchema->addColumnIfMissing('job_catalog_collection_entries', 'qualification_links_json', "text DEFAULT NULL COMMENT 'Qualifications citées reliées au référentiel : {texte: id de la fiche}' AFTER `qualifications_json`")) {
    echo "  [OK] Colonne qualification_links_json ajoutée aux emplois des catalogues de communauté.\n";
} else {
    echo "  [OK] Colonne qualification_links_json déjà présente.\n";
}
