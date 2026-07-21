<?php

declare(strict_types=1);

/**
 * Groupes de formation : cohortes de membres suivant un même parcours (promotion, session
 * groupée de tutorat...). Rattachement optionnel à une formation du catalogue (training_courses).
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'training_groups')) {
        try {
            $pdo->exec(
                'CREATE TABLE training_groups (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    description TEXT,
                    course_id BIGINT UNSIGNED DEFAULT NULL,
                    created_by INT UNSIGNED DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_tg_tenant (tenant_id),
                    CONSTRAINT tg_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] training_groups : ' . $e->getMessage() . "\n";

            return;
        }
    }

    if (!$tableExists($pdo, 'training_group_members')) {
        try {
            $pdo->exec(
                'CREATE TABLE training_group_members (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    group_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uk_tgm_group_user (group_id, user_id),
                    KEY idx_tgm_user (user_id),
                    CONSTRAINT tgm_group_fk FOREIGN KEY (group_id) REFERENCES training_groups (id) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT tgm_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            echo '  [ATTENTION] training_group_members : ' . $e->getMessage() . "\n";
        }
    }
};
