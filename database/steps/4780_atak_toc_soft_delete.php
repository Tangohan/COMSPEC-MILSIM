<?php

declare(strict_types=1);

/**
 * Étape « Poste TOC web : suppression douce et purge de la carte » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque colonne, index et table n'est
 * créé que s'il manque. Ajoute deleted_at, deleted_by et delete_batch aux marqueurs et aux tracés de la carte (corbeille
 * de 15 minutes, pierres tombales envoyées au jeu) et crée atak_toc_purges (état et journal des purges par lots).
 * Aucune donnée existante n'est lue ni modifiée. Compatible SQLite et MySQL (aucune clé unique ajoutée après coup).
 * Retour arrière : migrations/rollback/atak_toc_purges_down.sql.
 */

$tocPurgeSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$tocPurgeAdded = [];
foreach (['atak_markers' => 'idx_atak_markers_trash', 'atak_map_shapes' => 'idx_atak_map_shapes_trash'] as $tocPurgeTable => $tocPurgeIndex) {
    if (!$tocPurgeSchema->tableExists($tocPurgeTable)) {
        echo '  [OK] Table ' . $tocPurgeTable . " absente : rien à ajouter.\n";
        continue;
    }
    if ($tocPurgeSchema->addColumnIfMissing($tocPurgeTable, 'deleted_at', 'DATETIME NULL DEFAULT NULL')) {
        $tocPurgeAdded[] = $tocPurgeTable . '.deleted_at';
    }
    if ($tocPurgeSchema->addColumnIfMissing($tocPurgeTable, 'deleted_by', 'INT UNSIGNED NULL DEFAULT NULL')) {
        $tocPurgeAdded[] = $tocPurgeTable . '.deleted_by';
    }
    if ($tocPurgeSchema->addColumnIfMissing($tocPurgeTable, 'delete_batch', 'VARCHAR(24) NULL DEFAULT NULL')) {
        $tocPurgeAdded[] = $tocPurgeTable . '.delete_batch';
    }
    if ($tocPurgeSchema->addIndexIfMissing($tocPurgeTable, $tocPurgeIndex, ['tenant_id', 'map_id', 'deleted_at'])) {
        $tocPurgeAdded[] = $tocPurgeIndex;
    }
}
if (!$tocPurgeSchema->tableExists('atak_toc_purges')) {
    $tocPurgeSchema->runSqlFile($root . '/migrations/2026_11_15_140_atak_toc_purges.sql');
    if (!$tocPurgeSchema->tableExists('atak_toc_purges')) {
        throw new RuntimeException('Création de la table atak_toc_purges impossible.');
    }
    $tocPurgeAdded[] = 'atak_toc_purges';
}
foreach (['atak_markers', 'atak_map_shapes'] as $tocPurgeTable) {
    if ($tocPurgeSchema->tableExists($tocPurgeTable)) {
        foreach (['deleted_at', 'deleted_by', 'delete_batch'] as $tocPurgeColumn) {
            if (!$tocPurgeSchema->columnExists($tocPurgeTable, $tocPurgeColumn)) {
                throw new RuntimeException('Colonne ' . $tocPurgeTable . '.' . $tocPurgeColumn . ' impossible à créer.');
            }
        }
    }
}
echo $tocPurgeAdded === []
    ? "  [OK] Suppression douce et purge du poste TOC déjà en place.\n"
    : '  [OK] Ajouté : ' . implode(', ', $tocPurgeAdded) . ".\n";
