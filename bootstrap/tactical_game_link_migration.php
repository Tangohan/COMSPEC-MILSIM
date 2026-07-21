<?php

declare(strict_types=1);

/**
 * Code court de liaison Arma ↔ compte Athena : généré sur le portail (session membre),
 * saisi en jeu pour recevoir URL / clé / communauté sans passer par les réglages CBA obscurs.
 * Idempotent.
 *
 * Prod (si POST /atak/game-link renvoie 503) :
 *   php run-migrations.php
 * (inclut cette migration ; crée tactical_game_link_codes si absente.)
 * Ou invoquer ce fichier via le pipeline de migrations déjà branché dans run-migrations.php.
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    };

    if ($tableExists($pdo, 'tactical_game_link_codes')) {
        return;
    }
    try {
        $pdo->exec(
            'CREATE TABLE tactical_game_link_codes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                code VARCHAR(8) NOT NULL,
                expires_at DATETIME NOT NULL,
                redeemed_at DATETIME DEFAULT NULL,
                redeemed_steam_uid VARCHAR(32) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_tgl_code_expires (code, expires_at),
                KEY idx_tgl_tenant_user (tenant_id, user_id),
                CONSTRAINT tgl_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT tgl_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        echo "  [OK] tactical_game_link_codes\n";
    } catch (Throwable $e) {
        echo '  [ATTENTION] tactical_game_link_codes : ' . $e->getMessage() . "\n";
    }
};
