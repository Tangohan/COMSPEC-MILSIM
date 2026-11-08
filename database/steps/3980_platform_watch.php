<?php

declare(strict_types=1);

/**
 * Étape « Surveillance de la plateforme : charge par route et par communauté, alertes d'activité anormale du mod »
 * (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l'état réel de la base est vérifié
 * avant toute création, une relance ne change rien.
 * Source unique des tables : App\Services\Watch\WatchSchema. Conservation courte, purge bornée par lots dans la tâche
 * automatique « platform_watch » (3 jours de minutes, 8 jours par heure et communauté, 30 jours d'alertes).
 */

$watchSchema = new \App\Support\Migrations\SchemaInspector($pdo);
foreach (\App\Services\Watch\WatchSchema::tables() as $watchTable => $watchSql) {
    if ($watchSchema->tableExists($watchTable)) {
        echo "  [OK] Table {$watchTable} déjà présente.\n";
        continue;
    }
    $pdo->exec($watchSql);
    echo "  [OK] Table {$watchTable} créée.\n";
}
