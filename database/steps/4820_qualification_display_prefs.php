<?php

declare(strict_types=1);

/**
 * Étape « Qualifications : visibles sur Arma et sur le profil, au choix » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_20_100_qualification_display_prefs.sql). Aucune colonne ajoutée, aucune
 * donnée modifiée : sans cette table, toutes les qualifications restent visibles et les interrupteurs de la fiche sont désactivés.
 * Compatible SQLite et MySQL (unicité dans le CREATE TABLE). Retour arrière :
 * migrations/rollback/qualification_display_prefs_down.sql.
 */

$qualDisplaySchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($qualDisplaySchema->tableExists('personnel_qualification_display')) {
    echo "  [OK] Table personnel_qualification_display déjà présente.\n";
} else {
    $qualDisplaySchema->runSqlFile($root . '/migrations/2026_11_20_100_qualification_display_prefs.sql');
    if (!$qualDisplaySchema->tableExists('personnel_qualification_display')) {
        throw new RuntimeException('Création de la table personnel_qualification_display impossible.');
    }
    echo "  [OK] Table créée : personnel_qualification_display.\n";
}
