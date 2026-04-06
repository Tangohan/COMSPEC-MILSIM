<?php

declare(strict_types=1);

/**
 * Rôles multiples par utilisateur (tenant) : table user_roles + remplissage depuis users.role_id.
 * Idempotent — appelée depuis run-migrations.php.
 */
function run_user_roles_migration(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_roles (
            user_id int unsigned NOT NULL,
            role_id int unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, role_id),
            KEY user_roles_role_id (role_id),
            CONSTRAINT user_roles_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT user_roles_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    try {
        $pdo->exec(
            'INSERT IGNORE INTO user_roles (user_id, role_id)
             SELECT id, role_id FROM users WHERE role_id IS NOT NULL'
        );
    } catch (PDOException) {
        // Schéma partiel ou rôles orphelins — ignoré
    }
}
