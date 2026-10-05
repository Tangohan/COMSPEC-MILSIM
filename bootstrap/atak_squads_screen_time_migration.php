<?php

declare(strict_types=1);

/**
 * ATAK en jeu : escouades et équipes de feu remontées par les téléphones (Squad.Sync, COMSPEC Link 2.0.63),
 * temps d'écran du téléphone et temps de jeu par rôle (ScreenTime.Report).
 *
 * - atak_squads : un groupe Arma par session jouée (mission_hash + game_key) ;
 * - fire_teams : + atak_squad_id, game_key, icon, side (équipes « éphémères » créées par la synchro) ;
 * - fire_team_members : + role_key, role_label, steam_id (rôle tenu dans l'équipe, joueur sans fiche liée) ;
 * - atak_screen_time : secondes par membre, jour, type (screen | app | role) et code.
 *
 * Idempotent (après bootstrap/fire_teams_migration.php).
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };
    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
        $st->execute([$table, $index]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('atak_squads')) {
        $pdo->exec(
            "CREATE TABLE atak_squads (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED DEFAULT NULL,
                mission_key VARCHAR(190) NOT NULL,
                mission_hash CHAR(40) NOT NULL,
                game_key VARCHAR(80) NOT NULL,
                name VARCHAR(120) NOT NULL,
                squad_type VARCHAR(16) DEFAULT NULL,
                squad_type_label VARCHAR(64) DEFAULT NULL,
                side VARCHAR(16) DEFAULT NULL,
                leader_callsign VARCHAR(64) DEFAULT NULL,
                member_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                team_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                locked TINYINT(1) NOT NULL DEFAULT 0,
                unassigned_json TEXT DEFAULT NULL,
                reported_by_user_id INT UNSIGNED DEFAULT NULL,
                synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_squads_game (tenant_id, mission_hash, game_key),
                KEY idx_atak_squads_recent (tenant_id, synced_at),
                CONSTRAINT fk_atak_squads_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_squads\n";
    }

    if ($tableExists('fire_teams')) {
        $cols = [
            'atak_squad_id' => 'INT UNSIGNED DEFAULT NULL AFTER unit_id',
            'game_key' => 'VARCHAR(80) DEFAULT NULL AFTER mission_key',
            'icon' => 'VARCHAR(16) DEFAULT NULL AFTER color',
            'side' => 'VARCHAR(16) DEFAULT NULL AFTER icon',
        ];
        foreach ($cols as $col => $def) {
            if (!$columnExists('fire_teams', $col)) {
                $pdo->exec("ALTER TABLE fire_teams ADD COLUMN {$col} {$def}");
                echo "  [OK] fire_teams.{$col}\n";
            }
        }
        if (!$indexExists('fire_teams', 'idx_fire_teams_game')) {
            $pdo->exec('ALTER TABLE fire_teams ADD KEY idx_fire_teams_game (tenant_id, game_key)');
        }
        if (!$indexExists('fire_teams', 'idx_fire_teams_squad')) {
            $pdo->exec('ALTER TABLE fire_teams ADD KEY idx_fire_teams_squad (atak_squad_id)');
        }
    }
    if ($tableExists('fire_team_members')) {
        $cols = [
            'role_key' => 'VARCHAR(16) DEFAULT NULL AFTER role',
            'role_label' => 'VARCHAR(64) DEFAULT NULL AFTER role_key',
            'steam_id' => 'VARCHAR(32) DEFAULT NULL AFTER callsign',
        ];
        foreach ($cols as $col => $def) {
            if (!$columnExists('fire_team_members', $col)) {
                $pdo->exec("ALTER TABLE fire_team_members ADD COLUMN {$col} {$def}");
                echo "  [OK] fire_team_members.{$col}\n";
            }
        }
    }

    if (!$tableExists('atak_screen_time')) {
        $pdo->exec(
            "CREATE TABLE atak_screen_time (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                day DATE NOT NULL,
                kind VARCHAR(16) NOT NULL,
                item_key VARCHAR(64) NOT NULL,
                item_label VARCHAR(120) DEFAULT NULL,
                seconds INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_screen_time (tenant_id, user_id, day, kind, item_key),
                KEY idx_atak_screen_time_period (tenant_id, day),
                CONSTRAINT fk_atak_screen_time_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_screen_time_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_screen_time\n";
    }
};
