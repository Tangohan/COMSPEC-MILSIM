<?php

declare(strict_types=1);

/**
 * Colonne permissions.action (taxonomie) + index — idempotent.
 */
function run_permissions_action_migration(PDO $pdo): void
{
    $check = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permissions' AND COLUMN_NAME = 'action' LIMIT 1");
    if ($check && !$check->fetch()) {
        echo "Permissions: ajout colonne action...\n";
        $pdo->exec("ALTER TABLE permissions ADD COLUMN action VARCHAR(32) NULL DEFAULT NULL AFTER module");
        try {
            $pdo->exec('ALTER TABLE permissions ADD KEY permissions_tenant_module_action (tenant_id, module, action)');
        } catch (PDOException) {
        }
    }
}
