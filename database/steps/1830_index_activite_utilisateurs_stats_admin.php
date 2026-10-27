<?php

declare(strict_types=1);

/**
 * Étape « Index activité utilisateurs (stats admin) » (Schéma).
 *
 * Exécutée par run-migrations.php dans la portée du pipeline : $pdo, $root, $migrationRunner, $migrationFlush,
 * $migrationEnsurePdo et les helpers de database/steps/_context.php sont disponibles.
 */

// Index activité utilisateurs (stats admin)
$stmt = $pdo->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_tenant_status_login'");
if ($stmt && !$stmt->fetch()) {
    echo "Index users (tenant_id, status, last_login_at)...\n";
    $pdo->exec('ALTER TABLE users ADD KEY idx_users_tenant_status_login (tenant_id, status, last_login_at)');
}
