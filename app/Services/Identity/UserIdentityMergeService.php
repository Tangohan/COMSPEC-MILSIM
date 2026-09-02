<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Repositories\UserCommunityMembershipRepository;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Support\SilentSchemaMigration;
use App\Support\SqlText;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Fusion explicite et journalisée : un e-mail → un users.id survivant.
 * Les dossiers RH restent scopés par communauté (tenant_id).
 */
final class UserIdentityMergeService
{
    public function __construct(
        private PDO $pdo,
        private UserCommunityMembershipRepository $memberships,
        private ?AuditService $audit = null,
    ) {
        $this->audit ??= new AuditService();
    }

    public function ensureSchema(): void
    {
        SilentSchemaMigration::run(base_path('bootstrap/user_community_identity_migration.php'), $this->pdo);
    }

    /**
     * @return array{groups: int, merged: int, collisions: int, errors: list<string>}
     */
    public function mergeAllDuplicateEmails(): array
    {
        $this->ensureSchema();
        $summary = ['groups' => 0, 'merged' => 0, 'collisions' => 0, 'errors' => []];
        foreach ($this->listDuplicateEmailGroups() as $email => $rows) {
            $summary['groups']++;
            try {
                $result = $this->mergeEmailGroup($email, $rows);
                $summary['merged'] += $result['merged'];
                $summary['collisions'] += count($result['steam_collisions']);
            } catch (Throwable $e) {
                $summary['errors'][] = $email . ': ' . $e->getMessage();
            }
        }
        $this->tryAddGlobalEmailUnique();

        return $summary;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function listDuplicateEmailGroups(): array
    {
        $st = $this->pdo->query(
            "SELECT u.*
             FROM users u
             INNER JOIN (
                SELECT LOWER(TRIM(email)) AS email_key
                FROM users
                WHERE email IS NOT NULL AND TRIM(email) <> ''
                  AND LOWER(TRIM(email)) NOT LIKE '%@deleted.invalid'
                  AND LOWER(TRIM(email)) NOT LIKE '%@merged.invalid'
                  AND (is_service_account IS NULL OR is_service_account = 0)
                GROUP BY LOWER(TRIM(email))
                HAVING COUNT(*) > 1
             ) d ON LOWER(TRIM(u.email)) = d.email_key
             WHERE (u.is_service_account IS NULL OR u.is_service_account = 0)
             ORDER BY LOWER(TRIM(u.email)) ASC, u.id ASC"
        );
        $groups = [];
        foreach ($st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $row) {
            $email = UserIdentityMergeRules::normalizeEmail((string) ($row['email'] ?? ''));
            if (!UserIdentityMergeRules::isLiveHumanEmail($email)) {
                continue;
            }
            $groups[$email][] = $row;
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array{survivor_id: int, merged: int, steam_collisions: list<array{absorbed_user_id: int, steam_id: string}>}
     */
    public function mergeEmailGroup(string $email, array $rows, ?int $survivorUserId = null): array
    {
        $email = UserIdentityMergeRules::normalizeEmail($email);
        $live = [];
        foreach ($rows as $row) {
            if (UserIdentityMergeRules::isServiceAccount($row)) {
                continue;
            }
            $live[] = $row;
        }
        if (count($live) < 2) {
            return ['survivor_id' => (int) ($live[0]['id'] ?? 0), 'merged' => 0, 'steam_collisions' => []];
        }

        $survivor = $this->resolveSurvivor($live, $survivorUserId);
        $survivorId = (int) $survivor['id'];
        $absorbed = array_values(array_filter(
            $live,
            static fn (array $r): bool => (int) ($r['id'] ?? 0) !== $survivorId
        ));

        $identity = UserIdentityMergeRules::mergeIdentityOntoSurvivor($survivor, $absorbed);
        $this->pdo->beginTransaction();
        try {
            $this->applyIdentityFields($survivorId, $identity['fields']);
            $this->ensureMembershipWithProfile(
                $survivorId,
                (int) $survivor['tenant_id'],
                UserIdentityMergeRules::communityProfileFromUserRow($survivor),
                $survivorId
            );

            foreach ($absorbed as $row) {
                $this->absorbRow($survivor, $row, $identity['steam_collisions']);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'survivor_id' => $survivorId,
            'merged' => count($absorbed),
            'steam_collisions' => $identity['steam_collisions'],
        ];
    }

    /**
     * Prévisualise une fusion pour l’administration plateforme.
     *
     * @return array{
     *   mergeable: bool,
     *   email: string,
     *   suggested_survivor_id: int,
     *   rows: list<array<string, mixed>>,
     *   identity_fills: array<string, mixed>,
     *   steam_collisions: list<array{absorbed_user_id: int, steam_id: string}>,
     *   identity_table_fills: array<string, array<string, mixed>>,
     *   rh_tenants: list<array{tenant_id: int, absorbed_user_id: int, action: string}>
     * }
     */
    public function previewEmailMerge(string $email, ?int $survivorUserId = null): array
    {
        $email = UserIdentityMergeRules::normalizeEmail($email);
        $rows = $this->fetchLiveRowsForEmail($email);
        $empty = [
            'mergeable' => false,
            'email' => $email,
            'suggested_survivor_id' => 0,
            'rows' => $rows,
            'identity_fills' => [],
            'steam_collisions' => [],
            'identity_table_fills' => [],
            'rh_tenants' => [],
        ];
        if (count($rows) < 2) {
            return $empty;
        }

        $survivor = $this->resolveSurvivor($rows, $survivorUserId);
        $survivorId = (int) $survivor['id'];
        $absorbed = array_values(array_filter(
            $rows,
            static fn (array $r): bool => (int) ($r['id'] ?? 0) !== $survivorId
        ));
        $identity = UserIdentityMergeRules::mergeIdentityOntoSurvivor($survivor, $absorbed);
        $identityTableFills = [];
        foreach ($absorbed as $row) {
            $absorbedId = (int) ($row['id'] ?? 0);
            if ($absorbedId < 1) {
                continue;
            }
            foreach (UserIdentityMergeRules::IDENTITY_TABLE_MERGE_FIELDS as $table => $fields) {
                if (!$this->tableExists($table)) {
                    continue;
                }
                $survivorRow = $this->fetchOneToOneRow($table, $survivorId);
                $absorbedRow = $this->fetchOneToOneRow($table, $absorbedId);
                if ($survivorRow === null || $absorbedRow === null) {
                    continue;
                }
                $fills = UserIdentityMergeRules::mergeRowFieldsOntoSurvivor($survivorRow, $absorbedRow, $fields);
                if ($fills !== []) {
                    $identityTableFills[$table] = array_merge($identityTableFills[$table] ?? [], $fills);
                }
            }
        }

        $rhTenants = [];
        foreach ($absorbed as $row) {
            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $absorbedId = (int) ($row['id'] ?? 0);
            if ($tenantId < 1 || $absorbedId < 1) {
                continue;
            }
            $rhTenants[] = [
                'tenant_id' => $tenantId,
                'absorbed_user_id' => $absorbedId,
                'action' => 'remap_rh_and_references',
            ];
        }

        return [
            'mergeable' => true,
            'email' => $email,
            'suggested_survivor_id' => $survivorId,
            'rows' => $rows,
            'identity_fills' => $identity['fields'],
            'steam_collisions' => $identity['steam_collisions'],
            'identity_table_fills' => $identityTableFills,
            'rh_tenants' => $rhTenants,
        ];
    }

    /**
     * Fusionne tous les comptes actifs partageant un e-mail (action admin).
     *
     * @return array{survivor_id: int, merged: int, steam_collisions: list<array{absorbed_user_id: int, steam_id: string}>}
     */
    public function mergeAccountsForEmail(string $email, ?int $survivorUserId = null): array
    {
        $this->ensureSchema();
        $email = UserIdentityMergeRules::normalizeEmail($email);
        $rows = $this->fetchLiveRowsForEmail($email);
        if (count($rows) < 2) {
            throw new InvalidArgumentException('Cette adresse ne comporte pas plusieurs comptes fusionnables.');
        }

        return $this->mergeEmailGroup($email, $rows, $survivorUserId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchLiveRowsForEmail(string $email): array
    {
        $email = UserIdentityMergeRules::normalizeEmail($email);
        if (!UserIdentityMergeRules::isLiveHumanEmail($email)) {
            return [];
        }
        $emailEq = SqlText::normalizedEquals($this->pdo, 'email');
        $statusMerged = SqlText::inLiterals($this->pdo, 'status', ['merged']);
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users
             WHERE {$emailEq}
               AND NOT {$statusMerged}
               AND (is_service_account IS NULL OR is_service_account = 0)
             ORDER BY id ASC"
        );
        $stmt->execute([$email]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (UserIdentityMergeRules::isServiceAccount($row)) {
                continue;
            }
            if (!UserIdentityMergeRules::isLiveHumanEmail((string) ($row['email'] ?? ''))) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function resolveSurvivor(array $rows, ?int $survivorUserId): array
    {
        if ($survivorUserId !== null && $survivorUserId > 0) {
            foreach ($rows as $row) {
                if ((int) ($row['id'] ?? 0) === $survivorUserId) {
                    return $row;
                }
            }
            throw new InvalidArgumentException('Compte survivant introuvable pour cette adresse.');
        }

        return UserIdentityMergeRules::pickSurvivor($rows);
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function ensureMembershipWithProfile(int $userId, int $tenantId, array $profile, ?int $sourceUserId = null): void
    {
        if ($this->memberships->hasMembership($userId, $tenantId)) {
            $this->memberships->upsertProfileFillEmpty($userId, $tenantId, $profile);

            return;
        }
        $this->memberships->ensureMembership($userId, $tenantId, $profile, $sourceUserId);
    }

    /**
     * @param array<string, mixed> $survivor
     * @param array<string, mixed> $absorbed
     * @param list<array{absorbed_user_id: int, steam_id: string}> $collisions
     */
    private function absorbRow(array $survivor, array $absorbed, array $collisions): void
    {
        $survivorId = (int) $survivor['id'];
        $absorbedId = (int) $absorbed['id'];
        $tenantId = (int) $absorbed['tenant_id'];
        if ($survivorId < 1 || $absorbedId < 1 || $tenantId < 1) {
            return;
        }

        $this->ensureMembershipWithProfile(
            $survivorId,
            $tenantId,
            UserIdentityMergeRules::communityProfileFromUserRow($absorbed),
            $absorbedId
        );

        $this->scopeRhThenRemap($absorbedId, $survivorId, $tenantId);
        $this->remapForeignKeys($absorbedId, $survivorId, $tenantId);
        $this->remapIdentityOneToOne($absorbedId, $survivorId);

        $collision = false;
        $absorbedSteam = null;
        foreach ($collisions as $c) {
            if ((int) $c['absorbed_user_id'] === $absorbedId) {
                $collision = true;
                $absorbedSteam = $c['steam_id'];
                break;
            }
        }

        $snapshot = json_encode([
            'id' => $absorbedId,
            'tenant_id' => $tenantId,
            'email' => $absorbed['email'] ?? null,
            'steam_id' => $absorbed['steam_id'] ?? null,
            'athena_identifier' => $absorbed['athena_identifier'] ?? null,
            'grade_id' => $absorbed['grade_id'] ?? null,
            'role_id' => $absorbed['role_id'] ?? null,
            'callsign' => $absorbed['callsign'] ?? null,
            'status' => $absorbed['status'] ?? null,
            'created_at' => $absorbed['created_at'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        $this->pdo->prepare(
            'INSERT INTO user_identity_merges
                (survivor_user_id, absorbed_user_id, email, absorbed_tenant_id, steam_collision, absorbed_steam_id, absorbed_snapshot)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE merged_at = NOW()'
        )->execute([
            $survivorId,
            $absorbedId,
            UserIdentityMergeRules::normalizeEmail((string) ($absorbed['email'] ?? '')),
            $tenantId,
            $collision ? 1 : 0,
            $absorbedSteam,
            $snapshot !== false ? $snapshot : null,
        ]);

        $stubEmail = UserIdentityMergeRules::mergedStubEmail($absorbedId);
        $this->pdo->prepare(
            "UPDATE users
             SET email = ?, password_hash = '', steam_id = NULL, status = 'merged',
                 display_name = 'Compte fusionné', updated_at = NOW()
             WHERE id = ?"
        )->execute([$stubEmail, $absorbedId]);

        try {
            $this->audit?->log(
                AuditAction::USER_IDENTITY_MERGED,
                $tenantId,
                $survivorId,
                'user',
                $survivorId,
                (string) $absorbedId,
                $stubEmail
            );
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function applyIdentityFields(int $survivorId, array $fields): void
    {
        if ($fields === [] || $survivorId < 1) {
            return;
        }
        $set = [];
        $params = [];
        foreach ($fields as $key => $value) {
            if (!in_array($key, UserIdentityMergeRules::IDENTITY_FIELDS, true) || $key === 'email') {
                continue;
            }
            if (!$this->columnExists('users', $key)) {
                continue;
            }
            $set[] = '`' . $key . '` = ?';
            $params[] = $value;
        }
        if ($set === []) {
            return;
        }
        $params[] = $survivorId;
        $this->pdo->prepare(
            'UPDATE users SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = ?'
        )->execute($params);
    }

    private function scopeRhThenRemap(int $absorbedId, int $survivorId, int $tenantId): void
    {
        foreach (UserIdentityMergeRules::RH_ONE_TO_ONE_TABLES as $table) {
            if (!$this->tableExists($table) || !$this->columnExists($table, 'user_id')) {
                continue;
            }
            if ($this->columnExists($table, 'tenant_id')) {
                $this->pdo->prepare(
                    "UPDATE `{$table}` SET tenant_id = ? WHERE user_id = ? AND (tenant_id IS NULL OR tenant_id = 0)"
                )->execute([$tenantId, $absorbedId]);
                try {
                    $this->pdo->prepare(
                        "UPDATE `{$table}` SET user_id = ? WHERE user_id = ? AND tenant_id = ?"
                    )->execute([$survivorId, $absorbedId, $tenantId]);
                } catch (Throwable) {
                    // Doublon (user_id, tenant_id) : on conserve le dossier déjà scopé du survivant.
                }
            }
        }
    }

    private function remapForeignKeys(int $absorbedId, int $survivorId, int $tenantId): void
    {
        $skip = array_merge(
            UserIdentityMergeRules::IDENTITY_ONE_TO_ONE_TABLES,
            UserIdentityMergeRules::RH_ONE_TO_ONE_TABLES,
            ['users', 'user_identity_merges', 'user_community_memberships', 'user_community_profiles']
        );
        $st = $this->pdo->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'users'
               AND REFERENCED_COLUMN_NAME = 'id'"
        );
        $st->execute();
        $seen = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ref) {
            $table = (string) ($ref['TABLE_NAME'] ?? '');
            $column = (string) ($ref['COLUMN_NAME'] ?? '');
            $key = $table . '.' . $column;
            if ($table === '' || $column === '' || isset($seen[$key]) || in_array($table, $skip, true)) {
                continue;
            }
            $seen[$key] = true;
            $this->remapUserColumn($table, $column, $absorbedId, $survivorId, $tenantId);
        }

        foreach ([
            ['audit_logs', 'user_id'],
            ['sessions', 'user_id'],
            ['account_tenant_memberships', 'user_id'],
            ['tenant_user_roles', 'user_id'],
            ['user_units', 'user_id'],
        ] as [$table, $column]) {
            $key = $table . '.' . $column;
            if (isset($seen[$key]) || !$this->tableExists($table)) {
                continue;
            }
            $this->remapUserColumn($table, $column, $absorbedId, $survivorId, $tenantId);
        }
    }

    private function remapUserColumn(string $table, string $column, int $absorbedId, int $survivorId, int $tenantId): void
    {
        $hasTenant = $this->columnExists($table, 'tenant_id');
        try {
            if ($hasTenant) {
                $this->pdo->prepare(
                    "UPDATE `{$table}` SET `{$column}` = ? WHERE `{$column}` = ? AND tenant_id = ?"
                )->execute([$survivorId, $absorbedId, $tenantId]);
            } else {
                $this->pdo->prepare(
                    "UPDATE `{$table}` SET `{$column}` = ? WHERE `{$column}` = ?"
                )->execute([$survivorId, $absorbedId]);
            }
        } catch (Throwable) {
            // Contrainte d’unicité : on laisse la ligne absorbée (orphelin journalisé par le stub).
        }
    }

    private function remapIdentityOneToOne(int $absorbedId, int $survivorId): void
    {
        foreach (UserIdentityMergeRules::IDENTITY_ONE_TO_ONE_TABLES as $table) {
            if (!$this->tableExists($table) || !$this->columnExists($table, 'user_id')) {
                continue;
            }
            $survivorRow = $this->fetchOneToOneRow($table, $survivorId);
            $absorbedRow = $this->fetchOneToOneRow($table, $absorbedId);
            if ($survivorRow !== null && $absorbedRow !== null) {
                $this->mergeIdentityOneToOneFields($table, $survivorId, $absorbedRow);
                continue;
            }
            if ($survivorRow !== null) {
                continue;
            }
            if ($absorbedRow === null) {
                continue;
            }
            try {
                $this->pdo->prepare("UPDATE `{$table}` SET user_id = ? WHERE user_id = ?")->execute([$survivorId, $absorbedId]);
            } catch (Throwable) {
            }
        }
    }

    /**
     * @param array<string, mixed> $absorbedRow
     */
    private function mergeIdentityOneToOneFields(string $table, int $survivorId, array $absorbedRow): void
    {
        $fields = UserIdentityMergeRules::IDENTITY_TABLE_MERGE_FIELDS[$table] ?? null;
        if ($fields === null || $fields === []) {
            return;
        }
        $survivorRow = $this->fetchOneToOneRow($table, $survivorId);
        if ($survivorRow === null) {
            return;
        }
        $fills = UserIdentityMergeRules::mergeRowFieldsOntoSurvivor($survivorRow, $absorbedRow, $fields);
        if ($fills === []) {
            return;
        }
        $set = [];
        $params = [];
        foreach ($fills as $key => $value) {
            $set[] = '`' . $key . '` = ?';
            $params[] = $value;
        }
        $params[] = $survivorId;
        $this->pdo->prepare(
            'UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE user_id = ?'
        )->execute($params);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOneToOneRow(string $table, int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }
        $st = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE user_id = ? LIMIT 1");
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function tryAddGlobalEmailUnique(): void
    {
        $st = $this->pdo->prepare(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uk_users_email_identity' LIMIT 1"
        );
        $st->execute();
        if ($st->fetchColumn()) {
            return;
        }
        $dup = $this->pdo->query(
            "SELECT 1 FROM users
             WHERE (is_service_account IS NULL OR is_service_account = 0)
               AND LOWER(TRIM(email)) NOT LIKE '%@deleted.invalid'
               AND LOWER(TRIM(email)) NOT LIKE '%@merged.invalid'
             GROUP BY LOWER(TRIM(email))
             HAVING COUNT(*) > 1
             LIMIT 1"
        );
        if ($dup && $dup->fetchColumn()) {
            return;
        }
        try {
            if (!$this->columnExists('users', 'email_identity')) {
                return;
            }
            $this->pdo->exec('ALTER TABLE users ADD UNIQUE KEY uk_users_email_identity (email_identity)');
        } catch (Throwable) {
        }
    }

    private function tableExists(string $table): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }
}
