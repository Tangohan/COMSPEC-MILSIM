<?php

declare(strict_types=1);

/**
 * Annonces communauté : options de comportement (masquage, Discord, priorité).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $hasTable = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_alerts' LIMIT 1"
    );
    if (!$hasTable || !$hasTable->fetchColumn()) {
        return;
    }

    $hasCol = static function (PDO $pdo, string $col): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_alerts' AND COLUMN_NAME = ? LIMIT 1"
        );
        $st->execute([$col]);

        return (bool) $st->fetchColumn();
    };

    if (!$hasCol($pdo, 'features_json')) {
        try {
            $pdo->exec(
                "ALTER TABLE tenant_alerts ADD COLUMN features_json JSON DEFAULT NULL COMMENT 'Options: dismissible, notify_discord, highlight' AFTER is_active"
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] tenant_alerts.features_json : ' . $e->getMessage() . "\n";
        }
    }
};
