<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Codes courts de liaison compte Athena → mod Arma (saisie en jeu).
 */
class TacticalGameLinkRepository
{
    private const TTL_MINUTES = 15;

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * False tant que la migration bootstrap/tactical_game_link_migration.php n’a pas été jouée.
     */
    public function isReady(): bool
    {
        return $this->tableExists();
    }

    private function tableExists(): bool
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $stmt = $this->pdo->query("SHOW TABLES LIKE 'tactical_game_link_codes'");
                $exists = (bool) ($stmt && $stmt->fetchColumn());
            } catch (\Throwable) {
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * @return array{code: string, expires_at: string}|null
     */
    public function create(int $tenantId, int $userId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1 || $userId < 1) {
            return null;
        }
        try {
            $this->pdo->prepare(
                'DELETE FROM tactical_game_link_codes WHERE user_id = ? AND tenant_id = ? AND redeemed_at IS NULL'
            )->execute([$userId, $tenantId]);

            $code = $this->generateUniqueCode();
            $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_MINUTES * 60);
            $stmt = $this->pdo->prepare(
                'INSERT INTO tactical_game_link_codes (tenant_id, user_id, code, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$tenantId, $userId, $code, $expiresAt]);

            return ['code' => $code, 'expires_at' => $expiresAt];
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM tactical_game_link_codes WHERE code = ? AND expires_at > NOW() AND redeemed_at IS NULL LIMIT 1'
            );
            $stmt->execute([$code]);
            if (!$stmt->fetchColumn()) {
                return $code;
            }
        }

        return strtoupper(bin2hex(random_bytes(3)));
    }

    public function findValidByCode(string $code): ?array
    {
        if (!$this->tableExists() || trim($code) === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tactical_game_link_codes
             WHERE code = ? AND expires_at > NOW() AND redeemed_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markRedeemed(int $id, ?string $steamUid = null): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE tactical_game_link_codes
             SET redeemed_at = NOW(), redeemed_steam_uid = ?
             WHERE id = ? AND redeemed_at IS NULL'
        );
        $uid = $steamUid !== null && trim($steamUid) !== '' ? trim($steamUid) : null;
        $stmt->execute([$uid, $id]);
    }
}
