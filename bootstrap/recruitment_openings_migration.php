<?php

declare(strict_types=1);

/**
 * Offres de recrutement par unité + lien candidatures.
 * Idempotent.
 */
return static function (PDO $pdo): void {
    $hasUnits = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' LIMIT 1"
    )->fetchColumn();
    if (!$hasUnits) {
        echo "  [SKIP] recruitment_openings : table units absente\n";

        return;
    }

    $hasPjr = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_job_roles' LIMIT 1"
    )->fetchColumn();

    if (!(bool) $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_opening_counters' LIMIT 1"
    )->fetchColumn()) {
        $pdo->exec(
            'CREATE TABLE recruitment_opening_counters (
                tenant_id INT UNSIGNED NOT NULL,
                year SMALLINT UNSIGNED NOT NULL,
                last_seq INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (tenant_id, year),
                CONSTRAINT roc_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table recruitment_opening_counters créée.\n";
    }

    if (!(bool) $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_openings' LIMIT 1"
    )->fetchColumn()) {
        $pjrFk = $hasPjr
            ? ', CONSTRAINT ro_pjr_fk FOREIGN KEY (personnel_job_role_id) REFERENCES personnel_job_roles (id) ON DELETE SET NULL ON UPDATE CASCADE'
            : '';
        $pdo->exec(
            'CREATE TABLE recruitment_openings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                unit_id INT UNSIGNED NOT NULL,
                created_by_user_id INT UNSIGNED DEFAULT NULL,
                personnel_job_role_id INT UNSIGNED DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                summary TEXT DEFAULT NULL,
                description TEXT DEFAULT NULL,
                requirements_json JSON DEFAULT NULL,
                employment_contract_label VARCHAR(160) DEFAULT NULL,
                employment_context_label VARCHAR(160) DEFAULT NULL,
                personnel_category VARCHAR(32) NOT NULL DEFAULT \'other\',
                arm_domain VARCHAR(32) DEFAULT NULL,
                clearance_level VARCHAR(32) NOT NULL DEFAULT \'none\',
                candidate_profile_items JSON DEFAULT NULL,
                technical_notice TEXT DEFAULT NULL,
                mission_lead TEXT DEFAULT NULL,
                responsibility_blocks JSON DEFAULT NULL,
                public_page_slug VARCHAR(120) DEFAULT NULL,
                reference_public VARCHAR(280) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'draft\',
                published_at DATETIME DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL,
                forum_topic_id_externe INT UNSIGNED DEFAULT NULL,
                forum_topic_id_interne INT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ro_tenant_public_slug (tenant_id, public_page_slug),
                KEY idx_ro_tenant_status (tenant_id, status),
                KEY idx_ro_unit (unit_id),
                CONSTRAINT ro_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT ro_unit_fk FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT ro_creator_fk FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
                ' . $pjrFk . '
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table recruitment_openings créée.\n";
    } else {
        if ($hasPjr && !(bool) $pdo->query(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_openings' AND CONSTRAINT_NAME = 'ro_pjr_fk' LIMIT 1"
        )->fetchColumn()) {
            try {
                $pdo->exec(
                    'ALTER TABLE recruitment_openings ADD CONSTRAINT ro_pjr_fk FOREIGN KEY (personnel_job_role_id) REFERENCES personnel_job_roles (id) ON DELETE SET NULL ON UPDATE CASCADE'
                );
                echo "recruitment_openings.ro_pjr_fk ajouté.\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] ro_pjr_fk : ' . $e->getMessage() . "\n";
            }
        }
    }

    foreach (['forum_topic_id_externe', 'forum_topic_id_interne'] as $roForumCol) {
        $c = $pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_openings' AND COLUMN_NAME = " . $pdo->quote($roForumCol) . " LIMIT 1"
        );
        if ($c && !$c->fetchColumn()) {
            try {
                $pdo->exec('ALTER TABLE recruitment_openings ADD COLUMN `' . str_replace('`', '', $roForumCol) . '` INT UNSIGNED DEFAULT NULL');
                echo "recruitment_openings.$roForumCol ajouté.\n";
            } catch (Throwable $e) {
                echo '  [ATTENTION] recruitment_openings.' . $roForumCol . ' : ' . $e->getMessage() . "\n";
            }
        }
    }

    $chk = $pdo->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = 'recruitment_opening_id' LIMIT 1"
    );
    if ($chk && !$chk->fetch()) {
        try {
            $pdo->exec(
                'ALTER TABLE enlistments ADD COLUMN recruitment_opening_id BIGINT UNSIGNED DEFAULT NULL AFTER recruitment_preset_id,
                ADD KEY enlistments_recruitment_opening (recruitment_opening_id),
                ADD CONSTRAINT enlistments_ro_fk FOREIGN KEY (recruitment_opening_id) REFERENCES recruitment_openings (id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
            echo "enlistments.recruitment_opening_id ajouté.\n";
        } catch (Throwable $e) {
            echo '  [ATTENTION] enlistments.recruitment_opening_id : ' . $e->getMessage() . "\n";
        }
    }

    echo "  [OK] recruitment_openings_migration\n";
};
