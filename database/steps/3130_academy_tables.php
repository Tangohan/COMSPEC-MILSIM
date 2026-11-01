<?php

declare(strict_types=1);

/**
 * Étape « Académie : tables des cours et des apprenants » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : migrations/20261029130000_athena_academy.sql (CREATE TABLE IF NOT EXISTS).
 */

$academySchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($academySchema->tableExists('academy_courses') && $academySchema->tableExists('academy_learners')) {
    echo "  [OK] Tables de l’Académie déjà présentes.\n";
} else {
    $academySchema->runSqlFile($root . '/migrations/20261029130000_athena_academy.sql');
    echo "  [OK] Tables academy_courses et academy_learners créées.\n";
}
