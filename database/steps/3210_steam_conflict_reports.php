<?php

declare(strict_types=1);

/**
 * Étape « Steam en double : signalements (steam_conflict_reports) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : bootstrap/steam_conflict_reports_migration.php (CREATE TABLE IF NOT EXISTS), aussi lancée à la demande.
 */

$steamReportsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($steamReportsSchema->tableExists('steam_conflict_reports')) {
    echo "  [OK] Table steam_conflict_reports déjà présente.\n";
} else {
    $steamReportsMigrate = require $root . '/bootstrap/steam_conflict_reports_migration.php';
    $steamReportsMigrate($pdo);
    echo "  [OK] Table steam_conflict_reports créée.\n";
}
