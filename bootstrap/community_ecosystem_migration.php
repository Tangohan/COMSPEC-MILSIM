<?php

declare(strict_types=1);

/**
 * Hub inter-communautés : recommandations, avis après coopération, bourse aux coopérations, jumelages.
 * Idempotent : chaque table n’est créée que si elle manque.
 */
function run_community_ecosystem_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $tables = [
        // Une recommandation par compte et par communauté ; jamais pour sa propre communauté (contrôlé à l’écriture).
        'tenant_recommendations' => "CREATE TABLE tenant_recommendations (
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            voter_tenant_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id, user_id),
            KEY idx_trec_user (user_id),
            CONSTRAINT fk_trec_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Avis d’une communauté sur une autre, après une coopération clôturée qu’elles ont menée ensemble.
        'cooperation_reviews' => "CREATE TABLE cooperation_reviews (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            reviewer_tenant_id INT UNSIGNED NOT NULL,
            reviewed_tenant_id INT UNSIGNED NOT NULL,
            reviewer_user_id INT UNSIGNED NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            reliability TINYINT UNSIGNED NULL,
            organisation TINYINT UNSIGNED NULL,
            ambiance TINYINT UNSIGNED NULL,
            comment TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_coop_review (mission_id, reviewer_tenant_id, reviewed_tenant_id),
            KEY idx_coop_review_reviewed (reviewed_tenant_id),
            CONSTRAINT fk_coop_review_mission FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE,
            CONSTRAINT fk_coop_review_reviewed FOREIGN KEY (reviewed_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Bourse aux coopérations : un besoin ouvert publié par une communauté.
        'cooperation_board_posts' => "CREATE TABLE cooperation_board_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            need_type VARCHAR(32) NOT NULL DEFAULT 'renfort',
            game_label VARCHAR(80) NULL,
            starts_at DATETIME NULL,
            slots SMALLINT UNSIGNED NULL,
            details TEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            mission_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cbp_status (status, starts_at),
            KEY idx_cbp_tenant (tenant_id),
            CONSTRAINT fk_cbp_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Réponse d’une communauté à un besoin (une par communauté et par besoin).
        'cooperation_board_responses' => "CREATE TABLE cooperation_board_responses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            message TEXT NULL,
            slots SMALLINT UNSIGNED NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_cbr_post_tenant (post_id, tenant_id),
            KEY idx_cbr_tenant (tenant_id),
            CONSTRAINT fk_cbr_post FOREIGN KEY (post_id) REFERENCES cooperation_board_posts (id) ON DELETE CASCADE,
            CONSTRAINT fk_cbr_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Jumelage (d’égal à égal) ou parrainage (une unité accompagne une plus jeune) entre deux communautés.
        // sponsor_tenant_id : la marraine pour un parrainage, NULL pour un jumelage.
        'tenant_twinnings' => "CREATE TABLE tenant_twinnings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            requester_tenant_id INT UNSIGNED NOT NULL,
            partner_tenant_id INT UNSIGNED NOT NULL,
            kind VARCHAR(16) NOT NULL DEFAULT 'jumelage',
            sponsor_tenant_id INT UNSIGNED NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            message TEXT NULL,
            requested_by_user_id INT UNSIGNED NOT NULL,
            responded_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            accepted_at DATETIME NULL,
            ended_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_twin_requester (requester_tenant_id, status),
            KEY idx_twin_partner (partner_tenant_id, status),
            CONSTRAINT fk_twin_requester FOREIGN KEY (requester_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT fk_twin_partner FOREIGN KEY (partner_tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($tables as $name => $ddl) {
        if ($name === 'cooperation_reviews' && !$hasTable('interteam_missions')) {
            echo "  [ATTENTION] cooperation_reviews : table interteam_missions absente, création reportée.\n";

            continue;
        }
        if ($hasTable($name)) {
            continue;
        }
        try {
            $pdo->exec($ddl);
            echo "  Table {$name} créée.\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] ' . $name . ' : ' . $e->getMessage() . "\n";
        }
    }
}
