<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class UserLoginDeviceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function findByUserAndFingerprint(int $userId, string $fingerprintHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM user_login_devices WHERE user_id = ? AND fingerprint_hash = ? LIMIT 1'
        );
        $stmt->execute([$userId, $fingerprintHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function touchOrCreate(
        int $userId,
        int $tenantId,
        string $fingerprintHash,
        string $userAgent,
        string $ip,
        ?string $geoCountry
    ): array {
        $existing = $this->findByUserAndFingerprint($userId, $fingerprintHash);
        if ($existing) {
            $this->pdo->prepare(
                'UPDATE user_login_devices SET last_seen_ip = ?, last_seen_at = NOW(), geo_country = COALESCE(?, geo_country), user_agent = ? WHERE id = ?'
            )->execute([$ip, $geoCountry, mb_substr($userAgent, 0, 500), (int) $existing['id']]);

            return ['new' => false, 'row' => array_merge($existing, ['last_seen_ip' => $ip])];
        }

        $this->pdo->prepare(
            'INSERT INTO user_login_devices (user_id, tenant_id, fingerprint_hash, user_agent, first_seen_ip, last_seen_ip, geo_country, last_seen_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([$userId, $tenantId, $fingerprintHash, mb_substr($userAgent, 0, 500), $ip, $ip, $geoCountry]);

        return ['new' => true, 'row' => $this->findByUserAndFingerprint($userId, $fingerprintHash) ?? []];
    }
}
