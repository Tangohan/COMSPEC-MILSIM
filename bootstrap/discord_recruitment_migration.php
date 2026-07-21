<?php

declare(strict_types=1);

/**
 * Recrutement via Discord : troisième mode de formulaire de candidature.
 * Questions custom par tenant (recruitment_discord_questions) + colonnes de
 * suivi sur enlistments (pseudo Discord, réponses, rendez-vous, grille
 * d'évaluation staff, transmission au portail candidat). Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    $hasCol = static function (PDO $pdo, string $table, string $column): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists($pdo, 'recruitment_discord_questions')) {
        $pdo->exec(
            "CREATE TABLE recruitment_discord_questions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'open',
                label VARCHAR(255) NOT NULL,
                options_json JSON DEFAULT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                position INT UNSIGNED NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                KEY idx_rdq_tenant_position (tenant_id, position),
                CONSTRAINT fk_rdq_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    $columns = [
        'form_channel' => "ALTER TABLE enlistments ADD COLUMN form_channel VARCHAR(20) NOT NULL DEFAULT 'milsim' AFTER submitted_via",
        'discord_pseudo' => 'ALTER TABLE enlistments ADD COLUMN discord_pseudo VARCHAR(100) DEFAULT NULL',
        'discord_answers_json' => 'ALTER TABLE enlistments ADD COLUMN discord_answers_json JSON DEFAULT NULL',
        'discord_interview_at' => 'ALTER TABLE enlistments ADD COLUMN discord_interview_at DATETIME DEFAULT NULL',
        'discord_interview_notes' => 'ALTER TABLE enlistments ADD COLUMN discord_interview_notes TEXT',
        'discord_evaluation_json' => 'ALTER TABLE enlistments ADD COLUMN discord_evaluation_json JSON DEFAULT NULL',
        'discord_transmitted_at' => 'ALTER TABLE enlistments ADD COLUMN discord_transmitted_at DATETIME DEFAULT NULL',
        'discord_portal_messaging_enabled' => 'ALTER TABLE enlistments ADD COLUMN discord_portal_messaging_enabled TINYINT(1) NOT NULL DEFAULT 0',
        'discord_portal_messaging_enabled_at' => 'ALTER TABLE enlistments ADD COLUMN discord_portal_messaging_enabled_at DATETIME DEFAULT NULL',
    ];

    foreach ($columns as $column => $sql) {
        if (!$hasCol($pdo, 'enlistments', $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                echo '  [ATTENTION] ' . $column . ' : ' . $e->getMessage() . "\n";
            }
        }
    }
};
