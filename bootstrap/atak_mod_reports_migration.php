<?php

declare(strict_types=1);

/**
 * Rapports d’erreurs / bugs remontés par le pack Overwatch (Arma → Athena).
 * Idempotent.
 */
function run_atak_mod_reports_migration(PDO $pdo): void
{
    try {
        $hasTable = static function (string $table) use ($pdo): bool {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        };

        if (!$hasTable('atak_mod_reports')) {
            $pdo->exec(
                "CREATE TABLE atak_mod_reports (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    severity VARCHAR(16) NOT NULL DEFAULT 'error',
                    workflow_status VARCHAR(24) NOT NULL DEFAULT 'new',
                    channel VARCHAR(64) NOT NULL DEFAULT 'Core',
                    message VARCHAR(512) NOT NULL,
                    detail_text TEXT NULL,
                    context_json LONGTEXT NULL,
                    fingerprint VARCHAR(64) DEFAULT NULL,
                    source VARCHAR(32) NOT NULL DEFAULT 'auto',
                    steam_uid VARCHAR(32) DEFAULT NULL,
                    player_uid VARCHAR(64) DEFAULT NULL,
                    player_name VARCHAR(128) DEFAULT NULL,
                    callsign VARCHAR(64) DEFAULT NULL,
                    client_ip VARCHAR(45) DEFAULT NULL,
                    mod_version VARCHAR(32) DEFAULT NULL,
                    extension_version VARCHAR(32) DEFAULT NULL,
                    arma_build VARCHAR(64) DEFAULT NULL,
                    hit_count INT UNSIGNED NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_amr_severity_seen (severity, last_seen_at),
                    KEY idx_amr_workflow (workflow_status, last_seen_at),
                    KEY idx_amr_fingerprint (fingerprint),
                    KEY idx_amr_steam (steam_uid),
                    KEY idx_amr_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        // Colonne de suivi métier (Nouveau / En cours / Corrigé) — idempotent.
        $hasCol = static function (string $table, string $column) use ($pdo): bool {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);

            return (bool) $st->fetchColumn();
        };
        if ($hasTable('atak_mod_reports') && !$hasCol('atak_mod_reports', 'workflow_status')) {
            $pdo->exec(
                "ALTER TABLE atak_mod_reports
                 ADD COLUMN workflow_status VARCHAR(24) NOT NULL DEFAULT 'new' AFTER severity,
                 ADD KEY idx_amr_workflow (workflow_status, last_seen_at)"
            );
        }
        // Réponse au joueur et suivi du traitement (page /admin/atak-mod-reports) — idempotent.
        // last_seen_at = last_seen_at : la colonne a ON UPDATE CURRENT_TIMESTAMP, un ALTER ne la touche pas
        // mais chaque UPDATE du suivi doit la conserver (sinon le rapport remonte comme « vu à l'instant »).
        $replyColumns = [
            'status_changed_at' => 'DATETIME NULL DEFAULT NULL',
            'reply_text' => 'TEXT NULL',
            'replied_at' => 'DATETIME NULL DEFAULT NULL',
            'replied_by' => 'VARCHAR(128) NULL DEFAULT NULL',
            'reply_channels' => 'VARCHAR(64) NULL DEFAULT NULL',
        ];
        foreach ($replyColumns as $column => $definition) {
            if ($hasTable('atak_mod_reports') && !$hasCol('atak_mod_reports', $column)) {
                $pdo->exec('ALTER TABLE atak_mod_reports ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        // Gravité (critique, importante, moyenne, faible) : calculée à la réception, corrigeable sur la page.
        // Les rapports déjà reçus sont classés une fois, à l'ajout de la colonne.
        if ($hasTable('atak_mod_reports') && !$hasCol('atak_mod_reports', 'gravity')) {
            $pdo->exec(
                "ALTER TABLE atak_mod_reports
                 ADD COLUMN gravity VARCHAR(16) NOT NULL DEFAULT 'normal' AFTER severity,
                 ADD KEY idx_amr_gravity (gravity, last_seen_at)"
            );
            if (class_exists(\App\Services\Monitoring\AtakModReportGravity::class)) {
                $rows = $pdo->query('SELECT id, source, severity, message, detail_text FROM atak_mod_reports');
                $upd = $pdo->prepare('UPDATE atak_mod_reports SET gravity = ?, last_seen_at = last_seen_at WHERE id = ?');
                foreach ($rows ? $rows->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
                    $upd->execute([
                        \App\Services\Monitoring\AtakModReportGravity::classify(
                            (string) ($row['source'] ?? ''),
                            (string) ($row['severity'] ?? ''),
                            (string) ($row['message'] ?? ''),
                            (string) ($row['detail_text'] ?? '')
                        ),
                        (int) $row['id'],
                    ]);
                }
            }
        }
        // E-mails automatiques au joueur (accès refusé par Athena…) : un seul par joueur, problème et jour.
        if (!$hasTable('atak_player_auto_notices')) {
            $pdo->exec(
                "CREATE TABLE atak_player_auto_notices (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    steam_uid VARCHAR(32) NOT NULL,
                    problem VARCHAR(48) NOT NULL,
                    sent_on DATE NOT NULL,
                    report_id BIGINT UNSIGNED DEFAULT NULL,
                    diagnosis VARCHAR(48) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uk_apan_player_day (steam_uid, problem, sent_on)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (Throwable) {
        // Idempotent best-effort ; ne pas faire échouer le bootstrap API.
    }
}
