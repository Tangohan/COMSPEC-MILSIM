<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Identity\UserIdentityMergeRules;
use App\Support\LazyDatabaseConnection;
use App\Support\PortalAccessChoice;
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
        $st = $this->pdo()->prepare('SELECT status FROM user_community_memberships WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$userId, $tenantId]);
        $previous = $st->fetchColumn();
        $returning = $previous !== false && (string) $previous === 'left';
        if ($returning) {
            // Retour après un départ : nouveau séjour, rien de l'ancien ne revient (rôles, grade, numéro, ancienneté).
            $this->resetStayData($userId, $tenantId);
            $this->dropTenantRoles($userId, $tenantId);
        }
        $st = $this->pdo()->prepare(
            "INSERT INTO user_community_memberships (user_id, tenant_id, status, source_user_id, joined_at)
             VALUES (?, ?, 'active', ?, NOW())
             ON DUPLICATE KEY UPDATE joined_at = IF(status = 'left', NOW(), joined_at),
                 status = 'active', left_at = NULL, updated_at = NOW()"
        );
        $st->execute([$userId, $tenantId, $sourceUserId]);
        if ($previous === false || $returning) {
            $this->openPeriod($userId, $tenantId);
        }
        $this->upsertProfile($userId, $tenantId, $profile);
    }

    /** Début du séjour en cours dans cette communauté (Y-m-d H:i:s), null si pas membre actif. */
    public function currentPeriodStart(int $userId, int $tenantId): ?string
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo()->prepare("SELECT joined_at FROM user_community_memberships WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1");
        $st->execute([$userId, $tenantId]);
        $v = $st->fetchColumn();

        return $v === false || $v === null || $v === '' ? null : (string) $v;
    }

    private function periodsReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $this->pdo()->exec(
                    'CREATE TABLE IF NOT EXISTS user_community_membership_periods (
                      id bigint unsigned NOT NULL AUTO_INCREMENT, user_id int unsigned NOT NULL, tenant_id int unsigned NOT NULL,
                      joined_at datetime NOT NULL, left_at datetime DEFAULT NULL, PRIMARY KEY (id),
                      KEY idx_ucmp_user_tenant (user_id, tenant_id, joined_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
                );
                $ready = true;
            } catch (\Throwable) {
                $ready = false;
            }
        }

        return $ready;
    }

    private function openPeriod(int $userId, int $tenantId): void
    {
        if (!$this->periodsReady()) {
            return;
        }
        try {
            $this->pdo()->prepare('INSERT INTO user_community_membership_periods (user_id, tenant_id, joined_at) VALUES (?, ?, NOW())')->execute([$userId, $tenantId]);
        } catch (\Throwable) {
        }
    }

    private function closePeriod(int $userId, int $tenantId): void
    {
        if (!$this->periodsReady()) {
            return;
        }
        try {
            $this->pdo()->prepare('UPDATE user_community_membership_periods SET left_at = NOW() WHERE user_id = ? AND tenant_id = ? AND left_at IS NULL')->execute([$userId, $tenantId]);
        } catch (\Throwable) {
        }
    }

    /** Retour après un départ : grade, fonction et numéro repartent de zéro ; le nom et l'indicatif de la personne restent. */
    private function resetStayData(int $userId, int $tenantId): void
    {
        try {
            $this->pdo()->prepare(
                'UPDATE user_community_profiles SET role_id = NULL, grade_id = NULL, tenant_member_number = NULL, preferred_display_role_id = NULL, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?'
            )->execute([$userId, $tenantId]);
        } catch (\Throwable) {
        }
    }

    /** Un départ retire tout pouvoir dans cette communauté : plusieurs contrôles lisent tenant_user_roles sans regarder l'appartenance. */
    private function dropTenantRoles(int $userId, int $tenantId): void
    {
        try {
            $this->pdo()->prepare('DELETE FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        } catch (\Throwable) {
        }
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
            $value = $profile[$key];
            if (in_array($key, ['athena_identifier', 'profile_slug'], true)) {
                $trimmed = trim((string) ($value ?? ''));
                $value = $trimmed === '' ? null : $trimmed;
            }
            $cols[] = $key;
            $placeholders[] = '?';
            $params[] = $value;
            if (!UserIdentityMergeRules::isEmptyIdentityValue($value)
                && !($key === 'display_name' && is_string($value) && UserIdentityMergeRules::isMergedStubDisplayName($value))
                && !($key === 'status' && strtolower(trim((string) $value)) === 'pending' && !UserIdentityMergeRules::communityProfileHasSubstance($profile))
            ) {
                $updates[] = $key . ' = VALUES(' . $key . ')';
            }
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
        $this->closePeriod($userId, $tenantId);
        $this->dropTenantRoles($userId, $tenantId);
        try {
            $this->pdo()->prepare('UPDATE user_community_profiles SET role_id = NULL, preferred_display_role_id = NULL, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Toutes les appartenances (y compris quittées) pour décider du sas « pas d’organisation ».
     *
     * @return list<array<string, mixed>>
     */
    public function listAllForUser(int $userId): array
    {
        if ($userId < 1 || !$this->tablesExist()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            "SELECT m.*, t.name AS tenant_name, t.slug AS tenant_slug
             FROM user_community_memberships m
             INNER JOIN tenants t ON t.id = m.tenant_id
             WHERE m.user_id = ?
             ORDER BY t.name ASC"
        );
        $st->execute([$userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Dès qu’une vraie communauté existe, le sas « pas d’organisation » n’est plus
     * une appartenance active et ne conserve pas de copie du dossier métier.
     */
    public function leavePlaceholderMembershipsIfHasLiveOrg(int $userId): void
    {
        if ($userId < 1 || !$this->tablesExist()) {
            return;
        }
        $all = $this->listAllForUser($userId);
        $hasLive = false;
        foreach ($all as $row) {
            if (($row['status'] ?? '') !== 'active') {
                continue;
            }
            if (!PortalAccessChoice::membershipIsPlaceholder($row)) {
                $hasLive = true;
                break;
            }
        }
        if (!$hasLive) {
            return;
        }
        foreach ($all as $row) {
            if (!PortalAccessChoice::membershipIsPlaceholder($row)) {
                continue;
            }
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            if ($tenantId < 1) {
                continue;
            }
            $this->leave($userId, $tenantId);
            $this->clearCommunityProfileSubstance($userId, $tenantId);
        }
    }

    private function clearCommunityProfileSubstance(int $userId, int $tenantId): void
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tablesExist()) {
            return;
        }
        try {
            $this->pdo()->prepare(
                "UPDATE user_community_profiles
                 SET display_name = NULL, callsign = NULL, profile_slug = NULL,
                     athena_identifier = NULL, role_id = NULL, grade_id = NULL,
                     tenant_member_number = NULL, preferred_display_role_id = NULL,
                     updated_at = NOW()
                 WHERE user_id = ? AND tenant_id = ?"
            )->execute([$userId, $tenantId]);
        } catch (\Throwable) {
            // Schéma partiel : l’appartenance a déjà été marquée comme quittée.
        }
    }
}
