<?php

declare(strict_types=1);

/**
 * Dons / financement ATAK (Checkout Stripe mode payment).
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

    if ($tableExists($pdo, 'atak_donations')) {
        echo "  [OK] atak_donations (déjà présente)\n";

        return;
    }

    if (!$tableExists($pdo, 'users')) {
        echo "  [ATTENTION] users absente — atak_donations reportée\n";

        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE atak_donations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                tenant_id INT UNSIGNED DEFAULT NULL,
                amount_cents INT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'eur',
                stripe_checkout_session_id VARCHAR(255) DEFAULT NULL,
                stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'pending',
                badge_granted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_atak_donation_session (stripe_checkout_session_id),
                KEY idx_atak_donation_user (user_id),
                KEY idx_atak_donation_status (status),
                CONSTRAINT fk_atak_donation_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        echo "  [OK] atak_donations créée\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] atak_donations : ' . $e->getMessage() . "\n";
    }
};
