<?php

declare(strict_types=1);

/**
 * Étape « Migration atak_custom_roles / atak_role_prefs (rôles d » (Migrations).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Rôles d'équipe de feu partagés avec le jeu : rôles créés en jeu et rôle mémorisé par joueur (AtakRoleService).
$atakRolesMigrate = require $root . '/bootstrap/atak_roles_migration.php';
$migrationRunner->step('atak_roles', static function () use ($atakRolesMigrate, $pdo): void {
    echo "Migration atak_custom_roles / atak_role_prefs (rôles d'équipe de feu en jeu)...\n";
    $atakRolesMigrate($pdo);
});
