<?php

declare(strict_types=1);

/**
 * Étape « Anciens noms : journal de la reprise des noms de personnage » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table legacy_identity_import_log n'est
 * créée que si elle manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_100_legacy_identity_import_log.sql).
 * Aucune donnée n'est ajoutée ni modifiée : les noms ne sont repris que sur demande d'un responsable, jamais ici.
 * Retour arrière : migrations/rollback/legacy_identity_import_log_down.sql.
 */

$legacyIdentitySchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($legacyIdentitySchema->tableExists('legacy_identity_import_log')) {
    echo "  [OK] Journal de la reprise des anciens noms déjà présent.\n";
} else {
    $legacyIdentitySchema->runSqlFile($root . '/migrations/2026_11_15_100_legacy_identity_import_log.sql');
    if (!$legacyIdentitySchema->tableExists('legacy_identity_import_log')) {
        throw new RuntimeException('Création de la table legacy_identity_import_log impossible.');
    }
    echo "  [OK] Table créée : legacy_identity_import_log.\n";
}
