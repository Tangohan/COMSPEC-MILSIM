<?php

declare(strict_types=1);

/**
 * Suivi des paiements et des dons (Administration > Paiements) — idempotente.
 * - payment_events : journal de chaque demande de paiement, don et attribution du rôle donateur ;
 * - platform_donations : dons reçus hors Stripe (PayPal, virement…) saisis par l'administration ;
 * - plan_payment_requests : relances de la personne qui n'a pas finalisé son paiement ;
 * - atak_donations : décision de l'administration sur les dons Stripe (annulé, refusé, encaissé, remboursé).
 * Appelée par run-migrations.php et, en silence, au premier accès à la page.
 */
return static function (PDO $pdo, ?callable $say = null): void {
    $say ??= static function (string $m): void {
        echo '  ' . $m . "\n";
    };
    $tableExists = static function (string $table) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    };

    if (!$tableExists('payment_events')) {
        $pdo->exec(
            "CREATE TABLE payment_events (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                request_id INT UNSIGNED NULL,
                donation_id INT UNSIGNED NULL,
                tenant_id INT UNSIGNED NULL,
                user_id INT UNSIGNED NULL,
                kind VARCHAR(32) NOT NULL,
                actor_user_id INT UNSIGNED NULL,
                note VARCHAR(500) NULL,
                meta JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pe_request (request_id, created_at),
                KEY idx_pe_tenant (tenant_id, created_at),
                KEY idx_pe_user (user_id, created_at),
                KEY idx_pe_kind (kind, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('[OK] payment_events créée');
    }

    if (!$tableExists('platform_donations')) {
        $pdo->exec(
            "CREATE TABLE platform_donations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NULL,
                tenant_id INT UNSIGNED NULL,
                donor_name VARCHAR(120) NULL,
                donor_email VARCHAR(190) NULL,
                amount_cents INT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                channel VARCHAR(16) NOT NULL DEFAULT 'paypal' COMMENT 'paypal|virement|especes|autre',
                transaction_ref VARCHAR(80) NULL,
                note VARCHAR(500) NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 1,
                received_at DATETIME NOT NULL,
                recorded_by INT UNSIGNED NULL,
                refunded_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pd_user (user_id),
                KEY idx_pd_email (donor_email),
                KEY idx_pd_tenant (tenant_id),
                KEY idx_pd_received (received_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $say('[OK] platform_donations créée');
    }

    if ($tableExists('payment_events') && !$columnExists('payment_events', 'atak_donation_id')) {
        $pdo->exec('ALTER TABLE payment_events ADD COLUMN atak_donation_id INT UNSIGNED NULL AFTER donation_id, ADD KEY idx_pe_atak_donation (atak_donation_id, created_at)');
        $say('[OK] payment_events.atak_donation_id');
    }

    // Dons Stripe ATAK : décision de l'administration (annulé, refusé, encaissé à la main, remboursé).
    if ($tableExists('atak_donations')) {
        foreach ([
            'admin_note' => 'ADD COLUMN admin_note VARCHAR(500) NULL',
            'decided_at' => 'ADD COLUMN decided_at DATETIME NULL',
            'decided_by' => 'ADD COLUMN decided_by INT UNSIGNED NULL',
            'refunded_at' => 'ADD COLUMN refunded_at DATETIME NULL',
        ] as $col => $ddl) {
            if (!$columnExists('atak_donations', $col)) {
                $pdo->exec('ALTER TABLE atak_donations ' . $ddl);
                $say('[OK] atak_donations.' . $col);
            }
        }
    }

    if ($tableExists('plan_payment_requests')) {
        foreach ([
            'payer_reminded_at' => 'ADD COLUMN payer_reminded_at DATETIME NULL',
            'payer_reminder_count' => 'ADD COLUMN payer_reminder_count TINYINT UNSIGNED NOT NULL DEFAULT 0',
            'refunded_at' => 'ADD COLUMN refunded_at DATETIME NULL',
            'admin_reminded_at' => 'ADD COLUMN admin_reminded_at DATETIME NULL',
        ] as $col => $ddl) {
            if (!$columnExists('plan_payment_requests', $col)) {
                $pdo->exec('ALTER TABLE plan_payment_requests ' . $ddl);
                $say('[OK] plan_payment_requests.' . $col);
            }
        }
    }
};
