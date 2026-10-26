<?php

declare(strict_types=1);

/**
 * Étape « Migration plan_gifts / plan_gift_notices (formules offertes) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Formules offertes à une communauté par l'administration de la plateforme (e-mail + fenêtre aux gestionnaires).
$planGiftMigrate = require $root . '/bootstrap/plan_gift_migration.php';
$migrationRunner->step('plan_gifts', static function () use ($planGiftMigrate, $pdo): void {
    echo "Migration plan_gifts / plan_gift_notices (formules offertes)...\n";
    $planGiftMigrate($pdo);
});
