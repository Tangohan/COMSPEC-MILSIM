<?php

declare(strict_types=1);

/**
 * Étape « Academy Studio : archivage, corbeille et partage des formations » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * - academy_courses.archived_at / deleted_at : NULL = formation active (aucune formation existante ne change).
 * - Tables academy_course_shares et academy_course_links (migrations/2026_11_01_020_academy_course_sharing.sql).
 */

$academyLifecycleSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if (!$academyLifecycleSchema->tableExists('academy_courses')) {
    echo "  [OK] Table academy_courses absente : l’étape academy_tables doit passer d’abord.\n";
} else {
    foreach (['archived_at' => 'DATETIME NULL DEFAULT NULL', 'deleted_at' => 'DATETIME NULL DEFAULT NULL'] as $academyLifecycleColumn => $academyLifecycleDefinition) {
        echo $academyLifecycleSchema->addColumnIfMissing('academy_courses', $academyLifecycleColumn, $academyLifecycleDefinition)
            ? "  [OK] Colonne academy_courses.{$academyLifecycleColumn} ajoutée.\n"
            : "  [OK] Colonne academy_courses.{$academyLifecycleColumn} déjà présente.\n";
    }
}
if ($academyLifecycleSchema->tableExists('academy_course_shares') && $academyLifecycleSchema->tableExists('academy_course_links')) {
    echo "  [OK] Tables de partage de l’Academy déjà présentes.\n";
} else {
    $academyLifecycleSchema->runSqlFile($root . '/migrations/2026_11_01_020_academy_course_sharing.sql');
    echo "  [OK] Tables academy_course_shares et academy_course_links créées.\n";
}
