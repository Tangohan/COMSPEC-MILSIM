<?php

declare(strict_types=1);

namespace App\Services\Identity;

use PDO;
use Throwable;

/**
 * Reprend les dossiers laissés sur les comptes absorbés après la fusion « un e-mail = un compte ».
 * Ne remplit que les champs vides : une valeur déjà saisie sur le compte survivant n’est pas écrasée.
 */
final class UserIdentityProfileRestoreService
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *   merges: int,
     *   personnel: int,
     *   extras: int,
     *   user_profiles: int,
     *   legal: int,
     *   community_profiles: int
     * }
     */
    public function restoreAll(): array
    {
        $summary = [
            'merges' => 0,
            'personnel' => 0,
            'extras' => 0,
            'user_profiles' => 0,
            'legal' => 0,
            'community_profiles' => 0,
        ];
        if (!$this->tableExists('user_identity_merges')) {
            return $summary;
        }

        $st = $this->pdo->query('SELECT * FROM user_identity_merges ORDER BY id ASC');
        foreach ($st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $merge) {
            $summary['merges']++;
            $survivorId = (int) ($merge['survivor_user_id'] ?? 0);
            $absorbedId = (int) ($merge['absorbed_user_id'] ?? 0);
            $tenantId = (int) ($merge['absorbed_tenant_id'] ?? 0);
            $snapshot = json_decode((string) ($merge['absorbed_snapshot'] ?? ''), true);
            if (!is_array($snapshot)) {
                $snapshot = [];
            }
            if ($survivorId < 1 || $absorbedId < 1) {
                continue;
            }
            $summary['personnel'] += $this->restoreRhTable('personnel_profiles', $survivorId, $absorbedId, $tenantId);
            $summary['extras'] += $this->restoreRhTable('personnel_extras', $survivorId, $absorbedId, $tenantId);
            $summary['user_profiles'] += $this->restoreIdentityTable('user_profiles', $survivorId, $absorbedId);
            $summary['legal'] += $this->restoreIdentityTable('user_legal_identities', $survivorId, $absorbedId);
            $summary['community_profiles'] += $this->restoreCommunityProfile(
                $survivorId,
                $absorbedId,
                $tenantId,
                $snapshot
            );
        }

        return $summary;
    }

    private function restoreRhTable(string $table, int $survivorId, int $absorbedId, int $tenantId): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, 'user_id')) {
            return 0;
        }
        $changed = 0;
        $absorbedRows = $this->fetchRowsByUserId($table, $absorbedId);
        if ($absorbedRows === [] && $tenantId > 0) {
            return 0;
        }
        foreach ($absorbedRows as $absorbed) {
            $rowTenant = (int) ($absorbed['tenant_id'] ?? 0);
            if ($rowTenant < 1) {
                $rowTenant = $tenantId;
            }
            $changed += $this->moveOrFillRhRow($table, $survivorId, $rowTenant, $absorbed);
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $absorbed
     */
    private function moveOrFillRhRow(string $table, int $survivorId, int $tenantId, array $absorbed): int
    {
        $survivorRows = $this->fetchRowsByUserId($table, $survivorId);
        $target = null;
        if ($tenantId > 0) {
            foreach ($survivorRows as $row) {
                if ((int) ($row['tenant_id'] ?? 0) === $tenantId) {
                    $target = $row;
                    break;
                }
            }
        }
        if ($target === null && count($survivorRows) === 1 && $tenantId > 0) {
            $only = $survivorRows[0];
            $onlyTenant = (int) ($only['tenant_id'] ?? 0);
            if (($onlyTenant < 1 || $onlyTenant === $tenantId)
                && UserIdentityMergeRules::dossierCompletenessScore($only) === 0) {
                $target = $only;
            }
        } elseif ($target === null && $survivorRows === []) {
            $moved = $this->tryRemapUserId(
                $table,
                (int) ($absorbed['id'] ?? 0),
                $survivorId,
                $tenantId,
                (int) ($absorbed['user_id'] ?? 0)
            );
            if ($moved) {
                return 1;
            }
            return $this->insertFilledCopy($table, $survivorId, $tenantId, $absorbed) ? 1 : 0;
        }

        if ($target === null) {
            if ($this->insertFilledCopy($table, $survivorId, $tenantId, $absorbed)) {
                return 1;
            }
            if (count($survivorRows) === 1
                && UserIdentityMergeRules::dossierCompletenessScore($survivorRows[0]) === 0) {
                $fill = UserIdentityMergeRules::fillEmptyKeys($survivorRows[0], $absorbed);
                if ($tenantId > 0 && $this->columnExists($table, 'tenant_id')) {
                    $fill['tenant_id'] = $tenantId;
                }
                if ($fill === []) {
                    return 0;
                }

                return $this->updateRowById($table, (int) ($survivorRows[0]['id'] ?? 0), $survivorRows[0], $fill) ? 1 : 0;
            }

            return 0;
        }

        $fill = UserIdentityMergeRules::fillEmptyKeys($target, $absorbed);
        if ($tenantId > 0 && $this->columnExists($table, 'tenant_id') && (int) ($target['tenant_id'] ?? 0) < 1) {
            $fill['tenant_id'] = $tenantId;
        }
        if ($fill === []) {
            return 0;
        }

        return $this->updateRowById($table, (int) ($target['id'] ?? 0), $target, $fill) ? 1 : 0;
    }

    private function restoreIdentityTable(string $table, int $survivorId, int $absorbedId): int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, 'user_id')) {
            return 0;
        }
        $absorbed = $this->fetchRowsByUserId($table, $absorbedId)[0] ?? null;
        if ($absorbed === null) {
            return 0;
        }
        $survivor = $this->fetchRowsByUserId($table, $survivorId)[0] ?? null;
        if ($survivor === null) {
            return $this->tryRemapUserId(
                $table,
                (int) ($absorbed['id'] ?? 0),
                $survivorId,
                0,
                $absorbedId
            ) ? 1 : 0;
        }
        $fill = UserIdentityMergeRules::fillEmptyKeys($survivor, $absorbed);
        if ($fill === []) {
            return 0;
        }
        $id = (int) ($survivor['id'] ?? 0);
        if ($id > 0 && $this->columnExists($table, 'id')) {
            return $this->updateRowById($table, $id, $survivor, $fill) ? 1 : 0;
        }

        return $this->updateRowByUserId($table, $survivorId, $survivor, $fill) ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function restoreCommunityProfile(int $survivorId, int $absorbedId, int $tenantId, array $snapshot): int
    {
        if ($tenantId < 1 || !$this->tableExists('user_community_profiles')) {
            return 0;
        }
        $absorbedProfile = $this->fetchCommunityProfile($absorbedId, $tenantId) ?? [];
        $absorbedUser = $this->fetchUserRow($absorbedId) ?? [];
        $source = array_merge($snapshot, $absorbedUser, $absorbedProfile);
        $source = UserIdentityMergeRules::communityProfileFromUserRow($source);
        if ($source === [] || !UserIdentityMergeRules::communityProfileHasSubstance($source)) {
            return 0;
        }
        $survivorProfile = $this->fetchCommunityProfile($survivorId, $tenantId) ?? [];
        $fill = $survivorProfile === []
            ? $source
            : UserIdentityMergeRules::fillEmptyKeys($survivorProfile, $source);
        if ($fill === []) {
            return 0;
        }
        $this->upsertCommunityFill($survivorId, $tenantId, $fill);

        return 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRowsByUserId(string $table, int $userId): array
    {
        $st = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE user_id = ?");
        $st->execute([$userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchCommunityProfile(int $userId, int $tenantId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM user_community_profiles WHERE user_id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$userId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUserRow(int $userId): ?array
    {
        if (!$this->tableExists('users')) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function tryRemapUserId(string $table, int $rowId, int $survivorId, int $tenantId, int $absorbedUserId = 0): bool
    {
        try {
            if ($rowId > 0 && $this->columnExists($table, 'id')) {
                if ($tenantId > 0 && $this->columnExists($table, 'tenant_id')) {
                    $this->pdo->prepare(
                        "UPDATE `{$table}` SET user_id = ?, tenant_id = COALESCE(NULLIF(tenant_id, 0), ?) WHERE id = ?"
                    )->execute([$survivorId, $tenantId, $rowId]);
                } else {
                    $this->pdo->prepare("UPDATE `{$table}` SET user_id = ? WHERE id = ?")->execute([$survivorId, $rowId]);
                }

                return true;
            }
            if ($absorbedUserId > 0) {
                $this->pdo->prepare("UPDATE `{$table}` SET user_id = ? WHERE user_id = ?")->execute([$survivorId, $absorbedUserId]);

                return true;
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function insertFilledCopy(string $table, int $survivorId, int $tenantId, array $source): bool
    {
        $cols = ['user_id'];
        $placeholders = ['?'];
        $params = [$survivorId];
        if ($this->columnExists($table, 'tenant_id') && $tenantId > 0) {
            $cols[] = 'tenant_id';
            $placeholders[] = '?';
            $params[] = $tenantId;
        }
        foreach ($source as $key => $value) {
            $key = (string) $key;
            if (in_array($key, ['id', 'user_id', 'tenant_id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if (!$this->columnExists($table, $key) || UserIdentityMergeRules::isEmptyIdentityValue($value)) {
                continue;
            }
            $cols[] = $key;
            $placeholders[] = '?';
            $params[] = $value;
        }
        if (count($cols) <= 1) {
            return false;
        }
        try {
            $this->pdo->prepare(
                'INSERT INTO `' . $table . '` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')'
            )->execute($params);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $fill
     */
    private function updateRowById(string $table, int $id, array $current, array $fill): bool
    {
        if ($id < 1) {
            return $this->updateRowByUserId($table, (int) ($current['user_id'] ?? 0), $current, $fill);
        }
        $set = [];
        $params = [];
        foreach ($fill as $key => $value) {
            if (!$this->columnExists($table, (string) $key)) {
                continue;
            }
            $set[] = '`' . $key . '` = ?';
            $params[] = $value;
        }
        if ($set === []) {
            return false;
        }
        $params[] = $id;
        $this->pdo->prepare(
            'UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE id = ?'
        )->execute($params);

        return true;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $fill
     */
    private function updateRowByUserId(string $table, int $userId, array $current, array $fill): bool
    {
        if ($userId < 1) {
            return false;
        }
        $set = [];
        $params = [];
        foreach ($fill as $key => $value) {
            if (!$this->columnExists($table, (string) $key)) {
                continue;
            }
            $set[] = '`' . $key . '` = ?';
            $params[] = $value;
        }
        if ($set === []) {
            return false;
        }
        $params[] = $userId;
        $this->pdo->prepare(
            'UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE user_id = ?'
        )->execute($params);

        return true;
    }

    /**
     * @param array<string, mixed> $fill
     */
    private function upsertCommunityFill(int $userId, int $tenantId, array $fill): void
    {
        if ($this->isSqlite()) {
            $existing = $this->fetchCommunityProfile($userId, $tenantId);
            if ($existing === null) {
                $this->insertFilledCopy('user_community_profiles', $userId, $tenantId, $fill);

                return;
            }
            $toApply = UserIdentityMergeRules::fillEmptyKeys($existing, $fill);
            if ($toApply === []) {
                return;
            }
            $id = (int) ($existing['id'] ?? 0);
            if ($id > 0) {
                $this->updateRowById('user_community_profiles', $id, $existing, $toApply);
            }

            return;
        }
        $cols = ['user_id', 'tenant_id'];
        $placeholders = ['?', '?'];
        $params = [$userId, $tenantId];
        $updates = [];
        foreach (UserIdentityMergeRules::COMMUNITY_PROFILE_FIELDS as $key) {
            if (!array_key_exists($key, $fill) || UserIdentityMergeRules::isEmptyIdentityValue($fill[$key])) {
                continue;
            }
            if ($key === 'display_name' && is_string($fill[$key]) && UserIdentityMergeRules::isMergedStubDisplayName((string) $fill[$key])) {
                continue;
            }
            $value = $fill[$key];
            if (in_array($key, ['athena_identifier', 'profile_slug'], true) && trim((string) $value) === '') {
                continue;
            }
            $cols[] = $key;
            $placeholders[] = '?';
            $params[] = $value;
            $updates[] = $key . ' = IFNULL(NULLIF(' . $key . ", ''), VALUES(" . $key . '))';
        }
        $sql = 'INSERT INTO user_community_profiles (' . implode(', ', $cols) . ')
                VALUES (' . implode(', ', $placeholders) . ')';
        if ($updates !== []) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        }
        try {
            $this->pdo->prepare($sql)->execute($params);
        } catch (Throwable) {
        }
    }

    private function tableExists(string $table): bool
    {
        if ($this->isSqlite()) {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1"
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        }
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->isSqlite()) {
            $st = $this->pdo->query('PRAGMA table_info(' . $table . ')');
            foreach ($st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [] as $col) {
                if (($col['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }
        $st = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }

    private function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }
}
