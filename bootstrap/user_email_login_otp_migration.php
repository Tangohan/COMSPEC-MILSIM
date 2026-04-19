<?php

declare(strict_types=1);

/**
 * Double vérification par e-mail activable par l’utilisateur (2FA optionnel).
 * Idempotent — appelée depuis run-migrations.php.
 */
return function (PDO $pdo): void {
    $chk = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_login_otp_enabled' LIMIT 1");
    if ($chk && $chk->fetchColumn()) {
        return;
    }

    $pdo->exec(
        'ALTER TABLE users ADD COLUMN email_login_otp_enabled TINYINT(1) NOT NULL DEFAULT 0'
    );
};
