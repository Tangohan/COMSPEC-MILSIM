<?php

declare(strict_types=1);

/**
 * Cursus pilote : règles d'attribution automatique des qualifications de pilote à partir des heures de vol relevées
 * par le mod (atak_pilot_time). Une règle par qualification du référentiel : périmètre d'heures (filière ou type
 * d'appareil), seuils, palier précédent, mode (attribuée directement ou proposée puis validée par un instructeur).
 *
 * Idempotent — appelée depuis run-migrations.php.
 */
return static function (PDO $pdo): void {
    $hasTable = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable('pilot_qualification_rules')) {
        echo "  [--] pilot_qualification_rules (déjà là)\n";

        return;
    }
    if (!$hasTable('personnel_qualification_definitions')) {
        echo "  [--] pilot_qualification_rules : référentiel des qualifications absent, étape reportée\n";

        return;
    }
    $pdo->exec(
        "CREATE TABLE pilot_qualification_rules (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id INT UNSIGNED NOT NULL,
            definition_id INT UNSIGNED NOT NULL,
            track VARCHAR(8) NOT NULL DEFAULT 'heli',
            tier VARCHAR(16) NOT NULL,
            scope VARCHAR(100) NOT NULL,
            scope_label VARCHAR(120) DEFAULT NULL,
            min_scope_hours DECIMAL(7,2) NOT NULL DEFAULT 0,
            min_track_hours DECIMAL(7,2) NOT NULL DEFAULT 0,
            prereq_definition_id INT UNSIGNED DEFAULT NULL,
            award_mode VARCHAR(12) NOT NULL DEFAULT 'propose',
            validator_tier VARCHAR(16) DEFAULT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_pilot_rule_def (tenant_id, definition_id),
            KEY idx_pilot_rule_tenant (tenant_id, is_enabled),
            CONSTRAINT fk_pilot_rule_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT fk_pilot_rule_def FOREIGN KEY (definition_id) REFERENCES personnel_qualification_definitions (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "  [OK] pilot_qualification_rules\n";
};
