<?php

declare(strict_types=1);

/**
 * Étape « Doctrine : référentiel personnalisable par communauté » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : seules les tables absentes sont
 * créées (migrations/2026_11_02_100_doctrine_library.sql), une relance ne change rien. Rien n’est supprimé ni
 * modifié : le catalogue fourni par le site reste servi depuis config/doctrine_library.
 */

$doctrineLibrarySchema = new \App\Support\Migrations\SchemaInspector($pdo);
$doctrineLibraryTables = ['doctrine_library_categories', 'doctrine_library_entries', 'doctrine_library_hidden', 'doctrine_library_links'];
$doctrineLibraryMissing = array_values(array_filter($doctrineLibraryTables, static fn (string $t): bool => !$doctrineLibrarySchema->tableExists($t)));
if ($doctrineLibraryMissing === []) {
    echo "  [OK] Tables du référentiel doctrinal de communauté déjà présentes.\n";
} else {
    $doctrineLibrarySchema->runSqlFile($root . '/migrations/2026_11_02_100_doctrine_library.sql');
    echo '  [OK] Tables créées : ' . implode(', ', $doctrineLibraryMissing) . ".\n";
}
