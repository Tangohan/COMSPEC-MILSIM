<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ReferralRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function ensureSchema(): void
    {
        $path = dirname(__DIR__, 2) . '/bootstrap/platform_unit_commander_migration.php';
        if (!is_file($path)) {
            throw new \RuntimeException('Fichier bootstrap platform_unit_commander_migration.php introuvable.');
        }
        require_once $path;
        ensure_platform_unit_commander_schema($this->pdo);
    }

    public function normalizeReferralCode(string $raw): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($raw)));
    }

    /** Code alphanum majuscules 10 caractères. */
    public function getOrCreateCodeForUser(int $userId): string
    {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT code FROM referral_codes WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['code'])) {
            return (string) $row['code'];
        }
        for ($i = 0; $i < 12; $i++) {
            $code = $this->randomCode();
            try {
                $ins = $this->pdo->prepare('INSERT INTO referral_codes (user_id, code, created_at) VALUES (?, ?, NOW())');
                $ins->execute([$userId, $code]);

                return $code;
            } catch (\PDOException) {
                continue;
            }
        }
        throw new \RuntimeException('Impossible de générer un code parrain.');
    }

    private function randomCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }

    public function findUserIdByReferralCode(string $code): ?int
    {
        $this->ensureSchema();
        $c = $this->normalizeReferralCode($code);
        if (strlen($c) < 4) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT user_id FROM referral_codes WHERE code = ? LIMIT 1');
        $stmt->execute([$c]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    public function recordAttribution(int $referrerUserId, ?int $referredTenantId, string $eventType): void
    {
        if ($referrerUserId < 1) {
            return;
        }
        $this->ensureSchema();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO referral_attributions (referrer_user_id, referred_tenant_id, event_type, created_at) VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$referrerUserId, $referredTenantId, $eventType]);
        } catch (\PDOException) {
            // idempotence (doublon unique)
        }
    }
}
