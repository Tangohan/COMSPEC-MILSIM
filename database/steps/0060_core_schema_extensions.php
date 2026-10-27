<?php

declare(strict_types=1);

/**
 * Étape « Extensions DDL (tableau opérationnel planning_*, ORBAT, tenant_email_*, app_maintenance, label_en rôles…) » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Extensions DDL (tableau opérationnel planning_*, ORBAT, tenant_email_*, app_maintenance, label_en rôles…).
$migrationRunner->step('run_core_schema_extensions_migration', static fn () => run_core_schema_extensions_migration($pdo, $root, $migrationFlush));
