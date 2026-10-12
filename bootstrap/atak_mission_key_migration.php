<?php

declare(strict_types=1);

/**
 * Clé de mission de l'ATAK natif (« nom@carte#UTC », XEH_postInitServer.sqf) et pertes amies.
 *
 * - logs_positions.mission_key : relecture filtrée par session jouée (onglet Relecture de /atak) ;
 * - atak_screen_time.mission_key : temps d'écran et par rôle gardés par mission (clé unique élargie) ;
 * - atak_tactical_reports.mission_key : comptes rendus rattachés à la session ;
 * - atak_casualties : morts amis remontés par les téléphones (télémétrie « combat », kind kia),
 *   couche des pertes de la relecture. Un mort n'est gardé qu'une fois (dedupe_key).
 *
 * Lancée à la demande par AtakMissionKey::ensureSchema (première écriture qui échoue faute de colonne)
 * et par run-migrations.php ; sans effet si tout existe déjà.
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

    if ($tableExists('logs_positions') && !$columnExists('logs_positions', 'mission_key')) {
        $pdo->exec('ALTER TABLE logs_positions ADD COLUMN mission_key VARCHAR(190) DEFAULT NULL AFTER mission_id, ADD KEY idx_logs_positions_mkey (mission_id, mission_key, logged_at)');
        echo "  [OK] logs_positions.mission_key\n";
    }

    // Une seule instruction : la clé unique est remplacée en même temps que la colonne arrive.
    if ($tableExists('atak_screen_time') && !$columnExists('atak_screen_time', 'mission_key')) {
        $pdo->exec(
            "ALTER TABLE atak_screen_time
                ADD COLUMN mission_key VARCHAR(190) NOT NULL DEFAULT '' AFTER day,
                DROP INDEX uniq_atak_screen_time,
                ADD UNIQUE KEY uniq_atak_screen_time_mission (tenant_id, user_id, day, mission_key, kind, item_key)"
        );
        echo "  [OK] atak_screen_time.mission_key\n";
    }

    if ($tableExists('atak_tactical_reports') && !$columnExists('atak_tactical_reports', 'mission_key')) {
        $pdo->exec('ALTER TABLE atak_tactical_reports ADD COLUMN mission_key VARCHAR(190) DEFAULT NULL, ADD KEY idx_atak_reports_mission (tenant_id, mission_key)');
        echo "  [OK] atak_tactical_reports.mission_key\n";
    }

    if (!$tableExists('atak_casualties')) {
        $pdo->exec(
            "CREATE TABLE atak_casualties (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED NOT NULL DEFAULT 1,
                mission_key VARCHAR(190) NOT NULL DEFAULT '',
                call_sign VARCHAR(64) NOT NULL,
                kind VARCHAR(8) NOT NULL DEFAULT 'kia',
                pos_x DECIMAL(12,2) NOT NULL,
                pos_y DECIMAL(12,2) NOT NULL,
                reported_by VARCHAR(64) DEFAULT NULL,
                dedupe_key CHAR(40) NOT NULL,
                killed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_casualty (dedupe_key),
                KEY idx_atak_casualty_map (tenant_id, map_id, killed_at),
                KEY idx_atak_casualty_mission (tenant_id, mission_key),
                CONSTRAINT fk_atak_casualty_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_casualties\n";
    }
};
