<?php

declare(strict_types=1);

/**
 * Étape « Dossiers d'intérêt SSE : modification suivie, corbeille, éléments liés » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque colonne n'est ajoutée que si elle
 * manque (version, classification, case_type, assignee_user_id, due_at, tags, archive_from_status, deleted_at, deleted_by,
 * delete_reason sur sse_interest_cases) et chaque table n'est créée que si elle manque (historique champ par champ, éléments
 * liés, abonnés ; migrations/2026_11_18_100_sse_interest_case_suite.sql). Aucune donnée n'est modifiée : les dossiers existants
 * gardent leur état, passent à la version 1 et à la classification « interne » (valeur par défaut des colonnes).
 * Sans cette étape le module reste utilisable en mode dégradé (liste, création, fiche, changement d'état) et le dit à l'écran.
 * Retour arrière : migrations/rollback/sse_interest_case_suite_down.sql.
 */

$interestSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$interestAdded = [];
if (!$interestSchema->tableExists('sse_interest_cases')) {
    echo "  [OK] Table sse_interest_cases absente : les colonnes seront créées avec elle.\n";
} else {
    $interestColumns = [
        'version' => 'INT UNSIGNED NOT NULL DEFAULT 1',
        'classification' => "VARCHAR(20) NOT NULL DEFAULT 'interne'",
        'case_type' => 'VARCHAR(40) DEFAULT NULL',
        'assignee_user_id' => 'INT UNSIGNED DEFAULT NULL',
        'due_at' => 'DATETIME DEFAULT NULL',
        'tags' => 'VARCHAR(500) DEFAULT NULL',
        'archive_from_status' => 'VARCHAR(40) DEFAULT NULL',
        'deleted_at' => 'DATETIME DEFAULT NULL',
        'deleted_by' => 'INT UNSIGNED DEFAULT NULL',
        'delete_reason' => 'VARCHAR(300) DEFAULT NULL',
    ];
    foreach ($interestColumns as $interestColumn => $interestDefinition) {
        if ($interestSchema->addColumnIfMissing('sse_interest_cases', $interestColumn, $interestDefinition)) {
            $interestAdded[] = $interestColumn;
        }
    }
    if ($interestSchema->addIndexIfMissing('sse_interest_cases', 'idx_sse_interest_trash', ['tenant_id', 'deleted_at'])) {
        $interestAdded[] = 'idx_sse_interest_trash';
    }
    foreach (array_keys($interestColumns) as $interestColumn) {
        if (!$interestSchema->columnExists('sse_interest_cases', $interestColumn)) {
            throw new RuntimeException('Colonne sse_interest_cases.' . $interestColumn . ' impossible à créer.');
        }
    }
}

$interestTables = ['sse_interest_case_history', 'sse_interest_case_links', 'sse_interest_case_watchers'];
$interestMissing = array_values(array_filter($interestTables, static fn (string $t): bool => !$interestSchema->tableExists($t)));
if ($interestMissing !== []) {
    $interestSchema->runSqlFile($root . '/migrations/2026_11_18_100_sse_interest_case_suite.sql');
    foreach ($interestTables as $interestTable) {
        if (!$interestSchema->tableExists($interestTable)) {
            throw new RuntimeException('Création de la table ' . $interestTable . ' impossible.');
        }
    }
    $interestAdded = array_merge($interestAdded, $interestMissing);
}
echo $interestAdded === []
    ? "  [OK] Dossiers d'intérêt : colonnes et tables déjà présentes.\n"
    : '  [OK] Ajouté : ' . implode(', ', $interestAdded) . ".\n";
