<?php

declare(strict_types=1);

/**
 * Portail candidat : options par dossier (fichiers / audio) + table des pièces jointes.
 */
return function (PDO $pdo): void {
    echo "Recrutement : options portail candidat et pièces jointes…\n";
    @flush();
    @ob_flush();

    $hasCol = static function (PDO $pdo, string $col): bool {
        $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistments' AND COLUMN_NAME = " . $pdo->quote($col) . ' LIMIT 1');

        return (bool) $st->fetchColumn();
    };

    if (!$hasCol($pdo, 'candidate_portal_allow_files')) {
        try {
            $pdo->exec('ALTER TABLE enlistments ADD COLUMN candidate_portal_allow_files TINYINT(1) NOT NULL DEFAULT 0');
        } catch (Throwable $e) {
            echo '  [ATTENTION] candidate_portal_allow_files : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasCol($pdo, 'candidate_portal_allow_audio')) {
        try {
            $pdo->exec('ALTER TABLE enlistments ADD COLUMN candidate_portal_allow_audio TINYINT(1) NOT NULL DEFAULT 0');
        } catch (Throwable $e) {
            echo '  [ATTENTION] candidate_portal_allow_audio : ' . $e->getMessage() . "\n";
        }
    }

    if (!$hasCol($pdo, 'candidate_portal_status_mode')) {
        try {
            $pdo->exec("ALTER TABLE enlistments ADD COLUMN candidate_portal_status_mode VARCHAR(16) NOT NULL DEFAULT 'steps'");
        } catch (Throwable $e) {
            echo '  [ATTENTION] candidate_portal_status_mode : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasCol($pdo, 'candidate_portal_status_manual_text')) {
        try {
            $pdo->exec('ALTER TABLE enlistments ADD COLUMN candidate_portal_status_manual_text VARCHAR(280) NULL DEFAULT NULL');
        } catch (Throwable $e) {
            echo '  [ATTENTION] candidate_portal_status_manual_text : ' . $e->getMessage() . "\n";
        }
    }
    if (!$hasCol($pdo, 'candidate_portal_status_manual_band')) {
        try {
            $pdo->exec("ALTER TABLE enlistments ADD COLUMN candidate_portal_status_manual_band VARCHAR(16) NOT NULL DEFAULT 'amber'");
        } catch (Throwable $e) {
            echo '  [ATTENTION] candidate_portal_status_manual_band : ' . $e->getMessage() . "\n";
        }
    }

    $st = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enlistment_candidate_attachments' LIMIT 1");
    if ($st && (bool) $st->fetchColumn()) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE enlistment_candidate_attachments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            enlistment_id BIGINT UNSIGNED NOT NULL,
            kind ENUM(\'file\',\'audio\') NOT NULL DEFAULT \'file\',
            original_name VARCHAR(255) NOT NULL DEFAULT \'\',
            mime VARCHAR(160) NOT NULL DEFAULT \'\',
            size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            storage_path VARCHAR(512) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_enlistment_candidate_attachments_scope (tenant_id, enlistment_id, created_at),
            KEY idx_enlistment_candidate_attachments_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};
