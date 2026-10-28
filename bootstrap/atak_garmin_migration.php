<?php

declare(strict_types=1);

/**
 * App Garmin de l'ATAK natif (COMSPEC Link « Garmin.Report », 2.0.87) : la montre du joueur, mesurée sur son PC et
 * envoyée en entier toutes les 5 min (14 derniers jours, 40 dernières activités, état actuel).
 *
 * - atak_garmin_days : une ligne par membre et jour réel (pas, distances, fréquence cardiaque, stress, Body Battery, sommeil) ;
 * - atak_garmin_activities : activités reconnues par la montre (course, marche, véhicule, vol, saut, bateau, nage) ;
 * - atak_garmin_status : dernier état de la montre (Body Battery, fréquence cardiaque, stress, objectif de pas).
 *
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('atak_garmin_days')) {
        $pdo->exec(
            "CREATE TABLE atak_garmin_days (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                day DATE NOT NULL,
                steps INT UNSIGNED NOT NULL DEFAULT 0,
                foot_m INT UNSIGNED NOT NULL DEFAULT 0,
                foot_s INT UNSIGNED NOT NULL DEFAULT 0,
                hr_max SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                hr_rest SMALLINT UNSIGNED DEFAULT NULL,
                bb_up SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                bb_down SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                sleep_s INT UNSIGNED NOT NULL DEFAULT 0,
                stress_avg TINYINT UNSIGNED NOT NULL DEFAULT 0,
                run_n SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                run_m INT UNSIGNED NOT NULL DEFAULT 0,
                walk_n SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                walk_m INT UNSIGNED NOT NULL DEFAULT 0,
                act_n SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_garmin_day (tenant_id, user_id, day),
                CONSTRAINT fk_atak_garmin_days_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_garmin_days_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_garmin_days\n";
    }

    if (!$tableExists('atak_garmin_activities')) {
        $pdo->exec(
            "CREATE TABLE atak_garmin_activities (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                day DATE NOT NULL,
                started_at CHAR(5) NOT NULL DEFAULT '',
                type VARCHAR(8) NOT NULL,
                place VARCHAR(80) NOT NULL DEFAULT '',
                world VARCHAR(60) NOT NULL DEFAULT '',
                meters INT UNSIGNED NOT NULL DEFAULT 0,
                seconds INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_garmin_activity (tenant_id, user_id, day, started_at, type),
                KEY idx_atak_garmin_activity_recent (tenant_id, user_id, day),
                CONSTRAINT fk_atak_garmin_acts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_garmin_acts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_garmin_activities\n";
    }

    if (!$tableExists('atak_garmin_status')) {
        $pdo->exec(
            "CREATE TABLE atak_garmin_status (
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                body_battery TINYINT UNSIGNED NOT NULL DEFAULT 0,
                heart_rate SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                stress TINYINT UNSIGNED NOT NULL DEFAULT 0,
                rest_hr SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                step_goal INT UNSIGNED NOT NULL DEFAULT 10000,
                synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id, user_id),
                CONSTRAINT fk_atak_garmin_status_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_atak_garmin_status_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_garmin_status\n";
    }
};
