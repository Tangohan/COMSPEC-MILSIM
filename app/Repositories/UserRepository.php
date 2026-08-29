<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;

use App\Repositories\RoleAssignmentLogRepository;
use App\Services\Rbac\RoleCoherenceValidator;
use App\Services\User\UserProfileSlugService;
use InvalidArgumentException;
use PDO;

class UserRepository
{
    use LazyDatabaseConnection;


    private static ?bool $hasProfileSlugColumn = null;

    private static ?bool $hasServiceAccountColumn = null;
    private static ?bool $hasAthenaIdentifierColumn = null;

    private static ?bool $hasUserUnitsTable = null;

    private static ?bool $hasUserRolesTable = null;

    private static ?bool $hasEmailLoginOtpEnabledColumn = null;
    private static ?bool $hasTotpColumns = null;

    private static ?bool $hasProfileBannerUrlColumn = null;

    private static ?bool $hasDeletedAtColumn = null;

    private static ?bool $hasDeletionRequestColumns = null;

    /** @var array{join: string, grade_short: string, order_grade: string}|null */
    private static ?array $gradesConfigPublicRoster = null;

    /** Email réservé au compte technique par tenant (modération auto, cron, futurs tickets / webhooks). */
    public const SYSTEM_MODERATOR_EMAIL = 'system.moderation@internal.local';

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function hasProfileSlugColumn(): bool
    {
        if (self::$hasProfileSlugColumn === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_slug' LIMIT 1");
            self::$hasProfileSlugColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasProfileSlugColumn;
    }

    private function hasServiceAccountColumn(): bool
    {
        if (self::$hasServiceAccountColumn === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_service_account' LIMIT 1");
            self::$hasServiceAccountColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasServiceAccountColumn;
    }

    private function hasDeletedAtColumn(): bool
    {
        if (self::$hasDeletedAtColumn === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deleted_at' LIMIT 1");
            self::$hasDeletedAtColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasDeletedAtColumn;
    }

    private function hasDeletionRequestColumns(): bool
    {
        if (self::$hasDeletionRequestColumns === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'deletion_requested_at' LIMIT 1");
            self::$hasDeletionRequestColumns = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasDeletionRequestColumns;
    }

    private function hasAthenaIdentifierColumn(): bool
    {
        if (self::$hasAthenaIdentifierColumn === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'athena_identifier' LIMIT 1");
            self::$hasAthenaIdentifierColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasAthenaIdentifierColumn;
    }

    public function hasEmailLoginOtpEnabledColumn(): bool
    {
        if (self::$hasEmailLoginOtpEnabledColumn === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_login_otp_enabled' LIMIT 1");
            self::$hasEmailLoginOtpEnabledColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasEmailLoginOtpEnabledColumn;
    }

    public function hasTotpColumns(): bool
    {
        if (self::$hasTotpColumns === null) {
            $stmt = $this->pdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'users'
                   AND COLUMN_NAME = 'totp_enabled'
                 LIMIT 1"
            );
            self::$hasTotpColumns = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasTotpColumns;
    }

    public function hasProfileBannerUrlColumn(): bool
    {
        if (self::$hasProfileBannerUrlColumn === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_banner_url' LIMIT 1");
            self::$hasProfileBannerUrlColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasProfileBannerUrlColumn;
    }

    private function generateAthenaIdentifier(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $attempts = 0;

        do {
            $attempts++;
            $buf = random_bytes(9);
            $candidate = '';
            for ($i = 0; $i < 9; $i++) {
                $candidate .= $alphabet[ord($buf[$i]) % ($max + 1)];
            }
            $st = $this->pdo()->prepare('SELECT 1 FROM users WHERE athena_identifier = ? LIMIT 1');
            $st->execute([$candidate]);
            $exists = (bool) $st->fetchColumn();
            if (!$exists) {
                return $candidate;
            }
        } while ($attempts < 30);

        throw new \RuntimeException('Impossible de générer un identifiant Athena unique.');
    }

    /**
     * Fragment SQL (sans paramètres) pour exclure les courriels de comptes système sur le domaine interne.
     * Utile dans des sous-requêtes où l’on ne souhaite pas lier de paramètres.
     */
    public static function sqlLiteralExcludeTechnicalInternalEmails(string $alias = 'u'): string
    {
        $a = $alias;
        $lit = str_replace("'", "''", strtolower(self::SYSTEM_MODERATOR_EMAIL));

        return "LOWER(TRIM({$a}.email)) <> '{$lit}' AND LOWER(TRIM({$a}.email)) NOT LIKE 'system.%@internal.local'";
    }

    /**
     * Prédicat SQL + paramètres : comptes de service (si colonne) + courriels réservés du domaine interne.
     *
     * @return array{sql: string, params: list<mixed>}
     */
    private function technicalAccountExclusionPredicate(string $alias = 'u'): array
    {
        $fragments = [];
        $params = [];
        if ($this->hasServiceAccountColumn()) {
            $fragments[] = "({$alias}.is_service_account IS NULL OR {$alias}.is_service_account = 0)";
        }
        $fragments[] = "LOWER(TRIM({$alias}.email)) <> ?";
        $params[] = strtolower(self::SYSTEM_MODERATOR_EMAIL);
        $fragments[] = "LOWER(TRIM({$alias}.email)) NOT LIKE ?";
        $params[] = 'system.%@internal.local';

        return ['sql' => '(' . implode(' AND ', $fragments) . ')', 'params' => $params];
    }

    /** @var array<string,bool> */
    private static array $tableExistsCache = [];

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            self::$tableExistsCache[$table] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            self::$tableExistsCache[$table] = false;
        }

        return self::$tableExistsCache[$table];
    }

    /** @var array<string,bool> */
    private static array $columnExistsCache = [];

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnExistsCache)) {
            return self::$columnExistsCache[$key];
        }
        try {
            $stmt = $this->pdo()->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            self::$columnExistsCache[$key] = (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            self::$columnExistsCache[$key] = false;
        }

        return self::$columnExistsCache[$key];
    }

    /**
     * Identité légale (état civil) pour l’annuaire : la table dédiée n’existe que depuis la
     * migration 20260417103000. Sur une base non migrée, on retombe sur user_profiles puis sur
     * des colonnes nulles — l’annuaire reste affichable au lieu d’échouer en SQLSTATE 42S02.
     *
     * @return array{select: string, join: string, searchable: bool}
     */
    private function legalIdentityJoinFragments(string $alias = 'uli', string $userAlias = 'u'): array
    {
        foreach (['user_legal_identities', 'user_profiles'] as $table) {
            if ($this->tableExists($table) && $this->columnExists($table, 'first_name')) {
                return [
                    'select' => $alias . '.first_name, ' . $alias . '.last_name',
                    'join' => 'LEFT JOIN ' . $table . ' ' . $alias . ' ON ' . $alias . '.user_id = ' . $userAlias . '.id',
                    'searchable' => true,
                ];
            }
        }

        return [
            'select' => 'NULL AS first_name, NULL AS last_name',
            'join' => '',
            'searchable' => false,
        ];
    }

    /**
     * Jointure fonction métier : une seule ligne par membre (évite le dédoublement
     * quand plusieurs pivots ont encore is_primary = 1).
     *
     * @return array{join: string, select_as_primary_role: string, select_as_job_role_display: string}
     */
    private function primaryJobRoleJoinFragments(string $userAlias = 'u'): array
    {
        $empty = [
            'join' => '',
            'select_as_primary_role' => "'' AS primary_role",
            'select_as_job_role_display' => "'' AS job_role_display",
        ];
        if (!$this->tableExists('personnel_profile_job_roles') || !$this->tableExists('personnel_job_roles')) {
            return $empty;
        }

        $displayExpr = "TRIM(CONCAT(COALESCE(pjr.name, ''), IF(pjrole.role_detail IS NOT NULL AND pjrole.role_detail <> '', CONCAT(' — ', pjrole.role_detail), '')))";

        return [
            'join' => 'LEFT JOIN personnel_profile_job_roles pjrole ON pjrole.id = (
                    SELECT pj2.id FROM personnel_profile_job_roles pj2
                    WHERE pj2.user_id = ' . $userAlias . '.id AND pj2.tenant_id = ' . $userAlias . '.tenant_id
                    ORDER BY pj2.is_primary DESC, pj2.sort_order ASC, pj2.id ASC
                    LIMIT 1
                )
                LEFT JOIN personnel_job_roles pjr ON pjr.id = pjrole.personnel_job_role_id AND pjr.tenant_id = ' . $userAlias . '.tenant_id',
            'select_as_primary_role' => $displayExpr . ' AS primary_role',
            'select_as_job_role_display' => $displayExpr . ' AS job_role_display',
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function dedupeRowsByUserId(array $rows): array
    {
        $unique = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || isset($unique[$id])) {
                continue;
            }
            $unique[$id] = $row;
        }

        return array_values($unique);
    }

    private function hasUserUnitsTable(): bool
    {
        if (self::$hasUserUnitsTable === null) {
            try {
                $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_units' LIMIT 1");
                self::$hasUserUnitsTable = $stmt && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                self::$hasUserUnitsTable = false;
            }
        }

        return self::$hasUserUnitsTable;
    }

    private function hasUserRolesTable(): bool
    {
        if (self::$hasUserRolesTable === null) {
            try {
                $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_roles' LIMIT 1");
                self::$hasUserRolesTable = $stmt && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                self::$hasUserRolesTable = false;
            }
        }

        return self::$hasUserRolesTable;
    }

    private static ?bool $hasTenantUserRolesTable = null;

    public function hasTenantUserRolesTable(): bool
    {
        if (self::$hasTenantUserRolesTable === null) {
            try {
                $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_user_roles' LIMIT 1");
                self::$hasTenantUserRolesTable = $stmt && (bool) $stmt->fetchColumn();
            } catch (\Throwable) {
                self::$hasTenantUserRolesTable = false;
            }
        }

        return self::$hasTenantUserRolesTable;
    }

    /**
     * Rôles organisation (communauté / intra) attribués à l’utilisateur.
     *
     * @return list<int>
     */
    public function listOrganizationRoleIdsForUser(int $userId): array
    {
        if ($this->hasTenantUserRolesTable()) {
            $stmt = $this->pdo()->prepare(
                'SELECT DISTINCT tur.role_id FROM tenant_user_roles tur
                 INNER JOIN users u ON u.id = tur.user_id AND u.tenant_id = tur.tenant_id
                 WHERE tur.user_id = ? AND tur.org_unit_id IS NULL
                 ORDER BY tur.role_id ASC'
            );
            $stmt->execute([$userId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($ids !== []) {
                return $ids;
            }
        }
        if (!$this->hasUserRolesTable()) {
            $u = $this->findById($userId, null);

            return $u && !empty($u['role_id']) ? [(int) $u['role_id']] : [];
        }
        $stmt = $this->pdo()->prepare('SELECT role_id FROM user_roles WHERE user_id = ? ORDER BY role_id ASC');
        $stmt->execute([$userId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        if ($ids === []) {
            $u = $this->findById($userId, null);
            if ($u && !empty($u['role_id'])) {
                return [(int) $u['role_id']];
            }
        }

        return $ids;
    }

    /**
     * Identifiants de rôles tenant pour RBAC (union multi-rôles + repli sur users.role_id).
     *
     * @return list<int>
     */
    public function tenantRoleIdsForRbac(int $userId, ?int $legacyRoleId): array
    {
        $ids = $this->listOrganizationRoleIdsForUser($userId);
        if ($ids === [] && $legacyRoleId !== null && $legacyRoleId > 0) {
            return [$legacyRoleId];
        }

        return $ids;
    }

    public function userHasTenantRole(int $userId, int $roleId): bool
    {
        if ($roleId < 1) {
            return false;
        }
        if ($this->hasTenantUserRolesTable()) {
            $st = $this->pdo()->prepare(
                'SELECT 1 FROM tenant_user_roles WHERE user_id = ? AND role_id = ? AND org_unit_id IS NULL LIMIT 1'
            );
            $st->execute([$userId, $roleId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }
        if ($this->hasUserRolesTable()) {
            $st = $this->pdo()->prepare('SELECT 1 FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1');
            $st->execute([$userId, $roleId]);
            if ($st->fetchColumn()) {
                return true;
            }
        }
        $u = $this->findById($userId, null);

        return $u !== null && (int) ($u['role_id'] ?? 0) === $roleId;
    }

    /**
     * Remplace les rôles organisation de l’utilisateur et synchronise users.role_id (rôle « principal » affichage).
     *
     * @param list<int> $roleIds
     * @throws InvalidArgumentException si le jeu de rôles viole les règles de cohérence (sauf si $skipCoherenceCheck).
     */
    public function syncOrganizationRoles(int $userId, int $tenantId, array $roleIds, ?int $actorUserId = null, bool $skipCoherenceCheck = false): void
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $x) => $x > 0)));
        if (!$skipCoherenceCheck) {
            $err = RoleCoherenceValidator::validateOrgRoleSet($this->pdo, $tenantId, $roleIds);
            if ($err !== null) {
                throw new InvalidArgumentException($err);
            }
        }
        $beforeIds = $this->listOrganizationRoleIdsForUser($userId);
        if (!$this->hasUserRolesTable()) {
            $primary = $roleIds[0] ?? null;
            $this->pdo()->prepare('UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$primary, $userId, $tenantId]);

            return;
        }
        $this->pdo()->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        $valid = [];
        if ($roleIds !== []) {
            $ph = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $this->pdo()->prepare(
                "SELECT id FROM roles WHERE tenant_id = ? AND role_layer IN ('community','intra') AND id IN ({$ph})"
            );
            $stmt->execute(array_merge([$tenantId], $roleIds));
            $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $ins = $this->pdo()->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
            foreach ($valid as $rid) {
                $ins->execute([$userId, $rid]);
            }
        }
        if ($this->hasTenantUserRolesTable()) {
            $this->pdo()->prepare(
                'DELETE FROM tenant_user_roles WHERE user_id = ? AND tenant_id = ? AND org_unit_id IS NULL'
            )->execute([$userId, $tenantId]);
            if ($valid !== []) {
                $insTur = $this->pdo()->prepare(
                    'INSERT INTO tenant_user_roles (tenant_id, user_id, role_id, org_unit_id, co_unit_id, created_at) VALUES (?, ?, ?, NULL, 0, NOW())'
                );
                foreach ($valid as $rid) {
                    try {
                        $insTur->execute([$tenantId, $userId, $rid]);
                    } catch (\PDOException) {
                    }
                }
            }
        }
        $primary = $this->computePrimaryRoleIdForTenant($tenantId, $valid);
        $this->pdo()->prepare('UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$primary, $userId, $tenantId]);

        if ($this->hasPreferredDisplayRoleColumn()) {
            $prefSt = $this->pdo()->prepare('SELECT preferred_display_role_id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
            $prefSt->execute([$userId, $tenantId]);
            $prefRow = $prefSt->fetch(PDO::FETCH_ASSOC);
            $pref = isset($prefRow['preferred_display_role_id']) ? (int) $prefRow['preferred_display_role_id'] : 0;
            if ($pref > 0 && !in_array($pref, $valid, true)) {
                $this->pdo()->prepare('UPDATE users SET preferred_display_role_id = NULL, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                    ->execute([$userId, $tenantId]);
            }
        }

        $logger = new RoleAssignmentLogRepository();
        $beforeSet = array_fill_keys($beforeIds, true);
        $afterSet = array_fill_keys($valid, true);
        foreach ($beforeIds as $rid) {
            if (!isset($afterSet[$rid])) {
                $logger->logRevoke($tenantId, $userId, $rid, $actorUserId, null);
            }
        }
        foreach ($valid as $rid) {
            if (!isset($beforeSet[$rid])) {
                $logger->logAssign($tenantId, $userId, $rid, $actorUserId, null);
            }
        }
    }

    /**
     * Ajoute un rôle organisation (périmètre tenant) s'il n'est pas déjà présent.
     *
     * @throws InvalidArgumentException si la fusion viole la cohérence métier
     *
     * @return bool true si le rôle a été ajouté, false s'il était déjà attribué
     */
    public function addOrganizationRoleIfMissing(int $userId, int $tenantId, int $roleId, ?int $actorUserId = null): bool
    {
        if ($userId < 1 || $tenantId < 1 || $roleId < 1) {
            return false;
        }
        $current = $this->listOrganizationRoleIdsForUser($userId);
        if (in_array($roleId, $current, true)) {
            return false;
        }
        $merged = array_merge($current, [$roleId]);
        $this->syncOrganizationRoles($userId, $tenantId, $merged, $actorUserId);

        return true;
    }

    private function hasPreferredDisplayRoleColumn(): bool
    {
        static $v;
        if ($v !== null) {
            return $v;
        }
        try {
            $st = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_display_role_id' LIMIT 1");
            $v = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $v = false;
        }

        return $v;
    }

    public function setPreferredDisplayRoleId(int $userId, int $tenantId, ?int $roleId): void
    {
        if (!$this->hasPreferredDisplayRoleColumn()) {
            return;
        }
        if ($roleId !== null && $roleId > 0 && !$this->userHasTenantRole($userId, $roleId)) {
            return;
        }
        $this->pdo()->prepare('UPDATE users SET preferred_display_role_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$roleId, $userId, $tenantId]);
    }

    /**
     * Rôle « principal » (affichage / session) pour un ensemble de rôles valides tenant.
     *
     * @param list<int> $roleIds
     */
    public function peekPrimaryRoleIdForTenant(int $tenantId, array $roleIds): ?int
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $x): bool => $x > 0)));
        if ($roleIds === []) {
            return null;
        }
        $ph = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->pdo()->prepare(
            "SELECT id FROM roles WHERE tenant_id = ? AND role_layer IN ('community','intra') AND id IN ({$ph})"
        );
        $stmt->execute(array_merge([$tenantId], $roleIds));
        $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        return $this->computePrimaryRoleIdForTenant($tenantId, $valid);
    }

    /**
     * @param list<int> $validRoleIds
     */
    private function computePrimaryRoleIdForTenant(int $tenantId, array $validRoleIds): ?int
    {
        if ($validRoleIds === []) {
            return null;
        }
        $validRoleIds = array_values(array_unique($validRoleIds));
        $ph = implode(',', array_fill(0, count($validRoleIds), '?'));
        $stmt = $this->pdo()->prepare(
            "SELECT id, slug, role_layer, COALESCE(semantic_tier, 'function') AS semantic_tier, COALESCE(display_priority, 0) AS display_priority
             FROM roles WHERE tenant_id = ? AND id IN ({$ph})"
        );
        $stmt->execute(array_merge([$tenantId], $validRoleIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return null;
        }
        foreach (['community_owner', 'tenant_admin'] as $slug) {
            foreach ($rows as $r) {
                if (($r['slug'] ?? '') === $slug) {
                    return (int) $r['id'];
                }
            }
        }
        usort(
            $rows,
            static function (array $a, array $b): int {
                $rank = static function (array $x): int {
                    return match ((string) ($x['semantic_tier'] ?? 'function')) {
                        'authority' => 1,
                        'function' => 2,
                        'liaison' => 3,
                        'support' => 4,
                        'specialty' => 5,
                        'status' => 6,
                        default => 2,
                    };
                };
                $cmp = $rank($a) <=> $rank($b);
                if ($cmp !== 0) {
                    return $cmp;
                }
                $cmp = ((int) ($a['display_priority'] ?? 0)) <=> ((int) ($b['display_priority'] ?? 0));
                if ($cmp !== 0) {
                    return $cmp;
                }
                $la = (string) ($a['role_layer'] ?? '');
                $lb = (string) ($b['role_layer'] ?? '');
                if ($la === 'community' && $lb !== 'community') {
                    return -1;
                }
                if ($lb === 'community' && $la !== 'community') {
                    return 1;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
        );

        return (int) $rows[0]['id'];
    }

    /**
     * Crée si besoin le compte technique « Modération automatique » pour le tenant (statut inactive, non connectable).
     *
     * @return int id utilisateur ou 0 si colonne is_service_account absente
     */
    public function ensureSystemModeratorUser(int $tenantId): int
    {
        if (!$this->hasServiceAccountColumn()) {
            return 0;
        }
        $existing = $this->findByEmail($tenantId, self::SYSTEM_MODERATOR_EMAIL);
        if ($existing) {
            return (int) $existing['id'];
        }
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2ID);
        $sql = 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, status, is_service_account, created_at, updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())';
        try {
            $this->pdo()->prepare($sql)->execute([$tenantId, self::SYSTEM_MODERATOR_EMAIL, $hash, 'Modération automatique', 'SYSMOD', 'inactive']);
        } catch (\PDOException $e) {
            $again = $this->findByEmail($tenantId, self::SYSTEM_MODERATOR_EMAIL);
            if ($again) {
                return (int) $again['id'];
            }
            throw $e;
        }

        return (int) $this->pdo()->lastInsertId();
    }

    public function isServiceAccount(int $userId): bool
    {
        if (!$this->hasServiceAccountColumn()) {
            return false;
        }
        $u = $this->findById($userId);
        return $u !== null && !empty($u['is_service_account']);
    }

    public function findByProfileSlug(int $tenantId, string $slug): ?array
    {
        if (!$this->hasProfileSlugColumn()) {
            return null;
        }
        $slug = strtolower(trim($slug));
        if ($slug === '' || !UserProfileSlugService::isValidFormat($slug)) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE tenant_id = ? AND LOWER(profile_slug) = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function isProfileSlugTaken(int $tenantId, string $slug, ?int $exceptUserId = null): bool
    {
        if (!$this->hasProfileSlugColumn()) {
            return false;
        }
        $slug = strtolower(trim($slug));
        $sql = 'SELECT 1 FROM users WHERE tenant_id = ? AND LOWER(profile_slug) = ?';
        $params = [$tenantId, $slug];
        if ($exceptUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptUserId;
        }
        $stmt = $this->pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function findByEmail(int $tenantId, string $email): ?array
    {
        $email = strtolower(trim($email));
        $freed = $this->sqlEmailStillClaimedPredicate('users');
        $sql = 'SELECT * FROM users WHERE tenant_id = ? AND LOWER(TRIM(email)) = ? AND ' . $freed['sql'] . ' LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$tenantId, $email], $freed['params']));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tous les comptes partageant exactement la même adresse (multi-communautés).
     * Ignore les comptes déjà anonymisés / soft-deleted (e-mail libéré).
     *
     * @return list<int>
     */
    public function listIdsByEmailNormalized(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return [];
        }
        $freed = $this->sqlEmailStillClaimedPredicate('users');
        $stmt = $this->pdo()->prepare(
            'SELECT id FROM users WHERE LOWER(TRIM(email)) = ? AND ' . $freed['sql']
        );
        $stmt->execute(array_merge([$email], $freed['params']));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Libère une adresse encore portée par des lignes déjà anonymisées / soft-deleted
     * (cas où deleted_at ou le libellé ont été posés sans remplacer l’e-mail).
     * Sans cela, l’unicité SQL (tenant_id, email) bloquerait toute réinscription.
     *
     * @return list<int> ids anonymisés (pour scrub des tables liées côté service)
     */
    public function releaseEmailHeldByDeletedAccounts(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || str_ends_with($email, '@deleted.invalid')) {
            return [];
        }

        $conditions = ["(display_name = 'Compte supprimé' AND status = 'inactive')"];
        if ($this->hasDeletedAtColumn()) {
            $conditions[] = 'deleted_at IS NOT NULL';
        }
        $sql = 'SELECT id FROM users WHERE LOWER(TRIM(email)) = ? AND (' . implode(' OR ', $conditions) . ')';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$email]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $done = [];
        foreach ($ids as $id) {
            if ($this->anonymizeUserIdentity($id, 0)) {
                $done[] = $id;
            }
        }

        return $done;
    }

    /**
     * Indique si l’adresse est encore réservée uniquement par un compte en délai de
     * rétractation RGPD (pas encore anonymisé) — utile pour un message d’inscription clair.
     */
    public function emailPendingDeletionGlobally(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || strcasecmp($email, self::SYSTEM_MODERATOR_EMAIL) === 0) {
            return false;
        }
        if (!$this->hasDeletionRequestColumns()) {
            return false;
        }
        $freed = $this->sqlEmailStillClaimedPredicate('users');
        $sql = 'SELECT 1 FROM users
                WHERE LOWER(TRIM(email)) = ?
                  AND deletion_requested_at IS NOT NULL
                  AND ' . $freed['sql'];
        $params = array_merge([$email], $freed['params']);
        if ($this->hasServiceAccountColumn()) {
            $sql .= ' AND (is_service_account IS NULL OR is_service_account = 0)';
        }
        $stmt = $this->pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Comptes dont l’e-mail est encore « pris » pour inscription / login.
     * Exclut soft-delete et e-mails déjà remplacés par @deleted.invalid.
     *
     * @return array{sql: string, params: list<mixed>}
     */
    private function sqlEmailStillClaimedPredicate(string $alias = 'users'): array
    {
        $a = $alias !== '' ? $alias . '.' : '';
        $fragments = ["LOWER(TRIM({$a}email)) NOT LIKE '%@deleted.invalid'"];
        $params = [];
        if ($this->hasDeletedAtColumn()) {
            $fragments[] = "{$a}deleted_at IS NULL";
        }

        return ['sql' => implode(' AND ', $fragments), 'params' => $params];
    }

    /** RGPD : programme la suppression du compte (délai de rétractation). */
    public function requestDeletion(int $userId, int $tenantId, string $requestedAt, string $scheduledAt): bool
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET deletion_requested_at = ?, deletion_scheduled_at = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$requestedAt, $scheduledAt, $userId, $tenantId]);
    }

    /** RGPD : annule une suppression de compte programmée (reconnexion pendant le délai). */
    public function cancelDeletion(int $userId, int $tenantId): bool
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET deletion_requested_at = NULL, deletion_scheduled_at = NULL, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );

        return $stmt->execute([$userId, $tenantId]);
    }

    /** RGPD : comptes dont le délai de rétractation est dépassé (à anonymiser). */
    public function listDueForDeletionAnonymization(): array
    {
        $extra = '';
        if ($this->hasDeletedAtColumn()) {
            $extra = ' AND deleted_at IS NULL';
        }
        $stmt = $this->pdo()->query(
            "SELECT id, tenant_id FROM users
             WHERE deletion_requested_at IS NOT NULL
               AND deletion_scheduled_at IS NOT NULL
               AND deletion_scheduled_at <= NOW()
               AND LOWER(TRIM(email)) NOT LIKE '%@deleted.invalid'
               {$extra}"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * RGPD : anonymise un compte après le délai de rétractation (ligne users uniquement).
     * Préférer {@see \App\Services\Account\AccountDeletionService::anonymizeAccountCompletely}
     * pour le scrub des tables liées.
     */
    public function anonymizeForDeletion(int $userId, int $tenantId): bool
    {
        $row = $this->findById($userId, $tenantId);
        if ($row === null) {
            return false;
        }

        return $this->anonymizeUserIdentity($userId, 0);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM users WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>> indexé par id utilisateur
     */
    public function findByIdsForTenant(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM users WHERE tenant_id = ? AND id IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([$tenantId], $ids));
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $uid = (int) ($row['id'] ?? 0);
            if ($uid > 0) {
                $out[$uid] = $row;
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function findBySteamIdForTenant(int $tenantId, string $steamId): ?array
    {
        $sid = \App\Support\SteamId::normalize($steamId);
        if ($sid === null) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE tenant_id = ? AND steam_id = ? LIMIT 1');
        $stmt->execute([$tenantId, $sid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Compte Athena lié à un Steam ID (tous tenants). Utile pour la liaison Arma sans code court.
     *
     * @return array<string, mixed>|null
     */
    public function findBySteamId(string $steamId): ?array
    {
        $sid = \App\Support\SteamId::normalize($steamId);
        if ($sid === null) {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM users WHERE steam_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$sid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }

        // Repli : anciennes saisies (espaces, STEAM_x:y:z, [U:1:n]) non encore normalisées en base.
        $digits = preg_replace('/\D/', '', $sid) ?? $sid;
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM users
             WHERE steam_id IS NOT NULL AND TRIM(steam_id) <> ''
               AND (
                 REPLACE(REPLACE(REPLACE(steam_id, ' ', ''), '-', ''), '\t', '') = ?
                 OR steam_id LIKE 'STEAM_%'
                 OR steam_id LIKE 'U:1:%'
                 OR steam_id LIKE '[U:1:%'
               )
             ORDER BY id DESC
             LIMIT 80"
        );
        $stmt->execute([$digits]);
        while ($candidate = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $normalized = \App\Support\SteamId::normalize((string) ($candidate['steam_id'] ?? ''));
            if ($normalized === $sid) {
                return $candidate;
            }
        }

        return null;
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferred_grade_format' LIMIT 1");
        $hasGradeColumns = $stmt && $stmt->fetch();
        $hasAthenaIdentifier = $this->hasAthenaIdentifierColumn();
        // 0 / '' ne sont pas des FK valides : MySQL refuse grade_id=0 (contrainte users_grade_id_fk).
        $data['grade_id'] = $this->normalizeOptionalGradeId($data['grade_id'] ?? null);
        $athenaIdentifier = $hasAthenaIdentifier
            ? trim((string) ($data['athena_identifier'] ?? ''))
            : '';
        if ($hasAthenaIdentifier && $athenaIdentifier === '') {
            $athenaIdentifier = $this->generateAthenaIdentifier();
        }

        // Toujours normaliser l’e-mail (évite doublons casing / espaces).
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
        if ($data['email'] === '') {
            throw new \InvalidArgumentException('E-mail requis pour créer un compte.');
        }

        $profileSlug = null;
        if ($this->hasProfileSlugColumn()) {
            if (isset($data['profile_slug']) && $data['profile_slug'] !== null && trim((string) $data['profile_slug']) !== '') {
                $profileSlug = strtolower(trim((string) $data['profile_slug']));
            } else {
                $profileSlug = UserProfileSlugService::generateForNewUser(
                    isset($data['display_name']) ? (string) $data['display_name'] : null,
                    (string) ($data['email'] ?? ''),
                    fn (string $s) => $this->isProfileSlugTaken($tenantId, $s)
                );
            }
        }

        if ($hasGradeColumns) {
            if ($this->hasProfileSlugColumn()) {
                $stmt = $this->pdo()->prepare(
                    $hasAthenaIdentifier
                        ? 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, profile_slug, athena_identifier, role_id, grade_id, status, nationality_code, preferred_grade_format, professional_category_code, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        : 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, profile_slug, role_id, grade_id, status, nationality_code, preferred_grade_format, professional_category_code, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $params = [
                    $tenantId,
                    $data['email'],
                    $data['password_hash'],
                    $data['display_name'] ?? null,
                    $data['callsign'] ?? null,
                    $profileSlug,
                ];
                if ($hasAthenaIdentifier) {
                    $params[] = $athenaIdentifier;
                }
                $params = array_merge($params, [
                    $data['role_id'] ?? null,
                    $data['grade_id'] ?? null,
                    $data['status'] ?? 'pending',
                    $data['nationality_code'] ?? null,
                    $data['preferred_grade_format'] ?? 'classic',
                    $data['professional_category_code'] ?? null,
                ]);
                $stmt->execute($params);
            } else {
                $stmt = $this->pdo()->prepare(
                    $hasAthenaIdentifier
                        ? 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, athena_identifier, role_id, grade_id, status, nationality_code, preferred_grade_format, professional_category_code, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        : 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, nationality_code, preferred_grade_format, professional_category_code, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $params = [
                    $tenantId,
                    $data['email'],
                    $data['password_hash'],
                    $data['display_name'] ?? null,
                    $data['callsign'] ?? null,
                ];
                if ($hasAthenaIdentifier) {
                    $params[] = $athenaIdentifier;
                }
                $params = array_merge($params, [
                    $data['role_id'] ?? null,
                    $data['grade_id'] ?? null,
                    $data['status'] ?? 'pending',
                    $data['nationality_code'] ?? null,
                    $data['preferred_grade_format'] ?? 'classic',
                    $data['professional_category_code'] ?? null,
                ]);
                $stmt->execute($params);
            }
        } else {
            if ($this->hasProfileSlugColumn()) {
                $stmt = $this->pdo()->prepare(
                    $hasAthenaIdentifier
                        ? 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, profile_slug, athena_identifier, role_id, grade_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        : 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, profile_slug, role_id, grade_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $params = [
                    $tenantId,
                    $data['email'],
                    $data['password_hash'],
                    $data['display_name'] ?? null,
                    $data['callsign'] ?? null,
                    $profileSlug,
                ];
                if ($hasAthenaIdentifier) {
                    $params[] = $athenaIdentifier;
                }
                $params = array_merge($params, [
                    $data['role_id'] ?? null,
                    $data['grade_id'] ?? null,
                    $data['status'] ?? 'pending',
                ]);
                $stmt->execute($params);
            } else {
                $stmt = $this->pdo()->prepare(
                    $hasAthenaIdentifier
                        ? 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, athena_identifier, role_id, grade_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        : 'INSERT INTO users (tenant_id, email, password_hash, display_name, callsign, role_id, grade_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $params = [
                    $tenantId,
                    $data['email'],
                    $data['password_hash'],
                    $data['display_name'] ?? null,
                    $data['callsign'] ?? null,
                ];
                if ($hasAthenaIdentifier) {
                    $params[] = $athenaIdentifier;
                }
                $params = array_merge($params, [
                    $data['role_id'] ?? null,
                    $data['grade_id'] ?? null,
                    $data['status'] ?? 'pending',
                ]);
                $stmt->execute($params);
            }
        }
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPersonnelDirectory(int $tenantId, int $limit = 120): array
    {
        $limit = max(10, min(300, $limit));
        $hasAthenaIdentifier = $this->hasAthenaIdentifierColumn();
        $pack = $this->technicalAccountExclusionPredicate('u');
        $athenaSelect = $hasAthenaIdentifier ? 'u.athena_identifier' : "'' AS athena_identifier";
        $stmt = $this->pdo()->prepare(
            'SELECT u.id, u.display_name, u.callsign, u.profile_slug, ' . $athenaSelect . ', u.avatar_url,
                    p.character_name
             FROM users u
             LEFT JOIN personnel_profiles p ON p.user_id = u.id
             WHERE u.tenant_id = ? AND ' . $pack['sql'] . '
             ORDER BY u.display_name ASC
             LIMIT ?'
        );
        $stmt->execute(array_merge([$tenantId], $pack['params'], [$limit]));

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private static ?array $gradesConfigDirectory = null;

    /**
     * Jointure / colonnes grades pour l’annuaire : référentiel (label_short/label_long) ou ancienne table
     * tenant (short_name/name). Même logique de détection que {@see getGradesConfigForPublicRoster()}.
     *
     * @return array{join: string, select: string}
     */
    private function getGradesConfigForDirectory(): array
    {
        if (self::$gradesConfigDirectory !== null) {
            return self::$gradesConfigDirectory;
        }
        $stmt = $this->pdo()->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME IN ('name', 'label_long', 'tenant_id')"
        );
        $columns = $stmt ? array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME') : [];
        $hasLabelLong = in_array('label_long', $columns, true);
        $hasTenantId = in_array('tenant_id', $columns, true);
        if ($hasLabelLong) {
            self::$gradesConfigDirectory = [
                'select' => 'g.label_short AS grade_short, g.label_long AS grade_long, COALESCE(g.sort_order, 999) AS grade_sort_order',
                'join' => $hasTenantId
                    ? 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id'
                    : 'LEFT JOIN grades g ON g.id = u.grade_id',
            ];
        } else {
            self::$gradesConfigDirectory = [
                'select' => 'g.short_name AS grade_short, g.name AS grade_long, COALESCE(g.rank_order, 999) AS grade_sort_order',
                'join' => 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id',
            ];
        }

        return self::$gradesConfigDirectory;
    }

    /**
     * Annuaire personnel enrichi : grade, affectation principale, matricule, ancienneté, rôle métier —
     * en une seule requête (jointures), sans boucle N+1. Recherche optionnelle (nom affiché, prénom/nom,
     * indicatif, slug, identifiant Athena, nom de personnage).
     *
     * @return list<array<string, mixed>>
     */
    public function listPersonnelDirectoryRich(int $tenantId, string $query, int $limit = 150, bool $includeInactiveAndDeleted = false): array
    {
        $limit = max(10, min(300, $limit));
        $hasAthenaIdentifier = $this->hasAthenaIdentifierColumn();
        $pack = $this->technicalAccountExclusionPredicate('u');
        $athenaSelect = $hasAthenaIdentifier ? 'u.athena_identifier' : "'' AS athena_identifier";
        $gc = $this->getGradesConfigForDirectory();
        $legal = $this->legalIdentityJoinFragments('uli', 'u');

        $where = ['u.tenant_id = ?', $pack['sql']];
        $params = array_merge([$tenantId], $pack['params']);

        if (!$includeInactiveAndDeleted) {
            $where[] = "u.status = 'active'";
            if ($this->hasDeletedAtColumn()) {
                $where[] = 'u.deleted_at IS NULL';
            }
            $where[] = "(u.display_name IS NULL OR TRIM(u.display_name) <> 'Compte supprimé')";
        }

        $q = trim($query);
        if ($q !== '') {
            $term = '%' . $q . '%';
            $athenaFilter = $hasAthenaIdentifier
                ? " OR (u.athena_identifier IS NOT NULL AND TRIM(u.athena_identifier) <> '' AND u.athena_identifier LIKE ?)"
                : '';
            $legalFilter = $legal['searchable']
                ? ' OR (uli.first_name IS NOT NULL AND uli.first_name LIKE ?)
                 OR (uli.last_name IS NOT NULL AND uli.last_name LIKE ?)
                 OR (CONCAT(TRIM(COALESCE(uli.first_name, \'\')), \' \', TRIM(COALESCE(uli.last_name, \'\'))) LIKE ?)'
                : '';
            $where[] = '(u.display_name LIKE ?' . $legalFilter . '
                 OR (u.callsign IS NOT NULL AND TRIM(u.callsign) <> \'\' AND u.callsign LIKE ?)
                 OR (u.profile_slug IS NOT NULL AND TRIM(u.profile_slug) <> \'\' AND u.profile_slug LIKE ?)
                 OR (pp.character_name IS NOT NULL AND pp.character_name LIKE ?)' . $athenaFilter . ')';
            $params[] = $term;
            if ($legal['searchable']) {
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            if ($hasAthenaIdentifier) {
                $params[] = $term;
            }
        }

        $jobRole = $this->primaryJobRoleJoinFragments('u');
        $deployableSelect = $this->personnelProfilesHasColumn('deployable') ? 'pp.deployable' : 'NULL AS deployable';
        $hasExtras = $this->tableExists('personnel_extras');
        $extrasSelect = $hasExtras ? 'pex.service_number, pex.date_of_enlistment' : 'NULL AS service_number, NULL AS date_of_enlistment';
        $extrasJoin = $hasExtras ? 'LEFT JOIN personnel_extras pex ON pex.user_id = u.id' : '';
        $unitBlurbSelect = $this->unitsHasColumn('public_blurb')
            ? 'un.public_blurb AS unit_blurb'
            : 'NULL AS unit_blurb';

        $sql = 'SELECT u.id, u.display_name, u.callsign, u.profile_slug, ' . $athenaSelect . ', u.avatar_url, u.status, u.role_id,
                       ' . $legal['select'] . ',
                       ' . $gc['select'] . ',
                       un.name AS unit_name, un.code AS unit_code, ' . $unitBlurbSelect . ', pp.primary_unit_id,
                       pp.character_name, pp.matricule_internal, pp.enlistment_date, ' . $jobRole['select_as_primary_role'] . ',
                       pp.radio_assigned, pp.readiness_score, pp.rank_display, pp.rank_display_override, ' . $deployableSelect . ',
                       ' . $extrasSelect . '
                FROM users u
                ' . $legal['join'] . '
                LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                ' . $extrasJoin . '
                ' . $gc['join'] . '
                ' . $jobRole['join'] . '
                LEFT JOIN units un ON un.id = pp.primary_unit_id AND un.tenant_id = u.tenant_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY u.display_name ASC
                LIMIT ?';
        $params[] = $limit;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return \App\Support\PersonnelDirectoryHints::enrichUnitHints(
            $tenantId,
            $this->dedupeRowsByUserId($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [])
        );
    }

    /**
     * Enrichissement RH pour le tableur effectifs : grade, unité principale, fonction métier.
     *
     * @param list<int> $userIds
     * @return list<array<string, mixed>>
     */
    public function listEffectifsRosterByIds(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $x): bool => $x > 0)));
        if ($tenantId < 1 || $userIds === []) {
            return [];
        }
        $gc = $this->getGradesConfigForDirectory();
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $jobRole = $this->primaryJobRoleJoinFragments('u');
        $jobRoleJoin = $jobRole['join'];
        $jobRoleSelect = $jobRole['select_as_job_role_display'];
        $hasPa = $this->tableExists('personnel_assignments');
        $hasUu = $this->hasUserUnitsTable();
        $unitParts = [];
        $codeParts = [];
        $idParts = [];
        $extraJoins = '';
        if ($hasPa) {
            $extraJoins .= 'LEFT JOIN personnel_assignments pa ON pa.user_id = u.id AND pa.is_primary = 1
                    AND pa.status = \'active\' AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE())
                LEFT JOIN units un_pa ON un_pa.id = pa.unit_id AND un_pa.tenant_id = u.tenant_id ';
            $unitParts[] = 'un_pa.name';
            $codeParts[] = 'un_pa.code';
            $idParts[] = 'un_pa.id';
        }
        $unitParts[] = 'un_pp.name';
        $codeParts[] = 'un_pp.code';
        $idParts[] = 'un_pp.id';
        if ($hasUu) {
            $extraJoins .= 'LEFT JOIN user_units uu ON uu.user_id = u.id AND uu.is_primary = 1
                    AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
                LEFT JOIN units un_uu ON un_uu.id = uu.unit_id AND un_uu.tenant_id = u.tenant_id ';
            $unitParts[] = 'un_uu.name';
            $codeParts[] = 'un_uu.code';
            $idParts[] = 'un_uu.id';
        }
        $unitSelect = 'COALESCE(' . implode(', ', $unitParts) . ') AS unit_name, COALESCE(' . implode(', ', $codeParts) . ') AS unit_code, COALESCE(' . implode(', ', $idParts) . ') AS unit_id';
        $profileExtras = 'pp.character_name, pp.matricule_internal,
                       pp.enlistment_date, pp.readiness_score, pp.clearance_level, pp.clearance_reviewed_at';
        if ($this->personnelProfilesHasColumn('deployable')) {
            $profileExtras .= ', pp.deployable';
        } else {
            $profileExtras .= ', NULL AS deployable';
        }
        $extrasJoin = '';
        $extrasSelect = ', NULL AS date_of_enlistment, NULL AS service_number';
        if ($this->tableExists('personnel_extras')) {
            $extrasJoin = 'LEFT JOIN personnel_extras pex ON pex.user_id = u.id';
            $extrasSelect = ', pex.date_of_enlistment, pex.service_number';
        }
        $sql = 'SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign, u.status, u.avatar_url, u.created_at,
                       t.name AS tenant_name,
                       t.slug AS tenant_slug,
                       ' . $gc['select'] . ',
                       ' . $unitSelect . ',
                       ' . $profileExtras . ',
                       ' . $jobRoleSelect . $extrasSelect . '
                FROM users u
                LEFT JOIN tenants t ON t.id = u.tenant_id
                LEFT JOIN personnel_profiles pp ON pp.user_id = u.id
                ' . $extrasJoin . '
                ' . $jobRoleJoin . '
                ' . $gc['join'] . '
                LEFT JOIN units un_pp ON un_pp.id = pp.primary_unit_id AND un_pp.tenant_id = u.tenant_id
                ' . $extraJoins . '
                WHERE u.tenant_id = ? AND u.id IN (' . $ph . ')';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $userIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $tenantLabel = trim((string) ($row['tenant_name'] ?? ''));
            if (function_exists('community_display_name')) {
                $row['community_name'] = community_display_name([
                    'name' => $tenantLabel,
                    'slug' => (string) ($row['tenant_slug'] ?? ''),
                    'id' => (int) ($row['tenant_id'] ?? 0),
                ]);
            } else {
                $row['community_name'] = $tenantLabel;
            }
            if (trim((string) ($row['community_name'] ?? '')) === '') {
                $row['community_name'] = 'Communauté';
            }
            $row['grade_sort_order'] = (int) ($row['grade_sort_order'] ?? 999);
            $enlist = trim((string) ($row['enlistment_date'] ?? ''));
            if ($enlist === '') {
                $enlist = trim((string) ($row['date_of_enlistment'] ?? ''));
            }
            $row['enlistment_date_resolved'] = $enlist !== '' ? $enlist : null;
        }
        unset($row);

        return $this->dedupeRowsByUserId($rows);
    }

    private function personnelProfilesHasColumn(string $column): bool
    {
        static $cache = [];
        $column = trim($column);
        if ($column === '') {
            return false;
        }
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $stmt = $this->pdo()->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_profiles' AND COLUMN_NAME = ? LIMIT 1"
        );
        $stmt->execute([$column]);
        $cache[$column] = (bool) $stmt->fetchColumn();

        return $cache[$column];
    }

    private function unitsHasColumn(string $column): bool
    {
        static $cache = [];
        $column = trim($column);
        if ($column === '') {
            return false;
        }
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }
        $stmt = $this->pdo()->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'units' AND COLUMN_NAME = ? LIMIT 1"
        );
        $stmt->execute([$column]);
        $cache[$column] = (bool) $stmt->fetchColumn();

        return $cache[$column];
    }

    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->pdo()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    public function allForTenant(int $tenantId): array
    {
        $pack = $this->technicalAccountExclusionPredicate('u');
        $stmt = $this->pdo()->prepare(
            'SELECT u.*, r.name as role_name, up.first_name, up.last_name
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.tenant_id = ? AND ' . $pack['sql'] . ' ORDER BY u.email ASC'
        );
        $stmt->execute(array_merge([$tenantId], $pack['params']));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Identifiants des comptes actifs de la communauté (hors comptes de service si la colonne existe).
     *
     * @return list<int>
     */
    public function listActiveUserIdsForTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $parts = ['u.tenant_id = ?', "u.status = 'active'"];
        $params = [$tenantId];
        $pack = $this->technicalAccountExclusionPredicate('u');
        $parts[] = $pack['sql'];
        $params = array_merge($params, $pack['params']);
        $sql = 'SELECT u.id FROM users u WHERE ' . implode(' AND ', $parts) . ' ORDER BY u.id ASC';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /** Liste avec filtres optionnels (recherche, statut, rôle). */
    public function listForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null, ?int $limit = null, ?int $offset = null, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): array
    {
        [$sql, $params] = $this->buildUserListQuery($tenantId, $search, $status, $roleId, $excludeServiceAccounts, $onlyWithoutUnit, $onlyWithoutRole);
        $sql .= ' ORDER BY u.email ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, min(200, (int) $limit)) . ' OFFSET ' . max(0, (int) ($offset ?? 0));
        }
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countListForTenant(int $tenantId, ?string $search = null, ?string $status = null, ?int $roleId = null, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): int
    {
        [$whereSql, $params] = $this->buildUserListWhere($tenantId, $search, $status, $roleId, $excludeServiceAccounts, $onlyWithoutUnit, $onlyWithoutRole);
        $sql = 'SELECT COUNT(*) FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE ' . $whereSql;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildUserListWhere(int $tenantId, ?string $search, ?string $status, ?int $roleId, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): array
    {
        $parts = ['u.tenant_id = ?'];
        $params = [$tenantId];
        if ($search !== null && $search !== '') {
            $term = '%' . trim($search) . '%';
            $parts[] = '(u.email LIKE ? OR u.display_name LIKE ? OR u.callsign LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($status !== null && $status !== '') {
            $parts[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($roleId !== null && $roleId > 0) {
            if ($this->hasUserRolesTable()) {
                $parts[] = '(u.role_id = ? OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = ?))';
                $params[] = $roleId;
                $params[] = $roleId;
            } else {
                $parts[] = 'u.role_id = ?';
                $params[] = $roleId;
            }
        }
        if ($excludeServiceAccounts) {
            $pack = $this->technicalAccountExclusionPredicate('u');
            $parts[] = $pack['sql'];
            $params = array_merge($params, $pack['params']);
        }
        if ($onlyWithoutRole === true) {
            if ($this->hasUserRolesTable()) {
                $parts[] = '(u.role_id IS NULL AND NOT EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id))';
            } else {
                $parts[] = 'u.role_id IS NULL';
            }
        }
        if ($onlyWithoutUnit === true) {
            if ($this->hasUserUnitsTable()) {
                $parts[] = 'NOT EXISTS (
                    SELECT 1 FROM user_units uu
                    INNER JOIN units un ON un.id = uu.unit_id AND un.tenant_id = u.tenant_id
                    WHERE uu.user_id = u.id AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
                )';
            } else {
                $parts[] = '1=0';
            }
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildUserListQuery(int $tenantId, ?string $search, ?string $status, ?int $roleId, bool $excludeServiceAccounts = true, ?bool $onlyWithoutUnit = null, ?bool $onlyWithoutRole = null): array
    {
        [$whereSql, $params] = $this->buildUserListWhere($tenantId, $search, $status, $roleId, $excludeServiceAccounts, $onlyWithoutUnit, $onlyWithoutRole);
        $extra = '';
        if ($this->hasUserRolesTable()) {
            $extra = ', COALESCE(
                (SELECT GROUP_CONCAT(DISTINCT r2.name ORDER BY r2.role_layer DESC, r2.name SEPARATOR \', \')
                 FROM user_roles ur
                 INNER JOIN roles r2 ON r2.id = ur.role_id AND r2.tenant_id = u.tenant_id
                 WHERE ur.user_id = u.id),
                r.name
            ) AS roles_display';
        }
        $sql = 'SELECT u.*, r.name as role_name' . $extra . ' FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE ' . $whereSql;

        return [$sql, $params];
    }

    public function update(int $userId, int $tenantId, array $data): bool
    {
        $allowed = ['email', 'password_hash', 'display_name', 'callsign', 'avatar_url', 'steam_id', 'role_id', 'grade_id', 'status', 'nationality_code', 'preferred_grade_format', 'professional_category_code'];
        if ($this->hasProfileSlugColumn()) {
            $allowed[] = 'profile_slug';
        }
        if ($this->hasEmailLoginOtpEnabledColumn()) {
            $allowed[] = 'email_login_otp_enabled';
        }
        if ($this->hasTotpColumns()) {
            $allowed[] = 'totp_enabled';
            $allowed[] = 'totp_secret';
            $allowed[] = 'totp_confirmed_at';
        }
        if ($this->hasProfileBannerUrlColumn()) {
            $allowed[] = 'profile_banner_url';
        }
        $set = [];
        $params = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if ($key === 'grade_id') {
                    $value = $this->normalizeOptionalGradeId($value);
                }
                if ($key === 'steam_id' && $value !== null && $value !== '') {
                    $normalized = \App\Support\SteamId::normalize((string) $value);
                    if ($normalized === null) {
                        continue;
                    }
                    $value = $normalized;
                }
                $set[] = "`$key` = ?";
                $params[] = $value;
            }
        }
        if (empty($set)) {
            return true;
        }
        $params[] = $userId;
        $params[] = $tenantId;
        $sql = 'UPDATE users SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ? AND tenant_id = ?';
        $stmt = $this->pdo()->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Suppression douce (anonymisation) d'un compte depuis l'annuaire plateforme.
     * Ne supprime jamais la ligne : ~99 clés étrangères (posts forum, dossiers personnel,
     * formations, documents…) référencent users.id, la plupart en ON DELETE CASCADE —
     * une vraie suppression SQL effacerait tout cet historique en cascade.
     * Le compte reste identifié par son id mais devient inutilisable (email/mdp invalidés,
     * status inactive) et ses données personnelles sont scrubées — l'e-mail d'origine
     * est libéré pour une éventuelle réinscription.
     *
     * Si la même adresse existe sur d'autres communautés (clone / multi-appartenance),
     * elles sont anonymisées aussi : l'inscription plateforme est globale par e-mail.
     */
    public function softDeleteAccount(int $userId, int $tenantId, int $actorUserId): bool
    {
        $target = $this->findById($userId, $tenantId);
        if ($target === null) {
            return false;
        }

        $originalEmail = strtolower(trim((string) ($target['email'] ?? '')));
        $siblingIds = $originalEmail !== '' && !str_ends_with($originalEmail, '@deleted.invalid')
            ? $this->listIdsByEmailNormalized($originalEmail)
            : [$userId];
        if ($siblingIds === []) {
            $siblingIds = [$userId];
        }
        if (!in_array($userId, $siblingIds, true)) {
            $siblingIds[] = $userId;
        }

        $ok = true;
        foreach ($siblingIds as $sid) {
            if (!$this->anonymizeUserIdentity((int) $sid, $actorUserId)) {
                $ok = false;
            }
        }

        return $ok;
    }

    /**
     * Anonymise une ligne users (e-mail libéré, accès coupé, libellé « Compte supprimé »).
     * Ne filtre pas sur tenant_id : appelé pour chaque id partagé par l’e-mail.
     * Ne scrub pas les tables liées — voir AccountDeletionService.
     */
    public function anonymizeUserIdentity(int $userId, int $actorUserId): bool
    {
        if ($userId < 1) {
            return false;
        }

        $anonymizedEmail = 'deleted-' . $userId . '-' . time() . '@deleted.invalid';
        $invalidatedHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $set = [
            '`status` = ?',
            '`email` = ?',
            '`display_name` = ?',
            '`callsign` = NULL',
            '`avatar_url` = NULL',
            '`steam_id` = NULL',
            '`password_hash` = ?',
            '`updated_at` = NOW()',
        ];
        $params = ['inactive', $anonymizedEmail, 'Compte supprimé', $invalidatedHash];

        if ($this->hasDeletionRequestColumns()) {
            $set[] = '`deletion_requested_at` = NULL';
            $set[] = '`deletion_scheduled_at` = NULL';
        }
        if ($this->hasDeletedAtColumn()) {
            $set[] = '`deleted_at` = COALESCE(`deleted_at`, NOW())';
            $set[] = '`deleted_by` = COALESCE(`deleted_by`, ?)';
            $params[] = $actorUserId;
        }
        if ($this->hasProfileSlugColumn()) {
            $set[] = '`profile_slug` = NULL';
        }
        if ($this->hasAthenaIdentifierColumn()) {
            $set[] = '`athena_identifier` = NULL';
        }
        if ($this->hasProfileBannerUrlColumn()) {
            $set[] = '`profile_banner_url` = NULL';
        }
        if ($this->hasTotpColumns()) {
            $set[] = '`totp_enabled` = 0';
            $set[] = '`totp_secret` = NULL';
            $set[] = '`totp_confirmed_at` = NULL';
        }
        if ($this->hasEmailLoginOtpEnabledColumn()) {
            $set[] = '`email_login_otp_enabled` = 0';
        }

        $params[] = $userId;
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?'
        );
        if (!$stmt->execute($params)) {
            return false;
        }

        try {
            $this->invalidateAllSessionsForUser($userId);
        } catch (\Throwable) {
        }

        return $stmt->rowCount() > 0 || $this->findById($userId) !== null;
    }

    /** Vérifie si un autre utilisateur (hors userId) a déjà cet email dans le tenant. */
    public function emailExistsInTenant(int $tenantId, string $email, ?int $excludeUserId = null): bool
    {
        $email = strtolower(trim($email));
        $freed = $this->sqlEmailStillClaimedPredicate('users');
        $sql = 'SELECT 1 FROM users WHERE tenant_id = ? AND LOWER(TRIM(email)) = ? AND ' . $freed['sql'];
        $params = array_merge([$tenantId, $email], $freed['params']);
        if ($excludeUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /** Indique si cet e-mail existe déjà sur n’importe quelle communauté (hors comptes techniques / anonymisés). */
    public function emailExistsGlobally(string $email, ?int $excludeUserId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || strcasecmp($email, self::SYSTEM_MODERATOR_EMAIL) === 0) {
            return false;
        }
        $freed = $this->sqlEmailStillClaimedPredicate('users');
        $sql = 'SELECT 1 FROM users WHERE LOWER(TRIM(email)) = ? AND ' . $freed['sql'];
        $params = array_merge([$email], $freed['params']);
        if ($this->hasServiceAccountColumn()) {
            $sql .= ' AND (is_service_account IS NULL OR is_service_account = 0)';
        }
        if ($excludeUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /** Indique si un autre utilisateur du tenant utilise déjà ce callsign (insensible à la casse). */
    public function callsignExistsInTenant(int $tenantId, string $callsign, ?int $excludeUserId = null): bool
    {
        $callsign = trim($callsign);
        if ($callsign === '') {
            return false;
        }
        $sql = 'SELECT 1 FROM users WHERE tenant_id = ? AND callsign IS NOT NULL AND TRIM(callsign) <> \'\' AND LOWER(TRIM(callsign)) = LOWER(?)';
        $params = [$tenantId, $callsign];
        if ($excludeUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /** Recherche utilisateurs pour @mention (display_name, callsign). */
    public function searchForMention(int $tenantId, string $query, int $limit = 10): array
    {
        $term = '%' . trim($query) . '%';
        $extra = " AND status = 'active' AND LOWER(TRIM(email)) NOT LIKE '%@deleted.invalid'";
        if ($this->hasDeletedAtColumn()) {
            $extra .= ' AND deleted_at IS NULL';
        }
        $stmt = $this->pdo()->prepare(
            'SELECT id, display_name, callsign FROM users WHERE tenant_id = ? AND (display_name LIKE ? OR callsign LIKE ?)'
            . $extra . ' ORDER BY display_name ASC LIMIT ?'
        );
        $stmt->execute([$tenantId, $term, $term, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Résout un @identifiant unique (nom affiché ou indicatif exact, insensible à la casse).
     *
     * @return array{id: int, display_name: string, callsign: ?string}|null
     */
    public function findForForumMention(int $tenantId, string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $extra = " AND status = 'active' AND LOWER(TRIM(email)) NOT LIKE '%@deleted.invalid'";
        if ($this->hasDeletedAtColumn()) {
            $extra .= ' AND deleted_at IS NULL';
        }
        $stmt = $this->pdo()->prepare(
            'SELECT id, display_name, callsign FROM users WHERE tenant_id = ? AND (
                LOWER(display_name) = LOWER(?)
                OR (callsign IS NOT NULL AND TRIM(callsign) <> \'\' AND LOWER(TRIM(callsign)) = LOWER(?))
            )' . $extra . ' LIMIT 1'
        );
        $stmt->execute([$tenantId, $token, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Recherche annuaire portail (nom d’affichage, indicatif, slug de profil public).
     *
     * @return list<array<string, mixed>>
     */
    public function searchForPortal(int $tenantId, string $query, int $limit = 12): array
    {
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $hasAthenaIdentifier = $this->hasAthenaIdentifierColumn();
        $term = '%' . $q . '%';
        $pack = $this->technicalAccountExclusionPredicate('u');
        $athenaSelect = $hasAthenaIdentifier ? 'u.athena_identifier' : "'' AS athena_identifier";
        $athenaFilter = $hasAthenaIdentifier
            ? "OR (u.athena_identifier IS NOT NULL AND TRIM(u.athena_identifier) <> '' AND u.athena_identifier LIKE ?)"
            : '';
        $stmt = $this->pdo()->prepare(
            'SELECT u.id, u.display_name, u.callsign, u.profile_slug, ' . $athenaSelect . ', u.avatar_url FROM users u
             WHERE u.tenant_id = ?
             AND ' . $pack['sql'] . '
             AND (
                 u.display_name LIKE ?
                 OR (u.callsign IS NOT NULL AND TRIM(u.callsign) <> \'\' AND u.callsign LIKE ?)
                 OR (u.profile_slug IS NOT NULL AND TRIM(u.profile_slug) <> \'\' AND u.profile_slug LIKE ?)
                 ' . $athenaFilter . '
             )
             ORDER BY u.display_name ASC
             LIMIT ?'
        );
        $params = array_merge([$tenantId], $pack['params'], [$term, $term, $term]);
        if ($hasAthenaIdentifier) {
            $params[] = $term;
        }
        $params[] = $limit;
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recherche transverse (toutes communautés) pour assistance opérateur site — liste de restriction, etc.
     * Minimum 2 caractères ; comptes techniques exclus lorsque la colonne existe.
     *
     * @return list<array<string, mixed>>
     */
    public function searchAccountsForPlatformOperator(string $query, int $limit = 20): array
    {
        $q = trim($query);
        $len = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
        if ($len < 2) {
            return [];
        }
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 120) : substr($q, 0, 120);
        $term = '%' . $q . '%';
        $limit = max(1, min(50, $limit));
        $pack = $this->technicalAccountExclusionPredicate('u');
        $hasDeletedAt = $this->hasDeletedAtColumn();
        $deletedSelf = $hasDeletedAt ? 'AND u.deleted_at IS NULL' : '';
        $siblingAlive = $hasDeletedAt ? 'AND u2.deleted_at IS NULL' : '';
        $hasAthena = $this->hasAthenaIdentifierColumn();
        $athenaSelect = $hasAthena ? 'u.athena_identifier' : "'' AS athena_identifier";
        $athenaFilter = $hasAthena
            ? "OR (u.athena_identifier IS NOT NULL AND TRIM(u.athena_identifier) <> '' AND u.athena_identifier LIKE ?)"
            : '';
        $hasNickname = $this->columnExists('users', 'nickname');
        $nicknameFilter = $hasNickname
            ? "OR (u.nickname IS NOT NULL AND TRIM(u.nickname) <> '' AND u.nickname LIKE ?)"
            : '';
        $legal = $this->legalIdentityJoinFragments('uli', 'u');
        $legalFilter = $legal['searchable']
            ? "OR (uli.first_name IS NOT NULL AND TRIM(uli.first_name) <> '' AND uli.first_name LIKE ?)
                 OR (uli.last_name IS NOT NULL AND TRIM(uli.last_name) <> '' AND uli.last_name LIKE ?)
                 OR (CONCAT(TRIM(COALESCE(uli.first_name, '')), ' ', TRIM(COALESCE(uli.last_name, ''))) LIKE ?)"
            : '';
        $hasCharacter = $this->tableExists('personnel_profiles') && $this->columnExists('personnel_profiles', 'character_name');
        $characterJoin = $hasCharacter ? 'LEFT JOIN personnel_profiles pp ON pp.user_id = u.id' : '';
        $characterFilter = $hasCharacter
            ? "OR (pp.character_name IS NOT NULL AND TRIM(pp.character_name) <> '' AND pp.character_name LIKE ?)"
            : '';
        $sql = "SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign,
                    {$legal['select']},
                    u.steam_id, {$athenaSelect}, t.name AS tenant_name, t.slug AS tenant_slug, u.status
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             {$legal['join']}
             {$characterJoin}
             WHERE (
                 u.email LIKE ?
                 OR u.display_name LIKE ?
                 OR (u.callsign IS NOT NULL AND TRIM(u.callsign) <> '' AND u.callsign LIKE ?)
                 OR (u.steam_id IS NOT NULL AND TRIM(u.steam_id) <> '' AND u.steam_id LIKE ?)
                 {$legalFilter}
                 {$nicknameFilter}
                 {$athenaFilter}
                 {$characterFilter}
             ) AND {$pack['sql']}
             {$deletedSelf}
             AND (t.slug <> 'default' OR NOT EXISTS (
                SELECT 1 FROM users u2
                INNER JOIN tenants t2 ON t2.id = u2.tenant_id AND t2.slug <> 'default'
                WHERE LOWER(TRIM(u2.email)) = LOWER(TRIM(u.email))
                  AND TRIM(u.email) <> ''
                  AND LOWER(TRIM(u.email)) NOT LIKE '%@deleted.invalid'
                  AND u2.id <> u.id
                  {$siblingAlive}
             ))
             ORDER BY t.name ASC, u.display_name ASC, u.email ASC
             LIMIT {$limit}";
        $stmt = $this->pdo()->prepare($sql);
        $params = [$term, $term, $term, $term];
        if ($legal['searchable']) {
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($hasNickname) {
            $params[] = $term;
        }
        if ($hasAthena) {
            $params[] = $term;
        }
        if ($hasCharacter) {
            $params[] = $term;
        }
        $stmt->execute(array_merge($params, $pack['params']));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            if (function_exists('community_display_name')) {
                $row['tenant_name'] = community_display_name([
                    'name' => (string) ($row['tenant_name'] ?? ''),
                    'slug' => (string) ($row['tenant_slug'] ?? ''),
                ]);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Membres d’une communauté pour l’écran « restrictions mod » (Steam lié).
     *
     * @return list<array<string, mixed>>
     */
    public function searchMembersForModBlocklist(int $tenantId, string $query, int $limit = 20): array
    {
        $q = trim($query);
        $len = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
        if ($tenantId < 1 || $len < 2) {
            return [];
        }
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 120) : substr($q, 0, 120);
        $term = '%' . $q . '%';
        $limit = max(1, min(30, $limit));
        $pack = $this->technicalAccountExclusionPredicate('u');
        $sql = 'SELECT u.id, u.display_name, u.callsign, u.email, u.steam_id, u.status
             FROM users u
             WHERE u.tenant_id = ?
             AND ' . $pack['sql'] . '
             AND (
                 u.display_name LIKE ?
                 OR u.email LIKE ?
                 OR (u.callsign IS NOT NULL AND TRIM(u.callsign) <> \'\' AND u.callsign LIKE ?)
                 OR (u.steam_id IS NOT NULL AND TRIM(u.steam_id) <> \'\' AND u.steam_id LIKE ?)
             )
             ORDER BY u.display_name ASC
             LIMIT ' . $limit;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $pack['params'], [$term, $term, $term, $term]));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Annuaire plateforme : comptes toutes communautés.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listAccountsForPlatformDirectory(
        ?string $search = null,
        ?string $status = null,
        ?int $tenantId = null,
        int $page = 1,
        int $perPage = 50
    ): array {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $pack = $this->technicalAccountExclusionPredicate('u');
        $parts = [$pack['sql']];
        $params = $pack['params'];
        $hasDeletedAt = $this->hasDeletedAtColumn();

        $search = $search !== null ? trim($search) : '';
        if ($search !== '') {
            $term = '%' . (function_exists('mb_substr') ? mb_substr($search, 0, 120) : substr($search, 0, 120)) . '%';
            $parts[] = '(u.email LIKE ? OR u.display_name LIKE ? OR (u.callsign IS NOT NULL AND TRIM(u.callsign) <> \'\' AND u.callsign LIKE ?))';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($status === 'deleted' && $hasDeletedAt) {
            $parts[] = 'u.deleted_at IS NOT NULL';
        } else {
            if ($hasDeletedAt) {
                $parts[] = 'u.deleted_at IS NULL';
            }
            if ($status !== null && $status !== '' && in_array($status, ['active', 'inactive', 'pending_verification'], true)) {
                $parts[] = 'u.status = ?';
                $params[] = $status;
            }
        }
        if ($tenantId !== null && $tenantId > 0) {
            $parts[] = 'u.tenant_id = ?';
            $params[] = $tenantId;
        } else {
            // Masquer le compte « tenant système » dès qu’une vraie communauté existe pour le même e-mail
            // (évite les doublons Oliver / Aucune organisation + régiment dans l’annuaire plateforme).
            $siblingAlive = $hasDeletedAt ? 'AND u2.deleted_at IS NULL' : '';
            $parts[] = "(t.slug <> 'default' OR NOT EXISTS (
                SELECT 1 FROM users u2
                INNER JOIN tenants t2 ON t2.id = u2.tenant_id AND t2.slug <> 'default'
                WHERE LOWER(TRIM(u2.email)) = LOWER(TRIM(u.email))
                  AND TRIM(u.email) <> ''
                  AND LOWER(TRIM(u.email)) NOT LIKE '%@deleted.invalid'
                  AND u2.id <> u.id
                  {$siblingAlive}
            ))";
        }

        $where = implode(' AND ', $parts);
        $countStmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM users u INNER JOIN tenants t ON t.id = u.tenant_id WHERE {$where}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign, u.status, u.created_at, u.updated_at,
                       " . ($hasDeletedAt ? 'u.deleted_at' : 'NULL AS deleted_at') . ",
                       t.name AS tenant_name, t.slug AS tenant_slug,
                       r.name AS role_name
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE {$where}
                ORDER BY u.updated_at DESC, u.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            if (function_exists('community_display_name')) {
                $row['tenant_name'] = community_display_name([
                    'name' => (string) ($row['tenant_name'] ?? ''),
                    'slug' => (string) ($row['tenant_slug'] ?? ''),
                ]);
            }
        }
        unset($row);

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    /**
     * Annuaire plateforme regroupé par personne (e-mail) : une entrée = toutes les
     * appartenances communautaires (une ligne users par tenant).
     *
     * @return array{groups: list<array<string, mixed>>, total: int}
     */
    public function listGroupedAccountsForPlatformDirectory(
        ?string $search = null,
        ?string $status = null,
        ?int $tenantId = null,
        int $page = 1,
        int $perPage = 50
    ): array {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $pack = $this->technicalAccountExclusionPredicate('u');
        $parts = [$pack['sql']];
        $params = $pack['params'];
        $hasDeletedAt = $this->hasDeletedAtColumn();

        $search = $search !== null ? trim($search) : '';
        if ($search !== '') {
            $term = '%' . (function_exists('mb_substr') ? mb_substr($search, 0, 120) : substr($search, 0, 120)) . '%';
            $parts[] = '(u.email LIKE ? OR u.display_name LIKE ? OR (u.callsign IS NOT NULL AND TRIM(u.callsign) <> \'\' AND u.callsign LIKE ?))';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if ($status === 'deleted' && $hasDeletedAt) {
            $parts[] = 'u.deleted_at IS NOT NULL';
        } else {
            if ($hasDeletedAt) {
                $parts[] = 'u.deleted_at IS NULL';
            }
            if ($status !== null && $status !== '' && in_array($status, ['active', 'inactive', 'pending_verification'], true)) {
                $parts[] = 'u.status = ?';
                $params[] = $status;
            }
        }
        if ($tenantId !== null && $tenantId > 0) {
            $parts[] = 'u.tenant_id = ?';
            $params[] = $tenantId;
        } else {
            $siblingAlive = $hasDeletedAt ? 'AND u2.deleted_at IS NULL' : '';
            $parts[] = "(t.slug <> 'default' OR NOT EXISTS (
                SELECT 1 FROM users u2
                INNER JOIN tenants t2 ON t2.id = u2.tenant_id AND t2.slug <> 'default'
                WHERE LOWER(TRIM(u2.email)) = LOWER(TRIM(u.email))
                  AND TRIM(u.email) <> ''
                  AND LOWER(TRIM(u.email)) NOT LIKE '%@deleted.invalid'
                  AND u2.id <> u.id
                  {$siblingAlive}
            ))";
        }

        $where = implode(' AND ', $parts);

        $countStmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM (
                SELECT 1
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                WHERE {$where}
                GROUP BY LOWER(TRIM(u.email))
            ) grouped_accounts"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $emailStmt = $this->pdo()->prepare(
            "SELECT LOWER(TRIM(u.email)) AS email_key, MAX(u.updated_at) AS sort_at
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE {$where}
             GROUP BY LOWER(TRIM(u.email))
             ORDER BY sort_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $emailStmt->execute($params);
        $emailKeys = [];
        foreach ($emailStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $er) {
            $key = strtolower(trim((string) ($er['email_key'] ?? '')));
            if ($key !== '') {
                $emailKeys[] = $key;
            }
        }

        if ($emailKeys === []) {
            return ['groups' => [], 'total' => $total];
        }

        $ph = implode(',', array_fill(0, count($emailKeys), '?'));
        $memberWhere = $parts;
        // Remonter toutes les appartenances des e-mails de la page (même si filtre statut
        // ne matchait qu’une fiche) pour afficher le panel multi-communautés complet.
        $memberParts = [$pack['sql']];
        $memberParams = $pack['params'];
        $memberParts[] = 'LOWER(TRIM(u.email)) IN (' . $ph . ')';
        $memberParams = array_merge($memberParams, $emailKeys);
        if ($tenantId !== null && $tenantId > 0) {
            // Si on filtre une communauté, on garde quand même les sœurs pour le regroupement,
            // mais on n’affiche que les groupes qui ont au moins un match (déjà garanti par emailKeys).
        } else {
            $siblingAlive = $hasDeletedAt ? 'AND u2.deleted_at IS NULL' : '';
            $memberParts[] = "(t.slug <> 'default' OR NOT EXISTS (
                SELECT 1 FROM users u2
                INNER JOIN tenants t2 ON t2.id = u2.tenant_id AND t2.slug <> 'default'
                WHERE LOWER(TRIM(u2.email)) = LOWER(TRIM(u.email))
                  AND TRIM(u.email) <> ''
                  AND LOWER(TRIM(u.email)) NOT LIKE '%@deleted.invalid'
                  AND u2.id <> u.id
                  {$siblingAlive}
            ))";
        }
        $memberWhereSql = implode(' AND ', $memberParts);

        $sql = "SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign, u.status, u.created_at, u.updated_at,
                       " . ($hasDeletedAt ? 'u.deleted_at' : 'NULL AS deleted_at') . ",
                       t.name AS tenant_name, t.slug AS tenant_slug,
                       r.name AS role_name
                FROM users u
                INNER JOIN tenants t ON t.id = u.tenant_id
                LEFT JOIN roles r ON r.id = u.role_id
                WHERE {$memberWhereSql}
                ORDER BY u.updated_at DESC, u.id DESC";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($memberParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byEmail = [];
        foreach ($rows as $row) {
            if (function_exists('community_display_name')) {
                $row['tenant_name'] = community_display_name([
                    'name' => (string) ($row['tenant_name'] ?? ''),
                    'slug' => (string) ($row['tenant_slug'] ?? ''),
                ]);
            }
            $key = strtolower(trim((string) ($row['email'] ?? '')));
            if ($key === '') {
                continue;
            }
            if (!isset($byEmail[$key])) {
                $byEmail[$key] = [
                    'email' => (string) ($row['email'] ?? ''),
                    'email_key' => $key,
                    'display_name' => (string) ($row['display_name'] ?? ''),
                    'callsign' => (string) ($row['callsign'] ?? ''),
                    'memberships' => [],
                ];
            }
            // Préférer un indicatif / nom non anonymisé pour l’en-tête personne.
            $dn = trim((string) ($row['display_name'] ?? ''));
            $cs = trim((string) ($row['callsign'] ?? ''));
            if ($cs !== '' && trim((string) ($byEmail[$key]['callsign'] ?? '')) === '') {
                $byEmail[$key]['callsign'] = $cs;
            }
            if ($dn !== '' && $dn !== 'Compte supprimé' && (
                trim((string) ($byEmail[$key]['display_name'] ?? '')) === ''
                || (string) ($byEmail[$key]['display_name'] ?? '') === 'Compte supprimé'
            )) {
                $byEmail[$key]['display_name'] = $dn;
            }
            $byEmail[$key]['memberships'][] = $row;
        }

        $groups = [];
        foreach ($emailKeys as $key) {
            if (isset($byEmail[$key])) {
                $groups[] = $byEmail[$key];
            }
        }

        return [
            'groups' => $groups,
            'total' => $total,
        ];
    }

    /** @return list<int> User IDs ayant le rôle donné (pour assignation formation par rôle). */
    public function getIdsByRole(int $tenantId, int $roleId): array
    {
        if ($this->hasUserRolesTable()) {
            $stmt = $this->pdo()->prepare(
                'SELECT DISTINCT u.id FROM users u
                 WHERE u.tenant_id = ?
                 AND (u.role_id = ? OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = ?))'
            );
            $stmt->execute([$tenantId, $roleId, $roleId]);
        } else {
            $stmt = $this->pdo()->prepare('SELECT id FROM users WHERE tenant_id = ? AND role_id = ?');
            $stmt->execute([$tenantId, $roleId]);
        }

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> User IDs appartenant à l'unité (user_units, affectation non terminée). */
    public function getIdsByUnit(int $unitId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT user_id FROM user_units WHERE unit_id = ? AND (ended_at IS NULL OR ended_at > NOW())'
        );
        $stmt->execute([$unitId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<int> Unit IDs auxquelles l'utilisateur est affecté (user_units, non terminée). */
    public function getUnitIdsForUser(int $userId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT unit_id FROM user_units WHERE user_id = ? AND (ended_at IS NULL OR ended_at > NOW())'
        );
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return string|null Role slug de l'utilisateur (via users.role_id -> roles.slug). */
    public function getRoleSlugForUser(int $userId): ?string
    {
        $stmt = $this->pdo()->prepare('SELECT r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_COLUMN);
        return $row !== false ? (string) $row : null;
    }

    /** Nombre d'utilisateurs ayant le rôle donné (pour garde-fou dernier super-admin). */
    public function countUsersWithRole(int $roleId): int
    {
        if ($this->hasUserRolesTable()) {
            $stmt = $this->pdo()->prepare(
                'SELECT COUNT(DISTINCT u.id) FROM users u
                 WHERE (u.role_id = ? OR EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = ?))'
            );
            $stmt->execute([$roleId, $roleId]);
        } else {
            $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
            $stmt->execute([$roleId]);
        }

        return (int) $stmt->fetchColumn();
    }

    /** Utilisateurs actifs pour quotas d'abonnement (plan premium). */
    public function countActiveForTenant(int $tenantId): int
    {
        $stmt = $this->pdo()->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Toutes les fiches users partageant un e-mail (toutes communautés, y compris inactives / soft-deleted).
     *
     * @return list<array<string, mixed>>
     */
    public function listAllMembershipsByEmail(string $email): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || str_ends_with($email, '@deleted.invalid')) {
            return [];
        }
        $hasDeletedAt = $this->hasDeletedAtColumn();
        $hasAthena = $this->hasAthenaIdentifierColumn();
        $deletedSelect = $hasDeletedAt ? 'u.deleted_at' : 'NULL AS deleted_at';
        $athenaSelect = $hasAthena ? 'u.athena_identifier' : "'' AS athena_identifier";
        $stmt = $this->pdo()->prepare(
            "SELECT u.id, u.tenant_id, u.email, u.display_name, u.callsign, u.status, u.steam_id,
                    u.avatar_url, u.grade_id, u.role_id, u.created_at, u.updated_at, u.profile_slug,
                    {$deletedSelect}, {$athenaSelect},
                    t.name AS tenant_name, t.slug AS tenant_slug,
                    r.name AS role_name
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE LOWER(TRIM(u.email)) = ?
             ORDER BY
                CASE WHEN t.slug = 'default' THEN 1 ELSE 0 END ASC,
                CASE WHEN u.status = 'active' THEN 0 WHEN u.status = 'pending_verification' THEN 1 ELSE 2 END ASC,
                t.name ASC,
                u.id ASC"
        );
        $stmt->execute([$email]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            if (function_exists('community_display_name')) {
                $row['tenant_name'] = community_display_name([
                    'name' => (string) ($row['tenant_name'] ?? ''),
                    'slug' => (string) ($row['tenant_slug'] ?? ''),
                ]);
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Indique si un e-mail a encore une appartenance active à une vraie communauté.
     */
    public function emailHasActiveNonDefaultMembership(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || str_ends_with($email, '@deleted.invalid')) {
            return false;
        }
        $hasDeletedAt = $this->hasDeletedAtColumn();
        $liveDeleted = $hasDeletedAt ? 'AND u.deleted_at IS NULL' : '';
        $stmt = $this->pdo()->prepare(
            "SELECT 1 FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id AND t.slug <> 'default'
             WHERE LOWER(TRIM(u.email)) = ?
               AND u.status = 'active'
               {$liveDeleted}
             LIMIT 1"
        );
        $stmt->execute([$email]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return list<array{id: int, tenant_id: int, name: string, slug: string}>
     */
    public function listTenantsForEmail(string $email): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT u.id, u.tenant_id, t.name, t.slug FROM users u INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE LOWER(TRIM(u.email)) = ? AND u.status = ? ORDER BY t.name ASC'
        );
        $stmt->execute([strtolower(trim($email)), 'active']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retire le tenant système (slug `default`) du sélecteur dès qu'une autre communauté existe.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function filterSwitchableTenantsForUser(array $rows): array
    {
        $hasNonDefault = false;
        foreach ($rows as $r) {
            if (($r['slug'] ?? '') !== 'default') {
                $hasNonDefault = true;
                break;
            }
        }
        if (! $hasNonDefault) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn ($r) => ($r['slug'] ?? '') !== 'default'));
    }

    /** Premier tenant_id dont le slug n'est pas `default`, ou null. */
    public function firstNonDefaultTenantId(array $rows): ?int
    {
        foreach ($rows as $r) {
            if (($r['slug'] ?? '') !== 'default') {
                return (int) $r['tenant_id'];
            }
        }

        return null;
    }

    /**
     * Compte Athena déjà membre d’une communauté réelle autre que `$excludeTenantId`.
     * Le tenant système (slug `default` / « Pas d’organisation ») est ignoré.
     */
    public function hasOtherNonDefaultCommunityMembership(string $email, int $excludeTenantId): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        foreach ($this->listTenantsForEmail($email) as $r) {
            if (($r['slug'] ?? '') === 'default') {
                continue;
            }
            if ((int) ($r['tenant_id'] ?? 0) === $excludeTenantId) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Comptes actifs pour un email avec détail tenant (connexion sans slug).
     *
     * @return list<array<string,mixed>> lignes users.* + tenant_name, tenant_slug
     */
    public function listActiveUsersWithTenantForEmail(string $email): array
    {
        $email = strtolower(trim($email));
        $stmt = $this->pdo()->prepare(
            'SELECT u.*, t.name AS tenant_name, t.slug AS tenant_slug
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE LOWER(TRIM(u.email)) = ? AND u.status = ?
             ORDER BY t.name ASC'
        );
        $stmt->execute([$email, 'active']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Connexion : actifs + en attente de vérification e-mail.
     *
     * @return list<array<string, mixed>>
     */
    public function listUsersForLoginByEmail(string $email): array
    {
        $email = strtolower(trim($email));
        $stmt = $this->pdo()->prepare(
            "SELECT u.*, t.name AS tenant_name, t.slug AS tenant_slug
             FROM users u
             INNER JOIN tenants t ON t.id = u.tenant_id
             WHERE LOWER(TRIM(u.email)) = ? AND u.status IN ('active', 'pending_verification')
             ORDER BY t.name ASC"
        );
        $stmt->execute([$email]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findIdByTenantAndEmail(int $tenantId, string $email): ?int
    {
        $email = strtolower(trim($email));
        $stmt = $this->pdo()->prepare('SELECT id FROM users WHERE tenant_id = ? AND LOWER(TRIM(email)) = ? LIMIT 1');
        $stmt->execute([$tenantId, $email]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** Premier compte trouvé pour cet email (tout tenant), pour rattachement invitation. */
    public function findFirstByEmailGlobal(string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $sql = 'SELECT * FROM users WHERE LOWER(TRIM(email)) = ?';
        if ($this->hasServiceAccountColumn()) {
            $sql .= ' AND (is_service_account IS NULL OR is_service_account = 0)';
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Duplique un compte vers un autre tenant (même hash mot de passe) pour rejoindre une nouvelle communauté.
     *
     * @return int Nouvel id utilisateur
     */
    /**
     * @param array{display_name?: ?string, callsign?: ?string} $identityOverrides
     */
    public function cloneUserToTenant(int $sourceUserId, int $newTenantId, int $roleId, ?int $gradeId = null, array $identityOverrides = []): int
    {
        $u = $this->findById($sourceUserId, null);
        if (!$u) {
            throw new \InvalidArgumentException('Utilisateur source introuvable.');
        }
        if ($this->emailExistsInTenant($newTenantId, (string) $u['email'])) {
            throw new \RuntimeException('Cet email est déjà inscrit dans cette communauté.');
        }
        $displayName = array_key_exists('display_name', $identityOverrides)
            ? $identityOverrides['display_name']
            : ($u['display_name'] ?? null);
        $callsign = array_key_exists('callsign', $identityOverrides)
            ? $identityOverrides['callsign']
            : ($u['callsign'] ?? null);
        $cloneData = [
            'email' => $u['email'],
            'password_hash' => $u['password_hash'],
            'display_name' => $displayName,
            'callsign' => $callsign,
            'role_id' => $roleId,
            'grade_id' => $this->normalizeOptionalGradeId($gradeId),
            'status' => 'active',
        ];
        if ($this->hasProfileSlugColumn()) {
            $cloneData['profile_slug'] = UserProfileSlugService::generateForNewUser(
                $displayName,
                (string) $u['email'],
                fn (string $s) => $this->isProfileSlugTaken($newTenantId, $s)
            );
        }

        $newId = $this->create($newTenantId, $cloneData);
        if ($this->hasEmailVerifiedColumn()) {
            $srcEv = $u['email_verified_at'] ?? null;
            if ($srcEv) {
                $this->pdo()->prepare('UPDATE users SET email_verified_at = ? WHERE id = ?')->execute([$srcEv, $newId]);
            } else {
                $this->pdo()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$newId]);
            }
        }
        if ($roleId > 0) {
            $this->syncOrganizationRoles($newId, $newTenantId, [$roleId], null, true);
        }

        return $newId;
    }

    /**
     * Convertit 0 / chaî / chaîes négatives en NULL (FK grades.id).
     */
    private function normalizeOptionalGradeId(mixed $gradeId): ?int
    {
        if ($gradeId === null || $gradeId === '') {
            return null;
        }
        $id = (int) $gradeId;

        return $id > 0 ? $id : null;
    }

    public function countActiveMembers(int $tenantId): int
    {
        $stmt = $this->pdo()->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'");
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * % de membres actifs ayant eu une activité (last_login) sur les 30 derniers jours.
     */
    public function activityRateLast30DaysPercent(int $tenantId): ?int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'"
        );
        $stmt->execute([$tenantId]);
        $total = (int) $stmt->fetchColumn();
        if ($total === 0) {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM users WHERE tenant_id = ? AND status = 'active'
             AND last_login_at IS NOT NULL AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute([$tenantId]);
        $recent = (int) $stmt->fetchColumn();

        return (int) round(100 * $recent / $total);
    }

    public function countPublicRosterOptIn(int $tenantId): int
    {
        $stmt = $this->pdo()->query(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'public_roster_opt_in' LIMIT 1"
        );
        if (!$stmt || !$stmt->fetchColumn()) {
            return 0;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM users u
             INNER JOIN user_profile_display_settings ups ON ups.user_id = u.id AND ups.public_roster_opt_in = 1
             WHERE u.tenant_id = ? AND u.status = \'active\''
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Jointure / colonnes grades : ancienne table tenant (name, short_name, rank_order) ou référentiel (label_*, sort_order).
     *
     * @return array{join: string, grade_short: string, order_grade: string}
     */
    private function getGradesConfigForPublicRoster(): array
    {
        if (self::$gradesConfigPublicRoster !== null) {
            return self::$gradesConfigPublicRoster;
        }
        $stmt = $this->pdo()->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME IN ('name', 'label_long', 'tenant_id')"
        );
        $columns = $stmt ? array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME') : [];
        $hasLabelLong = in_array('label_long', $columns, true);
        $hasTenantId = in_array('tenant_id', $columns, true);
        if ($hasLabelLong) {
            self::$gradesConfigPublicRoster = [
                'grade_short' => 'g.label_short AS grade_short',
                'order_grade' => 'COALESCE(g.sort_order, 999) ASC, g.label_short ASC',
                'join' => $hasTenantId
                    ? 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id'
                    : 'LEFT JOIN grades g ON g.id = u.grade_id',
            ];
        } else {
            self::$gradesConfigPublicRoster = [
                'grade_short' => 'g.short_name AS grade_short',
                'order_grade' => 'COALESCE(g.rank_order, 999) ASC, g.short_name ASC',
                'join' => 'LEFT JOIN grades g ON g.id = u.grade_id AND g.tenant_id = u.tenant_id',
            ];
        }

        return self::$gradesConfigPublicRoster;
    }

    /**
     * Membres pour le roster public : actifs, opt-in, ordre stable.
     *
     * @return list<array<string,mixed>>
     */
    public function listPublicRosterForTenant(int $tenantId, int $limit = 120): array
    {
        $stmt = $this->pdo()->query(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'user_profile_display_settings' AND COLUMN_NAME = 'public_roster_opt_in' LIMIT 1"
        );
        if (!$stmt || !$stmt->fetchColumn()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $gc = $this->getGradesConfigForPublicRoster();
        $gradeJoin = $gc['join'];
        $gradeShort = $gc['grade_short'];
        $orderGrade = $gc['order_grade'];
        $sql = "SELECT u.id, u.display_name, u.callsign, u.status,
                       {$gradeShort},
                       r.name AS role_name,
                       ups.forum_alias, ups.forum_label_mode,
                       un.name AS unit_name
                FROM users u
                INNER JOIN user_profile_display_settings ups ON ups.user_id = u.id AND COALESCE(ups.public_roster_opt_in, 0) = 1
                {$gradeJoin}
                LEFT JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
                LEFT JOIN user_units uu ON uu.user_id = u.id AND uu.is_primary = 1
                    AND (uu.ended_at IS NULL OR uu.ended_at > NOW())
                LEFT JOIN units un ON un.id = uu.unit_id AND un.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active'
                ORDER BY {$orderGrade}, u.display_name ASC, u.callsign ASC
                LIMIT {$limit}";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static ?bool $hasEmailVerifiedColumn = null;

    public function hasEmailVerifiedColumn(): bool
    {
        if (self::$hasEmailVerifiedColumn === null) {
            $stmt = $this->pdo()->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email_verified_at' LIMIT 1");
            self::$hasEmailVerifiedColumn = $stmt && (bool) $stmt->fetchColumn();
        }

        return self::$hasEmailVerifiedColumn;
    }

    public function markEmailVerified(int $userId, int $tenantId): void
    {
        if (!$this->hasEmailVerifiedColumn()) {
            $this->pdo()->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
                ->execute(['active', $userId, $tenantId]);

            return;
        }
        $this->pdo()->prepare('UPDATE users SET email_verified_at = NOW(), status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute(['active', $userId, $tenantId]);
    }

    /** Comptes créés par l’admin : considérer l’e-mail comme déjà vérifié sans changer le statut choisi. */
    public function markEmailVerifiedWithoutStatusChange(int $userId, int $tenantId): void
    {
        if (!$this->hasEmailVerifiedColumn()) {
            return;
        }
        $this->pdo()->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = ? AND tenant_id = ?')
            ->execute([$userId, $tenantId]);
    }

    /**
     * Emails des gouvernants communauté (alertes nouveau membre, etc.).
     *
     * @return list<string>
     */
    public function listGovernanceEmailsForTenant(int $tenantId): array
    {
        $pack = $this->technicalAccountExclusionPredicate('u');
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
            AND r.slug IN ('tenant_admin', 'community_owner')";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $pack['params']));
        $emails = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $e = strtolower(trim((string) ($row['email'] ?? '')));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $e;
            }
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.email FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
                AND r.slug IN ('tenant_admin', 'community_owner')";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute(array_merge([$tenantId], $pack['params']));
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $e = strtolower(trim((string) ($row['email'] ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Destinataires pour une demande d’accès (gouvernance + profils pouvant gérer les rôles sur la communauté).
     *
     * @return list<string>
     */
    public function listEmailsForTenantAccessDelegation(int $tenantId): array
    {
        $emails = $this->listGovernanceEmailsForTenant($tenantId);
        $pack = $this->technicalAccountExclusionPredicate('u');
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            INNER JOIN role_permissions rp ON rp.role_id = r.id
            INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
            AND p.slug IN ('admin.organization', 'admin.access')";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute(array_merge([$tenantId], $pack['params']));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $e = strtolower(trim((string) ($row['email'] ?? '')));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = $e;
                }
            }
        } catch (\Throwable) {
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.email FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                INNER JOIN role_permissions rp ON rp.role_id = r.id
                INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
                AND p.slug IN ('admin.organization', 'admin.access')";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute(array_merge([$tenantId], $pack['params']));
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $e = strtolower(trim((string) ($row['email'] ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Emails des rôles recrutement : recruteur, fondateur (propriétaire communauté), RH.
     *
     * @return list<string>
     */
    public function listRecruitmentNotificationEmailsForTenant(int $tenantId): array
    {
        $pack = $this->technicalAccountExclusionPredicate('u');
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
            AND r.slug IN ('recruiter', 'community_owner', 'hr')";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $pack['params']));
        $emails = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $e = strtolower(trim((string) ($row['email'] ?? '')));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $e;
            }
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.email FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
                AND r.slug IN ('recruiter', 'community_owner', 'hr')";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute(array_merge([$tenantId], $pack['params']));
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $e = strtolower(trim((string) ($row['email'] ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * E-mails des comptes au rôle « administrateur » communauté (filet de sécurité notifications recrutement).
     *
     * @return list<string>
     */
    public function listAdministratorEmailsForTenant(int $tenantId): array
    {
        $pack = $this->technicalAccountExclusionPredicate('u');
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
            AND r.slug IN ('administrator')";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_merge([$tenantId], $pack['params']));
        $emails = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $e = strtolower(trim((string) ($row['email'] ?? '')));
            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $e;
            }
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.email FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
                AND r.slug IN ('administrator')";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute(array_merge([$tenantId], $pack['params']));
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $e = strtolower(trim((string) ($row['email'] ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $emails[] = $e;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Membres actifs susceptibles de modérer le forum (alertes internes).
     *
     * @return list<int>
     */
    public function listForumAlertRecipientUserIds(int $tenantId): array
    {
        $ids = [];
        $pack = $this->technicalAccountExclusionPredicate('u');
        $slugs = "'tenant_admin', 'community_owner', 'forum_moderator', 'administrator'";
        $sql = "SELECT DISTINCT u.id FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
            AND r.slug IN ({$slugs})";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute(array_merge([$tenantId], $pack['params']));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        } catch (\Throwable) {
            return [];
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.id FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
                AND r.slug IN ({$slugs})";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute(array_merge([$tenantId], $pack['params']));
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = (int) ($row['id'] ?? 0);
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Membres actifs avec une adresse e-mail utilisable (diffusions internes).
     *
     * @return list<int>
     */
    public function listActiveUserIdsEligibleForEmailBroadcast(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $pack = $this->technicalAccountExclusionPredicate('u');
        $sql = "SELECT u.id FROM users u
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']}
            AND u.email IS NOT NULL AND TRIM(u.email) <> ''
            AND u.email LIKE '%@%'";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute(array_merge([$tenantId], $pack['params']));
            $ids = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }

            return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Nombre d’adresses e-mail distinctes (comptes actifs, hors comptes techniques), toutes communautés.
     */
    public function countDistinctActiveMemberEmailsPlatformWide(): int
    {
        $pack = $this->technicalAccountExclusionPredicate('u');
        $sql = "SELECT COUNT(*) FROM (
            SELECT LOWER(TRIM(u.email)) AS e
            FROM users u
            WHERE u.status = 'active'
            AND u.email IS NOT NULL AND TRIM(u.email) <> ''
            AND u.email LIKE '%@%'
            AND {$pack['sql']}
            GROUP BY LOWER(TRIM(u.email))
        ) t";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($pack['params']);

            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Adresses e-mail distinctes (comptes actifs, hors comptes techniques), toutes communautés — pagination.
     *
     * @return list<string>
     */
    public function listDistinctActiveMemberEmailsPlatformWide(int $limit, int $offset): array
    {
        $pack = $this->technicalAccountExclusionPredicate('u');
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT LOWER(TRIM(u.email)) AS email
            FROM users u
            WHERE u.status = 'active'
            AND u.email IS NOT NULL AND TRIM(u.email) <> ''
            AND u.email LIKE '%@%'
            AND {$pack['sql']}
            GROUP BY LOWER(TRIM(u.email))
            ORDER BY email ASC
            LIMIT {$limit} OFFSET {$offset}";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($pack['params']);
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $e = strtolower(trim((string) ($row['email'] ?? '')));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $out[] = $e;
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Membres actifs dont le rôle communauté (principal ou additionnel) est parmi les slugs donnés.
     *
     * @param list<string> $roleSlugs
     * @return list<int>
     */
    public function listActiveUserIdsWithOrganizationRoleSlugs(int $tenantId, array $roleSlugs): array
    {
        $roleSlugs = array_values(array_unique(array_filter(array_map('trim', $roleSlugs))));
        if ($tenantId < 1 || $roleSlugs === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($roleSlugs), '?'));
        $pack = $this->technicalAccountExclusionPredicate('u');
        $params = array_merge([$tenantId], $pack['params'], $roleSlugs);
        $ids = [];
        $sql = "SELECT DISTINCT u.id FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']} AND r.slug IN ({$placeholders})";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        } catch (\Throwable) {
            return [];
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.id FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id AND tur.org_unit_id IS NULL
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']} AND r.slug IN ({$placeholders})";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute($params);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = (int) ($row['id'] ?? 0);
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Membres actifs de **cette communauté uniquement** ayant au moins une des permissions listées
     * (rôle principal et rôles additionnels communauté / intra).
     *
     * Ne jamais utiliser pour des alertes communauté : {@see listActiveEmailsHavingPermissionGlobally}
     * (escale plateforme volontairement cross-tenant).
     *
     * Filtre strict : `users`, `roles`, `permissions` et `tenant_user_roles` sont tous liés au
     * `$tenantId` passé en paramètre (pas de permissions site `tenant_id IS NULL`).
     *
     * @param list<string> $permissionSlugs
     * @return list<int>
     */
    public function listActiveUserIdsWithAnyPermissionSlug(int $tenantId, array $permissionSlugs): array
    {
        if ($tenantId < 1 || $permissionSlugs === []) {
            return [];
        }
        $permissionSlugs = array_values(array_unique(array_filter(array_map('trim', $permissionSlugs))));
        if ($permissionSlugs === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($permissionSlugs), '?'));
        $pack = $this->technicalAccountExclusionPredicate('u');
        // Ordre des ? (dans l’ordre d’apparition SQL) : roles.tenant_id, permissions.tenant_id,
        // users.tenant_id, exclusion technique, puis slugs. Jamais de permission site (tenant_id NULL).
        $params = array_merge([$tenantId, $tenantId, $tenantId], $pack['params'], $permissionSlugs);
        $ids = [];
        $sql = "SELECT DISTINCT u.id FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = ?
            INNER JOIN role_permissions rp ON rp.role_id = r.id
            INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = ?
            WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']} AND p.slug IN ({$placeholders})";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        } catch (\Throwable) {
            return [];
        }
        if ($this->hasTenantUserRolesTable()) {
            // Ordre : tur.tenant_id, roles.tenant_id, permissions.tenant_id, users.tenant_id, exclusion, slugs
            $params2 = array_merge([$tenantId, $tenantId, $tenantId, $tenantId], $pack['params'], $permissionSlugs);
            $sql2 = "SELECT DISTINCT u.id FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = ?
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = ?
                INNER JOIN role_permissions rp ON rp.role_id = r.id
                INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = ?
                WHERE u.tenant_id = ? AND u.status = 'active' AND {$pack['sql']} AND p.slug IN ({$placeholders})";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute($params2);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $ids[] = (int) ($row['id'] ?? 0);
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Courriels des comptes actifs disposant d’une permission donnée sur au moins une communauté (ex. escale vers l’équipe site).
     *
     * ATTENTION : volontairement cross-tenant. Réservé aux escalades plateforme (ex. recrutement site).
     * Pour les alertes d’une communauté (élévation RH, publication formation, etc.), utiliser
     * {@see listActiveUserIdsWithAnyPermissionSlug} avec le tenant courant.
     *
     * @return list<string>
     */
    public function listActiveEmailsHavingPermissionGlobally(string $permissionSlug, int $limit = 40): array
    {
        $slug = trim($permissionSlug);
        if ($slug === '') {
            return [];
        }
        $lim = max(1, min(80, $limit));
        $seen = [];
        $pack = $this->technicalAccountExclusionPredicate('u');
        $params = array_merge($pack['params'], [$slug]);
        $sql = "SELECT DISTINCT u.email FROM users u
            INNER JOIN roles r ON r.id = u.role_id AND r.tenant_id = u.tenant_id
            INNER JOIN role_permissions rp ON rp.role_id = r.id
            INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
            WHERE u.status = 'active' AND {$pack['sql']} AND p.slug = ?
            LIMIT {$lim}";
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $e = strtolower(trim((string) ($row['email'] ?? '')));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $seen[$e] = true;
                }
            }
        } catch (\Throwable) {
        }
        if ($this->hasTenantUserRolesTable()) {
            $sql2 = "SELECT DISTINCT u.email FROM users u
                INNER JOIN tenant_user_roles tur ON tur.user_id = u.id AND tur.tenant_id = u.tenant_id
                INNER JOIN roles r ON r.id = tur.role_id AND r.tenant_id = u.tenant_id
                INNER JOIN role_permissions rp ON rp.role_id = r.id
                INNER JOIN permissions p ON p.id = rp.permission_id AND p.tenant_id = u.tenant_id
                WHERE u.status = 'active' AND {$pack['sql']} AND p.slug = ?
                LIMIT {$lim}";
            try {
                $st = $this->pdo()->prepare($sql2);
                $st->execute($params);
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $e = strtolower(trim((string) ($row['email'] ?? '')));
                    if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                        $seen[$e] = true;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return array_keys($seen);
    }

    public function invalidateAllSessionsForUser(int $userId, ?int $tenantId = null): void
    {
        if ($tenantId !== null) {
            $this->pdo()->prepare('DELETE FROM sessions WHERE user_id = ? AND tenant_id = ?')->execute([$userId, $tenantId]);
        } else {
            $this->pdo()->prepare('DELETE FROM sessions WHERE user_id = ?')->execute([$userId]);
        }
    }
}
