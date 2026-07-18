<?php

declare(strict_types=1);

/**
 * Bannière de couverture du menu session / profil (users.profile_banner_url).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_banner_url' LIMIT 1");
    if ($chk && $chk->fetchColumn()) {
        return;
    }

    $pdo->exec(
        'ALTER TABLE users ADD COLUMN profile_banner_url VARCHAR(500) DEFAULT NULL AFTER avatar_url'
    );
};
