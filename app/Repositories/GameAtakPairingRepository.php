<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\SilentSchemaMigration;
use PDO;

final class GameAtakPairingRepository
{
    use LazyDatabaseConnection;

    public const TTL_SECONDS = 600;

    protected function onDatabaseConnected(PDO $pdo): void
    {
        SilentSchemaMigration::run(base_path('bootstrap/athena_atak_pair_migration.php'), $pdo);
    }

    public function isReady(): bool
    {
        try {
            $st = $this->pdo()->query("SHOW TABLES LIKE 'game_atak_pair_challenges'");

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array{
     *     device_code_hash: string,
     *     user_code_hash: string,
     *     steam_id?: ?string,
     *     terminal_uid?: ?string,
     *     device_id?: ?string,
     *     mod_version?: ?string
     * } $fields
     */
    public function createPending(array $fields): bool
    {
        if (!$this->isReady()) {
            return false;
        }
        $ttl = (int) self::TTL_SECONDS;
        $st = $this->pdo()->prepare(
            "INSERT INTO game_atak_pair_challenges
                (device_code_hash, user_code_hash, steam_id, terminal_uid, device_id, mod_version, status, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$ttl} SECOND), UTC_TIMESTAMP())"
        );

        return $st->execute([
            (string) $fields['device_code_hash'],
            (string) $fields['user_code_hash'],
            $this->nullable($fields['steam_id'] ?? null, 32),
            $this->nullable($fields['terminal_uid'] ?? null, 64),
            $this->nullable($fields['device_id'] ?? null, 64),
            $this->nullable($fields['mod_version'] ?? null, 32),
        ]);
    }

    public function expirePreviousPending(?string $steamId, ?string $terminalUid): void
    {
        if (!$this->isReady()) {
            return;
        }
        if ($steamId !== null && $steamId !== '') {
            $st = $this->pdo()->prepare(
                "UPDATE game_atak_pair_challenges
                 SET status = 'expired'
                 WHERE status = 'pending' AND steam_id = ? AND expires_at > UTC_TIMESTAMP()"
            );
            $st->execute([$steamId]);
        }
        if ($terminalUid !== null && $terminalUid !== '') {
            $st = $this->pdo()->prepare(
                "UPDATE game_atak_pair_challenges
                 SET status = 'expired'
                 WHERE status = 'pending' AND terminal_uid = ? AND expires_at > UTC_TIMESTAMP()"
            );
            $st->execute([$terminalUid]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPendingByDeviceHash(string $hash): ?array
    {
        return $this->findByHash('device_code_hash', $hash);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPendingByUserHash(string $hash): ?array
    {
        return $this->findByHash('user_code_hash', $hash);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByHash(string $column, string $hash): ?array
    {
        if (!$this->isReady() || $hash === '') {
            return null;
        }
        $allowed = ['device_code_hash', 'user_code_hash'];
        if (!in_array($column, $allowed, true)) {
            return null;
        }
        $st = $this->pdo()->prepare(
            "SELECT * FROM game_atak_pair_challenges
             WHERE {$column} = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function approve(int $id, int $accountId, int $userId, int $tenantId): bool
    {
        if (!$this->isReady() || $id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            "UPDATE game_atak_pair_challenges
             SET status = 'approved',
                 account_id = ?,
                 user_id = ?,
                 tenant_id = ?,
                 approved_at = UTC_TIMESTAMP()
             WHERE id = ? AND status = 'pending' AND expires_at > UTC_TIMESTAMP()"
        );
        $st->execute([$accountId, $userId, $tenantId, $id]);

        return $st->rowCount() > 0;
    }

    public function markConsumed(int $id): void
    {
        if (!$this->isReady() || $id < 1) {
            return;
        }
        $st = $this->pdo()->prepare(
            "UPDATE game_atak_pair_challenges
             SET status = 'consumed', consumed_at = UTC_TIMESTAMP()
             WHERE id = ? AND consumed_at IS NULL"
        );
        $st->execute([$id]);
    }

    public function markExpired(int $id): void
    {
        if (!$this->isReady() || $id < 1) {
            return;
        }
        $st = $this->pdo()->prepare(
            "UPDATE game_atak_pair_challenges SET status = 'expired' WHERE id = ? AND status = 'pending'"
        );
        $st->execute([$id]);
    }

    private function nullable(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return substr($text, 0, $max);
    }
}
