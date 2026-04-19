<?php

declare(strict_types=1);

/**
 * Workflow opérationnel mission (OPORD -> validation -> exécution -> clôture/AAR -> actions correctives).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $col = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if ($col($pdo, 'interteam_missions', 'id')) {
        $adds = [
            'operational_stage' => "VARCHAR(32) NOT NULL DEFAULT 'opord_draft'",
            'opord_text' => 'MEDIUMTEXT DEFAULT NULL',
            'command_validation_notes' => 'TEXT DEFAULT NULL',
            'command_validated_at' => 'DATETIME DEFAULT NULL',
            'execution_started_at' => 'DATETIME DEFAULT NULL',
            'closed_at' => 'DATETIME DEFAULT NULL',
            'aar_summary' => 'MEDIUMTEXT DEFAULT NULL',
            'corrective_actions_json' => 'JSON DEFAULT NULL',
            'linked_resources_json' => 'JSON DEFAULT NULL',
            'simulated_losses_json' => 'JSON DEFAULT NULL',
            'lessons_learned_json' => 'JSON DEFAULT NULL',
        ];
        foreach ($adds as $c => $def) {
            if (!$col($pdo, 'interteam_missions', $c)) {
                $pdo->exec("ALTER TABLE interteam_missions ADD COLUMN `{$c}` {$def}");
                echo "interteam_missions.{$c} ajouté.\n";
            }
        }
    }

    $hasSitrep = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'interteam_mission_sitreps' LIMIT 1");
    if (!$hasSitrep || !$hasSitrep->fetch()) {
        $pdo->exec(
            'CREATE TABLE interteam_mission_sitreps (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            actor_user_id INT UNSIGNED NOT NULL,
            actor_tenant_id INT UNSIGNED NOT NULL,
            occurred_at DATETIME NOT NULL,
            summary TEXT NOT NULL,
            payload_json JSON DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY interteam_sitrep_mission (mission_id, occurred_at),
            KEY interteam_sitrep_actor (actor_user_id),
            CONSTRAINT interteam_sitrep_mission_fk FOREIGN KEY (mission_id) REFERENCES interteam_missions (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "Table interteam_mission_sitreps créée.\n";
    }
};
