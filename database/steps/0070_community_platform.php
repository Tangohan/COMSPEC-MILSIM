<?php

declare(strict_types=1);

/**
 * Étape « Migrations bootstrap plateforme (community_platform + unit_commander + rbac_three_layer) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Plans Stripe, colonnes tenants, invitations, modération, événements, usage, codes communauté, parrainage — idempotent.
echo "Migrations bootstrap plateforme (community_platform + unit_commander + rbac_three_layer)...\n";
$migrationFlush();
$migrationRunner->step('run_community_platform_migration', static fn () => run_community_platform_migration($pdo));
