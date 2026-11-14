<?php

declare(strict_types=1);

/**
 * Étape « Moteur de circuits : tables workflow_instances et workflow_events » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque, depuis le seul fichier migrations/2026_11_14_010_workflow_engine.sql (CREATE TABLE IF NOT EXISTS).
 * Aucune table métier n'est modifiée : le moteur pointe vers les lignes existantes (élévations, mobilités…).
 */

$workflowSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($workflowSchema->tableExists('workflow_instances') && $workflowSchema->tableExists('workflow_events')) {
    echo "  [OK] Tables workflow_instances et workflow_events déjà présentes.\n";
} else {
    $workflowSchema->runSqlFile($root . '/migrations/2026_11_14_010_workflow_engine.sql');
    if (!$workflowSchema->tableExists('workflow_instances') || !$workflowSchema->tableExists('workflow_events')) {
        throw new RuntimeException('Création des tables du moteur de circuits impossible.');
    }
    echo "  [OK] Tables workflow_instances et workflow_events créées.\n";
}
