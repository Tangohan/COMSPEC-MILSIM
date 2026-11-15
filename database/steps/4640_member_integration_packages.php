<?php

declare(strict_types=1);

/**
 * Étape « Paquet d'intégration » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : chaque table n'est créée que si elle
 * manque (CREATE TABLE IF NOT EXISTS, depuis migrations/2026_11_15_010_member_integration_packages.sql). Aucune donnée
 * n'est ajoutée ni modifiée : sans paquet défini, l'intégration d'un candidat se passe comme avant.
 * Retour arrière : migrations/rollback/member_integration_packages_down.sql.
 */

$miPackagesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
$miPackagesTables = ['member_integration_packages', 'member_integration_package_items', 'member_integration_applications'];
$miPackagesMissing = array_values(array_filter($miPackagesTables, static fn (string $t): bool => !$miPackagesSchema->tableExists($t)));
if ($miPackagesMissing === []) {
    echo "  [OK] Tables des paquets d'intégration déjà présentes.\n";
} else {
    $miPackagesSchema->runSqlFile($root . '/migrations/2026_11_15_010_member_integration_packages.sql');
    foreach ($miPackagesTables as $miPackagesTable) {
        if (!$miPackagesSchema->tableExists($miPackagesTable)) {
            throw new RuntimeException('Création de la table ' . $miPackagesTable . ' impossible.');
        }
    }
    echo '  [OK] Tables créées : ' . implode(', ', $miPackagesMissing) . ".\n";
}
