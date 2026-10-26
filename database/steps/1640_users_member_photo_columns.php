<?php

declare(strict_types=1);

/**
 * Étape « Migration users_member_photo_columns » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$usersMemberPhotoMigrate = require $root . '/bootstrap/users_member_photo_columns_migration.php';
try {
    $usersMemberPhotoMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] users_member_photo : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('users_member_photo', $e);
}
