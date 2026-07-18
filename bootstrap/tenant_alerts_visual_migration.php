<?php

declare(strict_types=1);

/**
 * Enrichissement visuel des alertes communauté : couleur, icône, image, bannière, types étendus.
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

    // Assouplir le type pour accepter les nouveaux libellés métier.
    try {
        $pdo->exec("ALTER TABLE tenant_alerts MODIFY COLUMN kind VARCHAR(32) NOT NULL DEFAULT 'info'");
    } catch (PDOException $e) {
        // Ignorer si déjà VARCHAR ou contrainte non applicable.
    }

    $adds = [
        'accent_color' => "ALTER TABLE tenant_alerts ADD COLUMN accent_color VARCHAR(7) DEFAULT NULL AFTER coupon_code",
        'icon_key' => "ALTER TABLE tenant_alerts ADD COLUMN icon_key VARCHAR(32) DEFAULT NULL AFTER accent_color",
        'image_path' => "ALTER TABLE tenant_alerts ADD COLUMN image_path VARCHAR(512) DEFAULT NULL AFTER icon_key",
        'banner_path' => "ALTER TABLE tenant_alerts ADD COLUMN banner_path VARCHAR(512) DEFAULT NULL AFTER image_path",
    ];
    foreach ($adds as $col => $sql) {
        if ($hasCol($pdo, $col)) {
            continue;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            echo '  [ATTENTION] tenant_alerts.' . $col . ' : ' . $e->getMessage() . "\n";
        }
    }
};
