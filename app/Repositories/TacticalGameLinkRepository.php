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
    private const TTL_MINUTES = 30;

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
            // Toujours UTC_TIMESTAMP() : sur l’hébergeur, NOW() peut être en Europe/Paris
            // alors que PHP écrit en UTC → expires_at / created_at paraissent déjà périmés.
            $ttl = (int) self::TTL_MINUTES;
            $stmt = $this->pdo->prepare(
                "INSERT INTO tactical_game_link_codes (tenant_id, user_id, code, expires_at, created_at)
                 VALUES (?, ?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$ttl} MINUTE), UTC_TIMESTAMP())"
            );
            $stmt->execute([$tenantId, $userId, $code]);

            $read = $this->pdo->prepare(
                'SELECT code, expires_at FROM tactical_game_link_codes WHERE code = ? ORDER BY id DESC LIMIT 1'
            );
            $read->execute([$code]);
            $row = $read->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            return [
                'code' => (string) $row['code'],
                'expires_at' => (string) $row['expires_at'],
            ];
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
                'SELECT 1 FROM tactical_game_link_codes
                 WHERE code = ? AND redeemed_at IS NULL
                   AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (int) self::TTL_MINUTES . ' MINUTE)
                 LIMIT 1'
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
        $ttl = (int) self::TTL_MINUTES;
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tactical_game_link_codes
             WHERE code = ?
               AND redeemed_at IS NULL
               AND (
                 created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$ttl} MINUTE)
                 OR expires_at > UTC_TIMESTAMP()
               )
             LIMIT 1"
        );
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Dernière ligne pour ce code (même expirée / déjà utilisée) — pour messages d’erreur précis.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestByCode(string $code): ?array
    {
        if (!$this->tableExists() || trim($code) === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tactical_game_link_codes WHERE code = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([strtoupper(trim($code))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return 'already_used'|'expired'|'unknown'
     */
    public function explainInvalidCode(string $code): string
    {
        $latest = $this->findLatestByCode($code);
        if (!is_array($latest)) {
            return 'unknown';
        }
        if (!empty($latest['redeemed_at'])) {
            return 'already_used';
        }
        return 'expired';
    }

    public function markRedeemed(int $id, ?string $steamUid = null): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE tactical_game_link_codes
             SET redeemed_at = UTC_TIMESTAMP(), redeemed_steam_uid = ?
             WHERE id = ? AND redeemed_at IS NULL'
        );
        $uid = $steamUid !== null && trim($steamUid) !== '' ? trim($steamUid) : null;
        $stmt->execute([$uid, $id]);
    }
}
