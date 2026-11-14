<?php

declare(strict_types=1);

/**
 * Étape « JNET : tâches et pages de l'espace d'une unité, modules affichés » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_14_050_jnet_unit_workspace.sql) et la colonne
 * hidden_modules de jnet_unit_spaces n'est ajoutée que si elle manque. Retour arrière : migrations/rollback/jnet_unit_workspace_down.sql.
 */

$jnetWorkspaceSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$jnetWorkspaceTables = ['jnet_unit_tasks', 'jnet_unit_pages', 'jnet_unit_page_reactions', 'jnet_unit_page_comments', 'jnet_unit_page_votes'];
$jnetWorkspaceMissing = array_values(array_filter($jnetWorkspaceTables, static fn (string $t): bool => !$jnetWorkspaceSchema->tableExists($t)));
if ($jnetWorkspaceMissing === []) {
    echo "  [OK] Tables de l'espace d'unité JNET déjà présentes.\n";
} else {
    $jnetWorkspaceSchema->runSqlFile($root . '/migrations/2026_11_14_050_jnet_unit_workspace.sql');
    foreach ($jnetWorkspaceTables as $jnetWorkspaceTable) {
        if (!$jnetWorkspaceSchema->tableExists($jnetWorkspaceTable)) {
            throw new RuntimeException('Création de la table ' . $jnetWorkspaceTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $jnetWorkspaceMissing) . ".\n";
}
if ($jnetWorkspaceSchema->tableExists('jnet_unit_spaces')) {
    if ($jnetWorkspaceSchema->addColumnIfMissing('jnet_unit_spaces', 'hidden_modules', 'VARCHAR(80) DEFAULT NULL')) {
        echo "  [OK] Colonne jnet_unit_spaces.hidden_modules ajoutée.\n";
    } else {
        echo "  [OK] Colonne jnet_unit_spaces.hidden_modules déjà présente.\n";
    }
}
