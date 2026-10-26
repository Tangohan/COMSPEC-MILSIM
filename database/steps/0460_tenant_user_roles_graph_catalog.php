<?php

declare(strict_types=1);

/**
 * Étape « Migration tenant_user_roles_graph_catalog » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

$migrationRunner->step('run_tenant_user_roles_graph_catalog_migration', static fn () => run_tenant_user_roles_graph_catalog_migration($pdo));
