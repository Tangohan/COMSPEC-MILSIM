<?php

declare(strict_types=1);

/**
 * Étape « Libellés générés personnalisés par la communauté » (Schéma) : table tenant_generated_labels.
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle
 * manque, depuis le seul fichier migrations/2026_11_01_020_tenant_generated_labels.sql (CREATE TABLE IF NOT EXISTS).
 * Aucune donnée existante n'est touchée.
 */

$tenantGeneratedLabelsSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($tenantGeneratedLabelsSchema->tableExists('tenant_generated_labels')) {
    echo "  [OK] Table tenant_generated_labels déjà présente.\n";
} else {
    $tenantGeneratedLabelsSchema->runSqlFile($root . '/migrations/2026_11_01_020_tenant_generated_labels.sql');
    echo "  [OK] Table tenant_generated_labels créée.\n";
}
