<?php

declare(strict_types=1);

/**
 * Équipes de feu (fire teams) :
 * - éphémères : liées à une carte / mission ATAK (tenant_id + map_id)
 * - permanentes : liées à une unité RH (units / ORBAT)
 *
 * Distinct de fire_units (appui-feu artillerie).
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'fire_teams')) {
        try {
            $pdo->exec(
                "CREATE TABLE fire_teams (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    kind ENUM('ephemeral','permanent') NOT NULL DEFAULT 'ephemeral',
                    label VARCHAR(120) NOT NULL,
                    color VARCHAR(7) NOT NULL DEFAULT '#2563eb',
                    map_id INT UNSIGNED DEFAULT NULL,
                    mission_key VARCHAR(64) DEFAULT NULL,
                    unit_id INT UNSIGNED DEFAULT NULL,
                    notes VARCHAR(500) DEFAULT NULL,
                    created_by_user_id INT UNSIGNED DEFAULT NULL,
                    dissolved_at DATETIME DEFAULT NULL,
                    deleted_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_fire_teams_tenant_kind (tenant_id, kind),
                    KEY idx_fire_teams_tenant_map (tenant_id, map_id),
                    KEY idx_fire_teams_tenant_unit (tenant_id, unit_id),
                    KEY idx_fire_teams_active (tenant_id, deleted_at, dissolved_at),
                    CONSTRAINT fk_fire_teams_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_fire_teams_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL ON UPDATE CASCADE,
                    CONSTRAINT fk_fire_teams_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] fire_teams\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] fire_teams : ' . $e->getMessage() . "\n";
        }
    }

    if (!$tableExists($pdo, 'fire_team_members')) {
        try {
            $pdo->exec(
                "CREATE TABLE fire_team_members (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    fire_team_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED DEFAULT NULL,
                    callsign VARCHAR(64) DEFAULT NULL,
                    role ENUM('leader','member') NOT NULL DEFAULT 'member',
                    display_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_ftm_team (fire_team_id),
                    KEY idx_ftm_user (user_id),
                    UNIQUE KEY uniq_ftm_team_user (fire_team_id, user_id),
                    CONSTRAINT fk_ftm_team FOREIGN KEY (fire_team_id) REFERENCES fire_teams (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT fk_ftm_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            echo "  [OK] fire_team_members\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] fire_team_members : ' . $e->getMessage() . "\n";
        }
    }
};
