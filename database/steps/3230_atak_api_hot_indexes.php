<?php

declare(strict_types=1);

/**
 * Étape « Index des lectures fréquentes de la liaison (atak_pings, atak_units) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Mêmes index que scripts/sql/api-hot-indexes.sql (déjà passé à la main en production : l’étape le constate).
 * Une table ou une colonne absente est ignorée sans erreur.
 */

$atakHotSchema = new \App\Support\Migrations\SchemaInspector($pdo);
foreach ([
    ['atak_pings', 'idx_atak_pings_tenant_map_created', ['tenant_id', 'map_id', 'created_at']],
    ['atak_units', 'idx_atak_units_tenant_map_callsign', ['tenant_id', 'map_id', 'call_sign']],
] as [$atakHotTable, $atakHotIndex, $atakHotColumns]) {
    if ($atakHotSchema->addIndexIfMissing($atakHotTable, $atakHotIndex, $atakHotColumns)) {
        echo "  [OK] Index $atakHotIndex posé sur $atakHotTable.\n";
    } elseif ($atakHotSchema->indexExists($atakHotTable, $atakHotIndex)) {
        echo "  [OK] Index $atakHotIndex déjà présent.\n";
    } else {
        echo "  [OK] $atakHotTable absente ou incomplète : index $atakHotIndex ignoré.\n";
    }
}
