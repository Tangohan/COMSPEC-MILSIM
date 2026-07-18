<?php

declare(strict_types=1);

/**
 * Styles d’affichage des annonces (barre sous menu, Breaking, annonce importante communauté).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $hasCol = static function (PDO $pdo, string $table, string $col): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $st->execute([$table, $col]);

        return (bool) $st->fetchColumn();
    };

    $hasTable = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($hasTable($pdo, 'platform_alerts') && !$hasCol($pdo, 'platform_alerts', 'display_style')) {
        try {
            $pdo->exec(
                "ALTER TABLE platform_alerts ADD COLUMN display_style VARCHAR(32) NOT NULL DEFAULT 'classic' AFTER kind"
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] platform_alerts.display_style : ' . $e->getMessage() . "\n";
        }
    }

    if ($hasTable($pdo, 'tenant_alerts') && !$hasCol($pdo, 'tenant_alerts', 'display_style')) {
        try {
            $pdo->exec(
                "ALTER TABLE tenant_alerts ADD COLUMN display_style VARCHAR(32) NOT NULL DEFAULT 'classic' AFTER kind"
            );
        } catch (PDOException $e) {
            echo '  [ATTENTION] tenant_alerts.display_style : ' . $e->getMessage() . "\n";
        }
    }
};
