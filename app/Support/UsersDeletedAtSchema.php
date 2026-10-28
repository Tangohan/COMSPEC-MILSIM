<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Garantit la colonne users.deleted_at (facultative sur les anciennes installations).
 * Sans elle, un compte anonymisé ne se distingue d'un compte vivant que par son e-mail
 * « @deleted.invalid » : le nettoyage des traces de jeu s'appuie sur cette colonne.
 */
final class UsersDeletedAtSchema
{
    private static bool $done = false;

    public static function ensure(PDO $pdo): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;
        try {
            $st = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_at' LIMIT 1");
            if ($st !== false && $st->fetchColumn()) {
                return;
            }
            $pdo->exec('ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL');
            // Comptes déjà anonymisés avant l'ajout de la colonne.
            $pdo->exec("UPDATE users SET deleted_at = COALESCE(updated_at, NOW()) WHERE email LIKE '%@deleted.invalid' AND deleted_at IS NULL");
        } catch (\Throwable $e) {
            error_log('[users.deleted_at] ' . $e->getMessage());
        }
    }
}
