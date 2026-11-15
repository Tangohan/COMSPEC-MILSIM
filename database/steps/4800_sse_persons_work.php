<?php

declare(strict_types=1);

/**
 * Étape « SSE Personnes : classification, verrouillage, clôture, dernières ouvertures » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque colonne de sse_persons (classification,
 * locked_at, locked_by, lock_reason, lock_until, closed_at, closed_by, close_reason) n'est ajoutée que si elle manque, et la
 * table sse_person_views n'est créée que si elle manque (unicité dans le CREATE TABLE). Aucune donnée modifiée : sans cette
 * étape, la page Personnes s'affiche en lecture seule (classer, verrouiller et clore sont désactivés avec un message).
 * Retour arrière : migrations/rollback/sse_persons_work_down.sql.
 */

$personWorkSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$personWorkColumns = [
    'classification' => 'VARCHAR(16) DEFAULT NULL',
    'locked_at' => 'DATETIME DEFAULT NULL',
    'locked_by' => 'INT UNSIGNED DEFAULT NULL',
    'lock_reason' => 'VARCHAR(500) DEFAULT NULL',
    'lock_until' => 'DATETIME DEFAULT NULL',
    'closed_at' => 'DATETIME DEFAULT NULL',
    'closed_by' => 'INT UNSIGNED DEFAULT NULL',
    'close_reason' => 'VARCHAR(500) DEFAULT NULL',
];
if (!$personWorkSchema->tableExists('sse_persons')) {
    echo "  [OK] Table sse_persons absente : les colonnes seront ajoutées à la prochaine exécution.\n";
} else {
    $personWorkAdded = [];
    foreach ($personWorkColumns as $personWorkColumn => $personWorkDefinition) {
        if ($personWorkSchema->addColumnIfMissing('sse_persons', $personWorkColumn, $personWorkDefinition)) {
            $personWorkAdded[] = $personWorkColumn;
        }
    }
    if ($personWorkSchema->addIndexIfMissing('sse_persons', 'idx_sse_persons_closed', ['tenant_id', 'closed_at'])) {
        $personWorkAdded[] = 'idx_sse_persons_closed';
    }
    foreach (array_keys($personWorkColumns) as $personWorkColumn) {
        if (!$personWorkSchema->columnExists('sse_persons', $personWorkColumn)) {
            throw new RuntimeException('Colonne sse_persons.' . $personWorkColumn . ' impossible à créer.');
        }
    }
    echo $personWorkAdded === []
        ? "  [OK] Colonnes de classification, verrouillage et clôture déjà présentes.\n"
        : '  [OK] Ajouté à sse_persons : ' . implode(', ', $personWorkAdded) . ".\n";
}

if ($personWorkSchema->tableExists('sse_person_views')) {
    echo "  [OK] Table sse_person_views déjà présente.\n";
} else {
    $personWorkSchema->runSqlFile($root . '/migrations/2026_11_17_111_sse_person_views.sql');
    if (!$personWorkSchema->tableExists('sse_person_views')) {
        throw new RuntimeException('Création de la table sse_person_views impossible.');
    }
    echo "  [OK] Table créée : sse_person_views.\n";
}
