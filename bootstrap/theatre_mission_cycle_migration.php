<?php

declare(strict_types=1);

/**
 * Cycle de mission théâtre C2 : préparation → en cours → clôturée.
 * Relie briefing / exécution Tacmap / relecture après-action (fenêtre temporelle).
 * Idempotent.
 */
function run_theatre_mission_cycle_migration(PDO $pdo): void
{
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'theatre_mission_cycles')) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE theatre_mission_cycles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            map_id INT UNSIGNED NOT NULL DEFAULT 1,
            title VARCHAR(200) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'preparation',
            started_at DATETIME NULL,
            ended_at DATETIME NULL,
            aar_summary TEXT NULL,
            replay_mission_id VARCHAR(128) NOT NULL,
            created_by_user_id INT UNSIGNED NULL,
            closed_by_user_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tmc_tenant_map_status (tenant_id, map_id, status),
            KEY idx_tmc_tenant_updated (tenant_id, updated_at),
            KEY idx_tmc_replay (replay_mission_id),
            CONSTRAINT fk_tmc_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

return static function (PDO $pdo): void {
    try {
        run_theatre_mission_cycle_migration($pdo);
        echo "  [OK] theatre_mission_cycles\n";
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (
            str_contains($msg, 'already exists')
            || str_contains($msg, 'Duplicate')
        ) {
            echo "  [SKIP] theatre_mission_cycles déjà présent\n";

            return;
        }
        echo '  [ATTENTION] theatre_mission_cycles : ' . $msg . "\n";
    }
};
