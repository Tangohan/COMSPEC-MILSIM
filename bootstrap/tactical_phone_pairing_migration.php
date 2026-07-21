<?php

declare(strict_types=1);

/**
 * Connexion téléphone pour le module ATAK (inspiré de cTab) : un token (URL/QR) + un code
 * court lisible, générés en jeu, permettant de consulter la diapositive de briefing en cours
 * depuis un navigateur mobile sans compte. Idempotent.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'tactical_phone_pairings')) {
        return;
    }
    try {
        $pdo->exec(
            'CREATE TABLE tactical_phone_pairings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                token CHAR(32) NOT NULL,
                code VARCHAR(8) NOT NULL,
                expires_at DATETIME NOT NULL,
                paired_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_tpp_token (token),
                KEY idx_tpp_tenant_code_expires (tenant_id, code, expires_at),
                CONSTRAINT tpp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "  [OK] tactical_phone_pairings\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] tactical_phone_pairings : ' . $e->getMessage() . "\n";
    }
};
