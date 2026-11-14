<?php

declare(strict_types=1);

/**
 * Étape « Positions administratives créées par la communauté » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table tenant_administrative_positions
 * n'est créée que si elle manque, depuis migrations/2026_11_14_040_tenant_administrative_positions.sql.
 */

$adminPositionsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($adminPositionsSchema->tableExists('tenant_administrative_positions')) {
    echo "  [OK] Table tenant_administrative_positions déjà présente.\n";
} else {
    $adminPositionsSchema->runSqlFile($root . '/migrations/2026_11_14_040_tenant_administrative_positions.sql');
    if (!$adminPositionsSchema->tableExists('tenant_administrative_positions')) {
        throw new RuntimeException('Création de la table tenant_administrative_positions impossible.');
    }
    echo "  [OK] Table tenant_administrative_positions créée.\n";
}
