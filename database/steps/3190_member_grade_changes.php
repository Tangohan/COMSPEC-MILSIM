<?php

declare(strict_types=1);

/**
 * Étape « Historique des changements de grade (member_grade_changes) » (Schéma).
 *
 * Exécutée par run-migrations.php (file active) avec $pdo et $root. Idempotente : l’état réel de la base
 * est vérifié avant toute modification, une relance ne change rien.
 * Source unique : MemberGradeChangeRepository::MIGRATION_FILE (CREATE TABLE IF NOT EXISTS), aussi utilisée à la demande.
 */

$gradeChangesSchema = new \App\Support\Migrations\SchemaInspector($pdo);
if ($gradeChangesSchema->tableExists('member_grade_changes')) {
    echo "  [OK] Table member_grade_changes déjà présente.\n";
} else {
    $gradeChangesSchema->runSqlFile($root . '/' . \App\Repositories\MemberGradeChangeRepository::MIGRATION_FILE);
    echo "  [OK] Table member_grade_changes créée.\n";
}
