<?php

declare(strict_types=1);

/**
 * Triage des alertes médicales ATAK (statuts métier sur messages tchat ALERTE MÉDICALE / WIA).
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'atak_medical_alert_triage')) {
        echo "  [OK] atak_medical_alert_triage (déjà présente)\n";

        return;
    }

    if (!$tableExists($pdo, 'atak_chat_messages')) {
        echo "  [ATTENTION] atak_chat_messages absente — triage médical reporté\n";

        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE atak_medical_alert_triage (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                map_id INT UNSIGNED NOT NULL DEFAULT 1,
                chat_message_id INT UNSIGNED NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'a_secourir',
                status_by VARCHAR(120) DEFAULT NULL,
                status_note VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_medical_triage_chat (chat_message_id),
                KEY idx_medical_triage_tenant_map (tenant_id, map_id),
                KEY idx_medical_triage_status (tenant_id, status),
                CONSTRAINT fk_medical_triage_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_medical_triage_chat FOREIGN KEY (chat_message_id) REFERENCES atak_chat_messages (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_medical_alert_triage\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] atak_medical_alert_triage : ' . $e->getMessage() . "\n";
    }
};
