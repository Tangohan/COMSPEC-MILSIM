<?php

declare(strict_types=1);

/**
 * Back-office « Remontées » : suivi de tous les comptes rendus (atak_tactical_reports) et de toutes les fiches
 * de renseignement FRS / FRM / FRO… (sse_field_notes), comme un fil de pull request.
 *
 * - intel_intake_state      : état de suivi d'une remontée (à traiter, en cours, exploitée…), attribution, suppression ;
 * - intel_intake_events     : le fil (commentaires et historique : type changé, attribuée, données modifiées, caviardée…) ;
 * - intel_intake_redactions : passages caviardés, lisibles en clair seulement à partir d'une habilitation
 *                             ou par des membres nommés ;
 * - intel_intake_reads      : qui a lu quoi, où (back-office, portail SSE, jeu) ;
 * - sse_field_note_attachments.blurred : pièce jointe floutée.
 *
 * Idempotent.
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

    if (!$tableExists('intel_intake_state')) {
        $pdo->exec(
            "CREATE TABLE intel_intake_state (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                source VARCHAR(8) NOT NULL,
                source_id INT UNSIGNED NOT NULL,
                state VARCHAR(24) NOT NULL DEFAULT 'a_traiter',
                assignee_user_id INT UNSIGNED DEFAULT NULL,
                assigned_by INT UNSIGNED DEFAULT NULL,
                assigned_at DATETIME DEFAULT NULL,
                exploited_by INT UNSIGNED DEFAULT NULL,
                exploited_at DATETIME DEFAULT NULL,
                deleted_by INT UNSIGNED DEFAULT NULL,
                deleted_at DATETIME DEFAULT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_intel_intake_item (tenant_id, source, source_id),
                KEY idx_intel_intake_assignee (tenant_id, assignee_user_id, state),
                CONSTRAINT fk_intel_intake_state_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('intel_intake_events')) {
        $pdo->exec(
            "CREATE TABLE intel_intake_events (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                source VARCHAR(8) NOT NULL,
                source_id INT UNSIGNED NOT NULL,
                kind VARCHAR(16) NOT NULL,
                actor_user_id INT UNSIGNED DEFAULT NULL,
                actor_label VARCHAR(120) DEFAULT NULL,
                body TEXT DEFAULT NULL,
                meta_json TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_intel_intake_events_item (tenant_id, source, source_id, created_at),
                CONSTRAINT fk_intel_intake_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('intel_intake_redactions')) {
        $pdo->exec(
            "CREATE TABLE intel_intake_redactions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                source VARCHAR(8) NOT NULL,
                source_id INT UNSIGNED NOT NULL,
                field VARCHAR(24) NOT NULL DEFAULT 'body',
                phrase TEXT NOT NULL,
                clear_level VARCHAR(24) NOT NULL DEFAULT 'tres_restreint',
                allowed_user_ids VARCHAR(400) NOT NULL DEFAULT '[]',
                reason VARCHAR(255) DEFAULT NULL,
                created_by INT UNSIGNED DEFAULT NULL,
                created_by_label VARCHAR(120) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_intel_intake_redactions_item (tenant_id, source, source_id),
                CONSTRAINT fk_intel_intake_redactions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!$tableExists('intel_intake_reads')) {
        $pdo->exec(
            "CREATE TABLE intel_intake_reads (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                source VARCHAR(8) NOT NULL,
                source_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                channel VARCHAR(12) NOT NULL DEFAULT 'web',
                read_count INT UNSIGNED NOT NULL DEFAULT 1,
                first_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_intel_intake_read (tenant_id, source, source_id, user_id, channel),
                CONSTRAINT fk_intel_intake_reads_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if ($tableExists('sse_field_note_attachments') && !$columnExists('sse_field_note_attachments', 'blurred')) {
        $pdo->exec('ALTER TABLE sse_field_note_attachments ADD COLUMN blurred TINYINT(1) NOT NULL DEFAULT 0 AFTER caption');
    }
};
