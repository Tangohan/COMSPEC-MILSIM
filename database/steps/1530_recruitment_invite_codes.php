<?php

declare(strict_types=1);

/**
 * Étape « Migration recruitment_invite_codes » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$recruitmentInviteCodesMigrate = require $root . '/bootstrap/recruitment_invite_codes_migration.php';
try {
    $recruitmentInviteCodesMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] recruitment_invite_codes : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('recruitment_invite_codes', $e);
}
