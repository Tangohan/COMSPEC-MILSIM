<?php

declare(strict_types=1);

/**
 * Étape « Migration email_digest_items (récapitulatif des alertes e-mail) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Envois d'e-mails sous quota : récapitulatif des alertes d'exploitation, compteur du jour.
$emailDigestMigrate = require $root . '/bootstrap/email_digest_migration.php';
$migrationRunner->step('email_digest', static function () use ($emailDigestMigrate, $pdo): void {
    echo "Migration email_digest_items (récapitulatif des alertes e-mail)...\n";
    $emailDigestMigrate($pdo);
});
