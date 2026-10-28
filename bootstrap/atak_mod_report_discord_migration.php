<?php

declare(strict_types=1);

/**
 * Tickets Discord des signalements du mod ATAK (AtakModReportDiscordTicketService).
 * Un ticket = un fil Discord (post de forum) ; plusieurs rapports de même empreinte partagent le même ticket.
 * Idempotent.
 */
function run_atak_mod_report_discord_migration(PDO $pdo): void
{
    try {
        $hasTable = static function (string $table) use ($pdo): bool {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        };

        if (!$hasTable('atak_mod_report_discord_tickets')) {
            $pdo->exec(
                "CREATE TABLE atak_mod_report_discord_tickets (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    opened_report_id BIGINT UNSIGNED NOT NULL,
                    fingerprint VARCHAR(64) DEFAULT NULL,
                    thread_id VARCHAR(32) DEFAULT NULL,
                    header_message_id VARCHAR(32) NOT NULL,
                    status VARCHAR(24) NOT NULL DEFAULT 'new',
                    last_step_at DATETIME NULL DEFAULT NULL,
                    header_synced_at DATETIME NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_amrdt_fingerprint (fingerprint, created_at),
                    KEY idx_amrdt_opened (opened_report_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
        if (!$hasTable('atak_mod_report_discord_links')) {
            $pdo->exec(
                "CREATE TABLE atak_mod_report_discord_links (
                    report_id BIGINT UNSIGNED NOT NULL,
                    ticket_id BIGINT UNSIGNED NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (report_id),
                    KEY idx_amrdl_ticket (ticket_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    } catch (Throwable) {
        // Best-effort : sans ces tables, les tickets Discord sont simplement désactivés.
    }
}
