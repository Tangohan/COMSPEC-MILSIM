<?php

declare(strict_types=1);

/**
 * Gestion des parties détectées en jeu (clé de mission ATAK « nom@carte#AAAAMMJJHHMM »). Idempotente.
 *
 *   atak_debrief_ops (colonnes ajoutées) : type, catégorie, partie ignorée ou supprimée (logiquement, réversible),
 *       « compter quand même » malgré une règle, rattachement à une opération planifiée (mission_plans), un cycle de
 *       mission (theatre_mission_cycles) ou un événement (community_events).
 *   atak_op_types, atak_op_ignore_rules, atak_op_categories : migrations/2026_11_02_130_atak_played_operations.sql.
 *
 * Lancée par database/steps/3580_atak_played_operations.php et à la demande par AtakPlayedOpRepository::ready().
 * Aucune ligne créée, modifiée ni supprimée.
 */
function run_atak_played_ops_migration(PDO $pdo): void
{
    $schema = new \App\Support\Migrations\SchemaInspector($pdo);
    $tables = ['atak_debrief_ops', 'atak_op_types', 'atak_op_ignore_rules', 'atak_op_categories'];
    $missing = array_values(array_filter($tables, static fn (string $t): bool => !$schema->tableExists($t)));
    if ($missing !== []) {
        $schema->runSqlFile(dirname(__DIR__) . '/migrations/2026_11_02_130_atak_played_operations.sql');
        echo '  [OK] Tables créées : ' . implode(', ', $missing) . ".\n";
    }
    $columns = [
        'type_id' => 'INT UNSIGNED DEFAULT NULL',
        'category' => 'VARCHAR(80) DEFAULT NULL',
        'ignored_at' => 'DATETIME DEFAULT NULL',
        'deleted_at' => 'DATETIME DEFAULT NULL',
        'count_anyway' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'plan_id' => 'INT UNSIGNED DEFAULT NULL',
        'cycle_id' => 'INT UNSIGNED DEFAULT NULL',
        'event_id' => 'INT UNSIGNED DEFAULT NULL',
        'link_mode' => 'VARCHAR(12) DEFAULT NULL',
    ];
    $added = [];
    foreach ($columns as $name => $def) {
        if ($schema->addColumnIfMissing('atak_debrief_ops', $name, $def)) {
            $added[] = $name;
        }
    }
    echo $added !== []
        ? '  [OK] Colonnes atak_debrief_ops ajoutées : ' . implode(', ', $added) . ".\n"
        : "  [OK] Parties détectées : schéma déjà à jour.\n";
}
