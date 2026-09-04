<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use App\Support\SilentSchemaMigration;
use App\Support\SqlText;
use PDO;

final class AthenaAccountRepository
{
    public function findForUserAndTenant(int $userId, int $tenantId): ?array
    {
        $st = $this->pdo()->prepare('SELECT a.* FROM athena_accounts a INNER JOIN account_tenant_memberships m ON m.account_id=a.id WHERE m.user_id=? AND m.tenant_id=? AND m.status=\'active\' LIMIT 1');
        $st->execute([$userId, $tenantId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    use LazyDatabaseConnection;

    protected function onDatabaseConnected(PDO $pdo): void
    {
        SilentSchemaMigration::run(base_path('bootstrap/athena_game_auth_migration.php'), $pdo);
    }

    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $emailEq = SqlText::normalizedEquals($this->pdo(), 'email');
        $st = $this->pdo()->prepare('SELECT * FROM athena_accounts WHERE ' . $emailEq . ' LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM athena_accounts WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findBySteamId(string $steamId): ?array
    {
        $steamId = trim($steamId);
        if ($steamId === '') {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM athena_accounts WHERE steam_id = ? LIMIT 1');
        $st->execute([$steamId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(array $fields): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO athena_accounts (public_id, email, password_hash, email_verified_at, steam_id, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            (string) ($fields['public_id'] ?? ('acc_' . bin2hex(random_bytes(12)))),
            strtolower(trim((string) ($fields['email'] ?? ''))),
            (string) ($fields['password_hash'] ?? ''),
            $fields['email_verified_at'] ?? null,
            $fields['steam_id'] ?? null,
            (string) ($fields['status'] ?? 'active'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveMemberships(int $accountId): array
    {
        $st = $this->pdo()->prepare(
            "SELECT m.*, t.name AS tenant_name, t.slug AS tenant_slug, t.name AS tenant_short_name, t.logo_url AS tenant_logo_url,
                    u.status AS user_status, u.callsign, u.display_name, u.avatar_url, u.steam_id AS user_steam_id
             FROM account_tenant_memberships m
             INNER JOIN tenants t ON t.id = m.tenant_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.account_id = ? AND m.status = 'active' AND u.status = 'active'
             ORDER BY m.is_default DESC, m.last_used_at DESC, m.id ASC"
        );
        $st->execute([$accountId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findMembership(int $accountId, int $tenantId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM account_tenant_memberships WHERE account_id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$accountId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function ensureMembership(int $accountId, int $tenantId, int $userId, bool $isDefault = false): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO account_tenant_memberships (account_id, tenant_id, user_id, is_default, status)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), status = VALUES(status)'
        );
        $st->execute([$accountId, $tenantId, $userId, $isDefault ? 1 : 0, 'active']);
    }

    public function touchMembership(int $accountId, int $tenantId): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE account_tenant_memberships SET last_used_at = NOW() WHERE account_id = ? AND tenant_id = ?'
        );
        $st->execute([$accountId, $tenantId]);
    }

    public function findSessionByAccessHash(string $hash): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM game_sessions WHERE access_token_hash = ? AND revoked_at IS NULL LIMIT 1'
        );
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findSessionByRefreshHash(string $hash): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM game_sessions WHERE refresh_token_hash = ? AND revoked_at IS NULL LIMIT 1'
        );
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function insertSession(array $fields): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO game_sessions (
                public_id, account_id, user_id, tenant_id, device_id,
                access_token_hash, refresh_token_hash, steam_id, pairing_token_hash,
                mod_version, extension_version, expires_at, refresh_expires_at, last_seen_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            (string) ($fields['public_id'] ?? ('gs_' . bin2hex(random_bytes(10)))),
            (int) $fields['account_id'],
            (int) $fields['user_id'],
            (int) $fields['tenant_id'],
            (string) $fields['device_id'],
            (string) $fields['access_token_hash'],
            (string) $fields['refresh_token_hash'],
            $fields['steam_id'] ?? null,
            $fields['pairing_token_hash'] ?? null,
            $fields['mod_version'] ?? null,
            $fields['extension_version'] ?? null,
            (string) $fields['expires_at'],
            (string) $fields['refresh_expires_at'],
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function rotateSessionTokens(int $sessionId, string $accessHash, string $refreshHash, string $expiresAt, string $refreshExpiresAt, ?string $steamId = null, ?string $pairingTokenHash = null): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE game_sessions
             SET access_token_hash = ?, refresh_token_hash = ?, expires_at = ?, refresh_expires_at = ?,
                 steam_id = ?, pairing_token_hash = ?, last_seen_at = NOW()
             WHERE id = ?'
        );
        $st->execute([$accessHash, $refreshHash, $expiresAt, $refreshExpiresAt, $steamId, $pairingTokenHash, $sessionId]);
    }

    public function revokeSession(int $sessionId): void
    {
        $st = $this->pdo()->prepare('UPDATE game_sessions SET revoked_at = NOW() WHERE id = ?');
        $st->execute([$sessionId]);
    }

    public function touchSession(int $sessionId): void
    {
        $st = $this->pdo()->prepare('UPDATE game_sessions SET last_seen_at = NOW() WHERE id = ?');
        $st->execute([$sessionId]);
    }

    public function insertOtp(string $email, string $codeHash, string $expiresAt): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO game_auth_otps (email, code_hash, expires_at) VALUES (?, ?, ?)'
        );
        $st->execute([$email, $codeHash, $expiresAt]);
    }

    public function findLatestOtp(string $email): ?array
    {
        $emailEq = SqlText::normalizedEquals($this->pdo(), 'email');
        $st = $this->pdo()->prepare(
            'SELECT * FROM game_auth_otps WHERE ' . $emailEq . ' AND consumed_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $st->execute([strtolower(trim($email))]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function consumeOtp(int $id): void
    {
        $st = $this->pdo()->prepare('UPDATE game_auth_otps SET consumed_at = NOW() WHERE id = ?');
        $st->execute([$id]);
    }

    public function bumpOtpAttempts(int $id): void
    {
        $st = $this->pdo()->prepare('UPDATE game_auth_otps SET attempts = attempts + 1 WHERE id = ?');
        $st->execute([$id]);
    }

    public function findPairing(int $accountId, string $deviceId): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM game_device_pairings
             WHERE account_id = ? AND device_id = ? AND revoked_at IS NULL LIMIT 1'
        );
        $st->execute([$accountId, $deviceId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function assignSteamIdIfEmpty(int $accountId, string $steamId): bool
    {
        $steamId = trim($steamId);
        if ($accountId < 1 || $steamId === '') {
            return false;
        }
        try {
            $st = $this->pdo()->prepare(
                "UPDATE athena_accounts
                 SET steam_id = ?
                 WHERE id = ? AND (steam_id IS NULL OR TRIM(steam_id) = '')"
            );
            $st->execute([$steamId, $accountId]);

            return $st->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function upsertPairing(int $accountId, string $deviceId, ?string $steamId, string $tokenHash): void
    {
        $steamId = trim((string) $steamId);
        if ($accountId <= 0 || $deviceId === '' || $steamId === '' || $tokenHash === '') {
            return;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO game_device_pairings (account_id, device_id, steam_id, pairing_token_hash)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE steam_id = VALUES(steam_id), pairing_token_hash = VALUES(pairing_token_hash),
                last_used_at = NOW(), revoked_at = NULL'
        );
        $st->execute([$accountId, $deviceId, $steamId, $tokenHash]);
    }

    public function touchPairing(int $id): void
    {
        $st = $this->pdo()->prepare('UPDATE game_device_pairings SET last_used_at = NOW() WHERE id = ?');
        $st->execute([$id]);
    }
}
