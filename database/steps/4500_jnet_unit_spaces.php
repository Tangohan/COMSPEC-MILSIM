<?php

declare(strict_types=1);

/**
 * Étape « JNET : espace dédié et personnalisable de chaque unité » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table jnet_unit_spaces n'est créée
 * que si elle manque, depuis migrations/2026_11_14_030_jnet_unit_spaces.sql (CREATE TABLE IF NOT EXISTS).
 */

$jnetSpacesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($jnetSpacesSchema->tableExists('jnet_unit_spaces')) {
    echo "  [OK] Table jnet_unit_spaces déjà présente.\n";
} else {
    $jnetSpacesSchema->runSqlFile($root . '/migrations/2026_11_14_030_jnet_unit_spaces.sql');
    if (!$jnetSpacesSchema->tableExists('jnet_unit_spaces')) {
        throw new RuntimeException('Création de la table jnet_unit_spaces impossible.');
    }
    echo "  [OK] Table jnet_unit_spaces créée.\n";
}
