<?php

declare(strict_types=1);

/**
 * Étape « Moteur de circuits : circuits d'avis composés par la communauté » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table workflow_circuits n'est créée
 * que si elle manque, depuis le seul fichier migrations/2026_11_14_020_workflow_circuits.sql (CREATE TABLE IF NOT EXISTS).
 */

$circuitSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($circuitSchema->tableExists('workflow_circuits')) {
    echo "  [OK] Table workflow_circuits déjà présente.\n";
} else {
    $circuitSchema->runSqlFile($root . '/migrations/2026_11_14_020_workflow_circuits.sql');
    if (!$circuitSchema->tableExists('workflow_circuits')) {
        throw new RuntimeException('Création de la table workflow_circuits impossible.');
    }
    echo "  [OK] Table workflow_circuits créée.\n";
}
