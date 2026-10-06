<?php

declare(strict_types=1);

/**
 * ATAK en jeu : rôles d'équipe de feu partagés avec Athena (App\Services\Atak\AtakRoleService).
 *
 * - atak_custom_roles : rôles créés en jeu par la communauté (clé C_<NOM>, libellé, abrégé, icône du mod) ;
 * - atak_role_prefs : rôle mémorisé de chaque joueur (Steam ID), réappliqué à son arrivée en mission.
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('atak_custom_roles')) {
        $pdo->exec(
            "CREATE TABLE atak_custom_roles (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                role_key VARCHAR(16) NOT NULL,
                label VARCHAR(64) NOT NULL,
                short_label VARCHAR(8) NOT NULL DEFAULT '',
                icon_key VARCHAR(8) NOT NULL DEFAULT 'FUS',
                created_by_steam VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_custom_roles (tenant_id, role_key),
                CONSTRAINT fk_atak_custom_roles_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_custom_roles\n";
    }

    if (!$tableExists('atak_role_prefs')) {
        $pdo->exec(
            "CREATE TABLE atak_role_prefs (
                tenant_id INT UNSIGNED NOT NULL,
                steam_id VARCHAR(32) NOT NULL,
                role_key VARCHAR(16) NOT NULL,
                role_label VARCHAR(64) DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id, steam_id),
                CONSTRAINT fk_atak_role_prefs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_role_prefs\n";
    }
};
