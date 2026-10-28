<?php

declare(strict_types=1);

/**
 * App Distances de l'ATAK natif (COMSPEC Link « Distance.Batch », 2.0.86) : distances parcourues et heures de pilote,
 * comptées par le serveur Arma pour chaque joueur et envoyées toutes les 10 min.
 *
 * - atak_distance_stats : une ligne par membre et session jouée (mission_key « nom@carte#AAAAMMJJHHMM ») ;
 * - atak_pilot_time : heures aux commandes par membre et classe d'appareil (regroupée par type à l'affichage).
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('atak_distance_stats')) {
        $pdo->exec(
            "CREATE TABLE atak_distance_stats (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                mission_hash CHAR(40) NOT NULL,
                mission_key VARCHAR(190) NOT NULL DEFAULT '',
                day DATE NOT NULL,
                steps INT UNSIGNED NOT NULL DEFAULT 0,
                foot_m INT UNSIGNED NOT NULL DEFAULT 0,
                foot_s INT UNSIGNED NOT NULL DEFAULT 0,
                land_m INT UNSIGNED NOT NULL DEFAULT 0,
                land_s INT UNSIGNED NOT NULL DEFAULT 0,
                sea_m INT UNSIGNED NOT NULL DEFAULT 0,
                sea_s INT UNSIGNED NOT NULL DEFAULT 0,
                air_m INT UNSIGNED NOT NULL DEFAULT 0,
                air_s INT UNSIGNED NOT NULL DEFAULT 0,
                halo_m INT UNSIGNED NOT NULL DEFAULT 0,
                halo_s INT UNSIGNED NOT NULL DEFAULT 0,
                halo_jumps SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_distance_session (tenant_id, user_id, mission_hash),
                KEY idx_atak_distance_period (tenant_id, day),
                CONSTRAINT fk_atak_distance_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_distance_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_distance_stats\n";
    }

    if (!$tableExists('atak_pilot_time')) {
        $pdo->exec(
            "CREATE TABLE atak_pilot_time (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                vehicle_class VARCHAR(80) NOT NULL,
                vehicle_label VARCHAR(120) DEFAULT NULL,
                vehicle_kind VARCHAR(8) NOT NULL DEFAULT 'heli',
                seconds INT UNSIGNED NOT NULL DEFAULT 0,
                meters INT UNSIGNED NOT NULL DEFAULT 0,
                last_flown_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_pilot_time (tenant_id, user_id, vehicle_class),
                CONSTRAINT fk_atak_pilot_time_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_pilot_time_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_pilot_time\n";
    }
};
