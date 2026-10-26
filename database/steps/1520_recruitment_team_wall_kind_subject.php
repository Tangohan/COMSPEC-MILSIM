<?php

declare(strict_types=1);

/**
 * Étape « Migration recruitment_team_wall_kind_subject » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$recruitmentTeamWallKindSubjectMigrate = require $root . '/bootstrap/recruitment_team_wall_kind_subject_migration.php';
try {
    $recruitmentTeamWallKindSubjectMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] recruitment_team_wall_kind_subject : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('recruitment_team_wall_kind_subject', $e);
}
