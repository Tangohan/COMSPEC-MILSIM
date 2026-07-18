<?php

declare(strict_types=1);

/**
 * Alertes plateforme : masquage interdit + colonnes pour envoi e-mail (suivi optionnel).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $hasTable = $pdo->query(
        "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_alerts' LIMIT 1"
    );
    if (!$hasTable || !$hasTable->fetchColumn()) {
        return;
    }

    $hasCol = static function (PDO $pdo, string $col): bool {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_alerts' AND COLUMN_NAME = ? LIMIT 1"
        );
        $st->execute([$col]);

        return (bool) $st->fetchColumn();
    };

    $adds = [
        'dismissible' => "ALTER TABLE platform_alerts ADD COLUMN dismissible TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active",
        'email_last_sent_at' => "ALTER TABLE platform_alerts ADD COLUMN email_last_sent_at DATETIME DEFAULT NULL AFTER dismissible",
        'email_last_sent_count' => "ALTER TABLE platform_alerts ADD COLUMN email_last_sent_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER email_last_sent_at",
    ];
    foreach ($adds as $col => $sql) {
        if ($hasCol($pdo, $col)) {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            echo '  [ATTENTION] platform_alerts.' . $col . ' : ' . $e->getMessage() . "\n";
        }
    }
};
