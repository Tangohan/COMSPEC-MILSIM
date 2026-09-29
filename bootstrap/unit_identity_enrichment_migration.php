<?php

declare(strict_types=1);

/**
 * Vague A–C fiches unités : identité (motto, accent, types), journal d’activité,
 * nettoyage orphelins post_qualification_requirements.
 *
 * Idempotent.
 */
function run_unit_identity_enrichment_migration(PDO $pdo): void
{
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    $execTry = static function (string $sql, string $label) use ($pdo): void {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            fwrite(STDERR, '[ATTENTION] ' . $label . ' : ' . $e->getMessage() . PHP_EOL);
        }
    };

    if (!$hasTable('units')) {
        return;
    }

    // --- unit_type_definitions ---
    if (!$hasTable('unit_type_definitions')) {
        $execTry(
            "CREATE TABLE unit_type_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                label VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_unit_type_tenant_code (tenant_id, code),
                KEY idx_unit_type_tenant_sort (tenant_id, sort_order),
                CONSTRAINT unit_type_def_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'unit_type_definitions.create'
        );
    }

    $unitCols = [
        'motto' => "ALTER TABLE units ADD COLUMN motto VARCHAR(255) NULL AFTER public_blurb",
        'description_long' => "ALTER TABLE units ADD COLUMN description_long TEXT NULL AFTER motto",
        'accent_color' => "ALTER TABLE units ADD COLUMN accent_color VARCHAR(16) NULL AFTER public_accent_color",
        'unit_type_id' => "ALTER TABLE units ADD COLUMN unit_type_id INT UNSIGNED NULL AFTER type",
        'badge_media_path' => "ALTER TABLE units ADD COLUMN badge_media_path VARCHAR(512) NULL AFTER orbat_icon_path",
    ];
    foreach ($unitCols as $col => $sql) {
        if (!$hasColumn('units', $col)) {
            $execTry($sql, 'units.' . $col);
            if (!$hasColumn('units', $col)) {
                // Fallback sans AFTER si ancrage absent
                $execTry(
                    'ALTER TABLE units ADD COLUMN ' . match ($col) {
                        'motto' => 'motto VARCHAR(255) NULL',
                        'description_long' => 'description_long TEXT NULL',
                        'accent_color' => 'accent_color VARCHAR(16) NULL',
                        'unit_type_id' => 'unit_type_id INT UNSIGNED NULL',
                        'badge_media_path' => 'badge_media_path VARCHAR(512) NULL',
                        default => $col . ' VARCHAR(255) NULL',
                    },
                    'units.' . $col . '.fallback'
                );
            }
        }
    }

    // Seed types système par tenant
    if ($hasTable('unit_type_definitions') && $hasTable('tenants')) {
        $defaults = [
            ['commandement', 'Commandement', 10],
            ['operationnel', 'Opérationnel', 20],
            ['soutien', 'Soutien', 30],
            ['formation', 'Formation', 40],
        ];
        $tenants = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $ins = $pdo->prepare(
            'INSERT INTO unit_type_definitions (tenant_id, code, label, sort_order, is_system)
             SELECT ?, ?, ?, ?, 1 FROM DUAL
             WHERE NOT EXISTS (
               SELECT 1 FROM unit_type_definitions WHERE tenant_id = ? AND code = ?
             )'
        );
        foreach ($tenants as $tid) {
            $tid = (int) $tid;
            if ($tid < 1) {
                continue;
            }
            foreach ($defaults as [$code, $label, $sort]) {
                $ins->execute([$tid, $code, $label, $sort, $tid, $code]);
            }
        }
    }

    // --- Journal d’activité (Vague C) ---
    if (!$hasTable('unit_activity_type_definitions')) {
        $execTry(
            "CREATE TABLE unit_activity_type_definitions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                code VARCHAR(64) NOT NULL,
                label VARCHAR(150) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_unit_act_type_tenant_code (tenant_id, code),
                CONSTRAINT unit_act_type_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'unit_activity_type_definitions.create'
        );
    }

    if ($hasTable('unit_activity_type_definitions') && $hasTable('tenants')) {
        $actDefaults = [
            ['operation', 'Opération', 10],
            ['exercice', 'Exercice', 20],
            ['formation', 'Formation', 30],
            ['inspection', 'Inspection', 40],
        ];
        $tenants = $pdo->query('SELECT id FROM tenants')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $insAct = $pdo->prepare(
            'INSERT INTO unit_activity_type_definitions (tenant_id, code, label, sort_order, is_system)
             SELECT ?, ?, ?, ?, 1 FROM DUAL
             WHERE NOT EXISTS (
               SELECT 1 FROM unit_activity_type_definitions WHERE tenant_id = ? AND code = ?
             )'
        );
        foreach ($tenants as $tid) {
            $tid = (int) $tid;
            if ($tid < 1) {
                continue;
            }
            foreach ($actDefaults as [$code, $label, $sort]) {
                $insAct->execute([$tid, $code, $label, $sort, $tid, $code]);
            }
        }
    }

    if (!$hasTable('unit_activity_log')) {
        $execTry(
            "CREATE TABLE unit_activity_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                unit_id INT UNSIGNED NOT NULL,
                activity_type_id INT UNSIGNED NULL,
                activity_type_code VARCHAR(64) NULL,
                title VARCHAR(255) NOT NULL,
                occurred_on DATE NOT NULL,
                summary TEXT NULL,
                after_action_report_document_id INT UNSIGNED NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_unit_activity_unit (tenant_id, unit_id, occurred_on),
                KEY idx_unit_activity_type (activity_type_id),
                CONSTRAINT unit_activity_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT unit_activity_unit_fk FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'unit_activity_log.create'
        );
    }

    if (!$hasTable('unit_activity_participants')) {
        $execTry(
            "CREATE TABLE unit_activity_participants (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                activity_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_unit_act_participant (activity_id, user_id),
                KEY idx_unit_act_part_user (tenant_id, user_id),
                CONSTRAINT unit_act_part_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT unit_act_part_activity_fk FOREIGN KEY (activity_id) REFERENCES unit_activity_log (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT unit_act_part_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'unit_activity_participants.create'
        );
    }

    // --- Orphelins post_qualification_requirements (gate Vague B) ---
    if ($hasTable('post_qualification_requirements') && $hasTable('orbat_billets')) {
        try {
            $pdo->exec(
                'DELETE r FROM post_qualification_requirements r
                 LEFT JOIN orbat_billets b ON b.id = r.post_id AND b.tenant_id = r.tenant_id
                 WHERE b.id IS NULL'
            );
        } catch (Throwable $e) {
            fwrite(STDERR, '[ATTENTION] post_qualification_requirements orphan cleanup : ' . $e->getMessage() . PHP_EOL);
        }
    }
}
