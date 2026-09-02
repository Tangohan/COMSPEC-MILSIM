<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Identity\UserIdentityMergeRules;
use App\Support\LazyDatabaseConnection;
use App\Support\SilentSchemaMigration;
use PDO;

final class UserCommunityMembershipRepository
{
    use LazyDatabaseConnection;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
        SilentSchemaMigration::run(base_path('bootstrap/user_community_identity_migration.php'), $pdo);
    }

    public function tablesExist(): bool
    {
        $st = $this->pdo()->query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_community_memberships' LIMIT 1"
        );

        return $st !== false && (bool) $st->fetchColumn();
    }

    public function hasMembership(int $userId, int $tenantId): bool
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist()) {
            return false;
        }
        $st = $this->pdo()->prepare(
            "SELECT 1 FROM user_community_memberships
             WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1"
        );
        $st->execute([$userId, $tenantId]);

        return (bool) $st->fetchColumn();
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function ensureMembership(int $userId, int $tenantId, array $profile = [], ?int $sourceUserId = null): void
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist()) {
            return;
        }
        $st = $this->pdo()->prepare(
            "INSERT INTO user_community_memberships (user_id, tenant_id, status, source_user_id, joined_at)
             VALUES (?, ?, 'active', ?, NOW())
             ON DUPLICATE KEY UPDATE status = 'active', left_at = NULL, updated_at = NOW()"
        );
        $st->execute([$userId, $tenantId, $sourceUserId]);
        $this->upsertProfile($userId, $tenantId, $profile);
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function upsertProfile(int $userId, int $tenantId, array $profile): void
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist()) {
            return;
        }
        $allowed = UserIdentityMergeRules::COMMUNITY_PROFILE_FIELDS;
        $cols = ['user_id', 'tenant_id'];
        $placeholders = ['?', '?'];
        $params = [$userId, $tenantId];
        $updates = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $profile)) {
                continue;
            }
            $cols[] = $key;
            $placeholders[] = '?';
            $params[] = $profile[$key];
            $updates[] = $key . ' = VALUES(' . $key . ')';
        }
        $sql = 'INSERT INTO user_community_profiles (' . implode(', ', $cols) . ')
                VALUES (' . implode(', ', $placeholders) . ')';
        if ($updates !== []) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) . ', updated_at = NOW()';
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE updated_at = NOW()';
        }
        $this->pdo()->prepare($sql)->execute($params);
    }

    /**
     * Complète une fiche communauté existante sans écraser les champs déjà renseignés.
     *
     * @param array<string, mixed> $profile
     */
    public function upsertProfileFillEmpty(int $userId, int $tenantId, array $profile): void
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist() || $profile === []) {
            return;
        }
        $existing = $this->findProfile($userId, $tenantId) ?? [];
        $fills = UserIdentityMergeRules::communityProfileFillEmpty($existing, $profile);
        if ($fills === []) {
            return;
        }
        if ($existing === []) {
            $this->upsertProfile($userId, $tenantId, $fills);

            return;
        }
        $set = [];
        $params = [];
        foreach ($fills as $key => $value) {
            $set[] = '`' . $key . '` = ?';
            $params[] = $value;
        }
        $params[] = $userId;
        $params[] = $tenantId;
        $this->pdo()->prepare(
            'UPDATE user_community_profiles SET ' . implode(', ', $set) . ', updated_at = NOW()
             WHERE user_id = ? AND tenant_id = ?'
        )->execute($params);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findProfile(int $userId, int $tenantId): ?array
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM user_community_profiles WHERE user_id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$userId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActiveForUser(int $userId): array
    {
        if ($userId < 1 || !$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT m.*, t.name AS tenant_name, t.slug AS tenant_slug,
                    p.display_name, p.callsign, p.status AS profile_status, p.grade_id, p.role_id,
                    p.athena_identifier, p.profile_slug
             FROM user_community_memberships m
             INNER JOIN tenants t ON t.id = m.tenant_id
             LEFT JOIN user_community_profiles p ON p.user_id = m.user_id AND p.tenant_id = m.tenant_id
             WHERE m.user_id = ? AND m.status = 'active'
             ORDER BY t.name ASC"
        );
        $st->execute([$userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function leave(int $userId, int $tenantId): void
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist()) {
            return;
        }
        $this->pdo()->prepare(
            "UPDATE user_community_memberships
             SET status = 'left', left_at = NOW(), updated_at = NOW()
             WHERE user_id = ? AND tenant_id = ?"
        )->execute([$userId, $tenantId]);
    }
}
