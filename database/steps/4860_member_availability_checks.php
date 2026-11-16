<?php

declare(strict_types=1);

/**
 * Étape « Effectifs : vérifications de disponibilité guidées » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : la table n'est créée que si elle manque
 * (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_22_100_member_availability_checks.sql). Aucune colonne ajoutée,
 * aucune donnée modifiée, aucun droit créé (la vérification suit le droit existant de gestion du suivi des dossiers).
 * Sans cette table, la vérification se lance et s'affiche, mais n'est pas enregistrée. Compatible SQLite et MySQL.
 * Retour arrière : migrations/rollback/member_availability_checks_down.sql.
 */

$availabilityChecksSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($availabilityChecksSchema->tableExists('member_availability_checks')) {
    echo "  [OK] Table member_availability_checks déjà présente.\n";
} else {
    $availabilityChecksSchema->runSqlFile($root . '/migrations/2026_11_22_100_member_availability_checks.sql');
    if (!$availabilityChecksSchema->tableExists('member_availability_checks')) {
        throw new RuntimeException('Création de la table member_availability_checks impossible.');
    }
    echo "  [OK] Table créée : member_availability_checks.\n";
}
