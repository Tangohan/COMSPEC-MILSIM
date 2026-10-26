<?php

declare(strict_types=1);

/**
 * Étape « Migration enlistment_portal_attachments » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$enlistmentPortalAttachmentsMigrate = require $root . '/bootstrap/enlistment_portal_attachments_migration.php';
try {
    $enlistmentPortalAttachmentsMigrate($pdo);
} catch (Throwable $e) {
    echo '  [ATTENTION] enlistment_portal_attachments : ' . $e->getMessage() . "\n";
    $migrationRunner->warn('enlistment_portal_attachments', $e);
}
