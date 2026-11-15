<?php

declare(strict_types=1);

/**
 * Étape « Cycle de 24 h des marqueurs et missions archivées » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_130_atak_marker_cycles.sql). Aucune donnée existante
 * n'est lue ni modifiée : l'archivage est fait ensuite, par lots, par la tâche planifiée « atak_marker_cycle ».
 * Retour arrière : migrations/rollback/atak_marker_cycles_down.sql.
 */

$markerCycleSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$markerCycleTables = ['atak_marker_cycle_settings', 'atak_marker_archives', 'atak_marker_archive_items'];
$markerCycleMissing = array_values(array_filter($markerCycleTables, static fn (string $t): bool => !$markerCycleSchema->tableExists($t)));
if ($markerCycleMissing === []) {
    echo "  [OK] Tables du cycle des marqueurs déjà présentes.\n";
} else {
    $markerCycleSchema->runSqlFile($root . '/migrations/2026_11_15_130_atak_marker_cycles.sql');
    foreach ($markerCycleTables as $markerCycleTable) {
        if (!$markerCycleSchema->tableExists($markerCycleTable)) {
            throw new RuntimeException('Création de la table ' . $markerCycleTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $markerCycleMissing) . ".\n";
}
