<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Comptes Discord reliés (rôles liés de Discord) : migrations/20261025000002_user_discord_links.sql.
 * Les jetons arrivent ici déjà chiffrés (App\Services\Integrations\DiscordLinkedRoles).
 */
final class UserDiscordLinkRepository
{
    use LazyDatabaseConnection;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute(['user_discord_links']);
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM user_discord_links WHERE user_id = ?');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{discord_user_id:string, discord_username:string, access_token:string, refresh_token:string, expires_at:string} $row
     */
    public function save(int $userId, array $row): void
    {
        // Un compte Discord ne sert qu'à un compte du site : une nouvelle liaison remplace l'ancienne.
        $this->pdo()->prepare('DELETE FROM user_discord_links WHERE discord_user_id = ? AND user_id <> ?')
            ->execute([$row['discord_user_id'], $userId]);
        $this->pdo()->prepare(
            'INSERT INTO user_discord_links (user_id, discord_user_id, discord_username, access_token, refresh_token, expires_at, linked_at, last_error)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), \'\')
             ON DUPLICATE KEY UPDATE discord_user_id = VALUES(discord_user_id), discord_username = VALUES(discord_username),
                 access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expires_at = VALUES(expires_at),
                 linked_at = NOW(), last_error = \'\''
        )->execute([
            $userId,
            $row['discord_user_id'],
            mb_substr($row['discord_username'], 0, 64),
            $row['access_token'],
            $row['refresh_token'],
            $row['expires_at'],
        ]);
    }

    public function updateTokens(int $userId, string $access, string $refresh, string $expiresAt): void
    {
        $this->pdo()->prepare('UPDATE user_discord_links SET access_token = ?, refresh_token = ?, expires_at = ? WHERE user_id = ?')
            ->execute([$access, $refresh, $expiresAt, $userId]);
    }

    public function markSynced(int $userId, string $error = ''): void
    {
        $this->pdo()->prepare('UPDATE user_discord_links SET synced_at = NOW(), last_error = ? WHERE user_id = ?')
            ->execute([mb_substr($error, 0, 255), $userId]);
    }

    public function delete(int $userId): void
    {
        $this->pdo()->prepare('DELETE FROM user_discord_links WHERE user_id = ?')->execute([$userId]);
    }

    /** @return list<int> comptes à renvoyer à Discord, les plus anciennement envoyés d'abord */
    public function staleUserIds(int $olderThanHours, int $limit): array
    {
        $st = $this->pdo()->prepare(
            'SELECT user_id FROM user_discord_links
             WHERE synced_at IS NULL OR synced_at < (NOW() - INTERVAL ' . max(1, $olderThanHours) . ' HOUR)
             ORDER BY synced_at IS NOT NULL, synced_at ASC LIMIT ' . max(1, $limit)
        );
        $st->execute();

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function count(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM user_discord_links')->fetchColumn();
    }
}
