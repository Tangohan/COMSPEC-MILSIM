<?php

declare(strict_types=1);

/**
 * Fiches de renseignement simplifiées (SSE) — saisie rapide terrain / bureau.
 *
 * Une fiche = un texte libre daté, situé et étiqueté, avec des pièces jointes.
 * Elle vit à côté des dossiers : c'est le point d'entrée le plus léger du
 * renseignement, avant toute qualification.
 *
 * Idempotent — appelée depuis run-migrations.php et depuis le repository.
 */
return static function (PDO $pdo, ?callable $log = null): void {
    $say = static function (string $message) use ($log): void {
        if ($log !== null) {
            $log($message);
        }
    };

    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('sse_field_notes')) {
        $pdo->exec("CREATE TABLE sse_field_notes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            context_id INT UNSIGNED NOT NULL DEFAULT 1,
            reference_code VARCHAR(32) NOT NULL,
            note_kind VARCHAR(16) NOT NULL DEFAULT 'FRM',
            themes VARCHAR(400) NOT NULL DEFAULT '[]',
            title VARCHAR(180) DEFAULT NULL,
            body MEDIUMTEXT NOT NULL,
            observed_at DATETIME NOT NULL,
            place_label VARCHAR(180) DEFAULT NULL,
            grid_reference VARCHAR(32) DEFAULT NULL,
            pos_x DECIMAL(12,2) DEFAULT NULL,
            pos_y DECIMAL(12,2) DEFAULT NULL,
            pos_z DECIMAL(12,2) DEFAULT NULL,
            lat DECIMAL(10,7) DEFAULT NULL,
            lng DECIMAL(10,7) DEFAULT NULL,
            urgency VARCHAR(16) NOT NULL DEFAULT 'routine',
            intel_source VARCHAR(16) DEFAULT NULL,
            classification VARCHAR(24) NOT NULL DEFAULT 'interne',
            source_reliability CHAR(1) NOT NULL DEFAULT 'C',
            info_credibility TINYINT UNSIGNED NOT NULL DEFAULT 3,
            status VARCHAR(24) NOT NULL DEFAULT 'transmise',
            origin VARCHAR(16) NOT NULL DEFAULT 'web',
            author_label VARCHAR(120) DEFAULT NULL,
            author_user_id INT UNSIGNED DEFAULT NULL,
            author_steam_id VARCHAR(32) DEFAULT NULL,
            author_unit VARCHAR(120) DEFAULT NULL,
            case_id INT UNSIGNED DEFAULT NULL,
            interest_case_id INT UNSIGNED DEFAULT NULL,
            triage_note VARCHAR(400) DEFAULT NULL,
            triaged_by INT UNSIGNED DEFAULT NULL,
            triaged_at DATETIME DEFAULT NULL,
            idempotency_key VARCHAR(80) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_sse_field_note_ref (tenant_id, reference_code),
            UNIQUE KEY uniq_sse_field_note_idem (tenant_id, idempotency_key),
            KEY idx_sse_field_note_queue (tenant_id, status, observed_at),
            KEY idx_sse_field_note_kind (tenant_id, note_kind, observed_at),
            KEY idx_sse_field_note_case (tenant_id, case_id),
            CONSTRAINT fk_sse_field_note_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $say('  + table sse_field_notes');
    } else {
        // Colonnes ajoutées après la première mise en service.
        $additions = [
            'lat' => 'ALTER TABLE sse_field_notes ADD COLUMN lat DECIMAL(10,7) DEFAULT NULL AFTER pos_z',
            'lng' => 'ALTER TABLE sse_field_notes ADD COLUMN lng DECIMAL(10,7) DEFAULT NULL AFTER lat',
            'interest_case_id' => 'ALTER TABLE sse_field_notes ADD COLUMN interest_case_id INT UNSIGNED DEFAULT NULL AFTER case_id',
            'title' => 'ALTER TABLE sse_field_notes ADD COLUMN title VARCHAR(180) DEFAULT NULL AFTER themes',
            'intel_source' => 'ALTER TABLE sse_field_notes ADD COLUMN intel_source VARCHAR(16) DEFAULT NULL AFTER urgency',
        ];
        foreach ($additions as $column => $sql) {
            if (!$columnExists('sse_field_notes', $column)) {
                try {
                    $pdo->exec($sql);
                    $say('  + sse_field_notes.' . $column);
                } catch (Throwable $e) {
                    $say('  [ATTENTION] sse_field_notes.' . $column . ' : ' . $e->getMessage());
                }
            }
        }
    }

    if (!$tableExists('sse_field_note_attachments')) {
        $pdo->exec("CREATE TABLE sse_field_note_attachments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            note_id INT UNSIGNED NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(180) DEFAULT NULL,
            mime_type VARCHAR(80) DEFAULT NULL,
            byte_size INT UNSIGNED DEFAULT NULL,
            kind VARCHAR(16) NOT NULL DEFAULT 'photo',
            caption VARCHAR(255) DEFAULT NULL,
            grid_reference VARCHAR(32) DEFAULT NULL,
            pos_x DECIMAL(12,2) DEFAULT NULL,
            pos_y DECIMAL(12,2) DEFAULT NULL,
            pos_z DECIMAL(12,2) DEFAULT NULL,
            author_label VARCHAR(120) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sse_field_note_att_note (tenant_id, note_id),
            CONSTRAINT fk_sse_field_note_att_note FOREIGN KEY (note_id) REFERENCES sse_field_notes (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $say('  + table sse_field_note_attachments');
    }
};
