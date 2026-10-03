<?php

declare(strict_types=1);

/** Téléphone en jeu de chaque opérateur : numéro, IMEI et MAC attribués une fois et gardés. */
return static function (PDO $pdo): void {
    $exists = $pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_phone_identities' LIMIT 1")->fetchColumn();
    if ($exists) {
        return;
    }

    $pdo->exec("CREATE TABLE game_phone_identities (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        phone_format VARCHAR(2) NOT NULL DEFAULT 'FR',
        phone_number VARCHAR(32) NOT NULL,
        imei VARCHAR(32) NOT NULL,
        mac VARCHAR(17) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_game_phone_user (tenant_id, user_id),
        UNIQUE KEY uq_game_phone_number (tenant_id, phone_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
