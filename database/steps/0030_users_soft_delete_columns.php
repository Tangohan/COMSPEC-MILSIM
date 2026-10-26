<?php

declare(strict_types=1);

/**
 * Étape « Suppression douce des comptes (users.deleted_at, deleted_by) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Suppression douce (anonymisation) d'un compte depuis l'annuaire plateforme admin/users.
$ensureAddedUsers = schema_ensure_columns($pdo, 'users', [
    'deleted_at' => '`deleted_at` DATETIME NULL AFTER `status`',
    'deleted_by' => '`deleted_by` INT UNSIGNED NULL AFTER `deleted_at`',
]);
if ($ensureAddedUsers === 0) {
    echo "  [OK] users : deleted_at, deleted_by déjà présentes\n";
} else {
    echo "  [OK] users : {$ensureAddedUsers} colonne(s) comblée(s)\n";
}
$migrationFlush();
