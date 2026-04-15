<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Données BDD pour {@see \App\Services\Platform\ModuleReleaseAccessResolver} (tables platform_*).
 */
final class PlatformModuleReleaseRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'platform_modules' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function findModuleByCode(string $code): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM platform_modules WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Versions courantes par code canal (PROD, DEV, …).
     *
     * @return array<string, array<string, mixed>>
     */
    public function findCurrentReleasesByChannelForModule(int $moduleId): array
    {
        $sql = 'SELECT dc.code AS channel_code, mv.id AS module_version_id, mv.version AS version,
                mcr.id AS release_row_id, mcr.module_id
                FROM platform_module_channel_releases mcr
                INNER JOIN deployment_channels dc ON dc.id = mcr.channel_id
                INNER JOIN platform_module_versions mv ON mv.id = mcr.module_version_id
                WHERE mcr.module_id = ? AND mcr.is_current = 1';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$moduleId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $code = strtoupper(trim((string) ($row['channel_code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $out[$code] = [
                'id' => (int) ($row['release_row_id'] ?? 0),
                'module_version_id' => (int) ($row['module_version_id'] ?? 0),
                'version' => (string) ($row['version'] ?? ''),
                'module_id' => (int) ($row['module_id'] ?? 0),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function listActiveModuleAccessRules(int $moduleId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT mar.*, dc.code AS environment_channel_code
             FROM platform_module_access_rules mar
             LEFT JOIN deployment_channels dc ON dc.id = mar.environment_channel_id
             WHERE mar.module_id = ? AND mar.is_active = 1
             ORDER BY mar.priority DESC, mar.id DESC'
        );
        $st->execute([$moduleId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<string> */
    public function listUserTesterCommunityCodes(int $userId): array
    {
        if (!$this->testerMembershipSchemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT UPPER(TRIM(tc.code)) AS c
             FROM tester_community_members tcm
             INNER JOIN tester_communities tc ON tc.id = tcm.community_id
             WHERE tcm.user_id = ? AND tcm.status = \'active\' AND tc.is_active = 1
               AND (tcm.expires_at IS NULL OR tcm.expires_at > NOW())'
        );
        $st->execute([$userId]);
        $codes = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $c = trim((string) ($row['c'] ?? ''));
            if ($c !== '') {
                $codes[] = $c;
            }
        }

        return array_values(array_unique($codes));
    }

    /** @return list<int> */
    public function listUserTesterCommunityIds(int $userId): array
    {
        if (!$this->testerMembershipSchemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT tcm.community_id
             FROM tester_community_members tcm
             INNER JOIN tester_communities tc ON tc.id = tcm.community_id
             WHERE tcm.user_id = ? AND tcm.status = \'active\' AND tc.is_active = 1
               AND (tcm.expires_at IS NULL OR tcm.expires_at > NOW())'
        );
        $st->execute([$userId]);
        $ids = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $ids[] = (int) ($row['community_id'] ?? 0);
        }

        return array_values(array_filter(array_unique($ids), static fn (int $id): bool => $id > 0));
    }

    private function testerMembershipSchemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tester_community_members' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Communautés de préqualification auxquelles l’utilisateur participe encore activement.
     *
     * @return list<array<string, mixed>>
     */
    public function listActiveTesterCommunitiesForUser(int $userId): array
    {
        if ($userId < 1 || !$this->testerMembershipSchemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT tc.id, tc.name, tc.description, tc.valid_from, tc.valid_until
             FROM tester_community_members tcm
             INNER JOIN tester_communities tc ON tc.id = tcm.community_id
             WHERE tcm.user_id = ? AND tcm.status = \'active\' AND tc.is_active = 1
               AND (tcm.expires_at IS NULL OR tcm.expires_at > NOW())
             ORDER BY tc.priority ASC, tc.name ASC'
        );
        $st->execute([$userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Fonctionnalités plateforme liées aux communautés de test du membre (règles actives).
     *
     * @return list<array{module_id: int, module_name: string, module_description: ?string, rule_type: string}>
     */
    public function listModuleAccessRowsForUserTesterCommunities(int $userId): array
    {
        if ($userId < 1 || !$this->schemaReady() || !$this->testerMembershipSchemaReady()) {
            return [];
        }
        $sql = 'SELECT DISTINCT pm.id AS module_id, pm.name AS module_name, pm.description AS module_description,
                mar.rule_type
                FROM platform_module_access_rules mar
                INNER JOIN platform_modules pm ON pm.id = mar.module_id
                INNER JOIN tester_community_members tcm ON tcm.community_id = mar.community_id
                INNER JOIN tester_communities tc ON tc.id = mar.community_id
                WHERE mar.is_active = 1 AND mar.community_id IS NOT NULL
                  AND mar.rule_type IN (\'allow_community\', \'deny_community\')
                  AND tcm.user_id = ? AND tcm.status = \'active\' AND tc.is_active = 1
                  AND (tcm.expires_at IS NULL OR tcm.expires_at > NOW())
                ORDER BY pm.name ASC, mar.rule_type ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$userId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $mid = (int) ($row['module_id'] ?? 0);
            if ($mid < 1) {
                continue;
            }
            $out[] = [
                'module_id' => $mid,
                'module_name' => (string) ($row['module_name'] ?? ''),
                'module_description' => isset($row['module_description']) && $row['module_description'] !== ''
                    ? (string) $row['module_description']
                    : null,
                'rule_type' => (string) ($row['rule_type'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function listDeploymentChannels(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        try {
            $st = $this->pdo()->query(
                'SELECT id, code, name, priority FROM deployment_channels ORDER BY priority ASC, id ASC'
            );
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function listPlatformModules(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->query(
            'SELECT id, code, name, description, is_active, is_public FROM platform_modules ORDER BY name ASC'
        );
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listModuleVersions(int $moduleId): array
    {
        if (!$this->schemaReady() || $moduleId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT id, module_id, version, status, created_at FROM platform_module_versions
             WHERE module_id = ? ORDER BY created_at DESC, id DESC'
        );
        $st->execute([$moduleId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listCurrentReleasesAllModules(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT mcr.module_id, pm.name AS module_name, pm.code AS module_code,
                dc.id AS channel_id, dc.code AS channel_code, dc.name AS channel_name,
                mv.version, mcr.is_current, mcr.id AS release_id
                FROM platform_module_channel_releases mcr
                INNER JOIN deployment_channels dc ON dc.id = mcr.channel_id
                INNER JOIN platform_module_versions mv ON mv.id = mcr.module_version_id
                INNER JOIN platform_modules pm ON pm.id = mcr.module_id
                WHERE mcr.is_current = 1
                ORDER BY pm.name ASC, dc.priority ASC';
        $st = $this->pdo()->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function findModuleById(int $moduleId): ?array
    {
        if (!$this->schemaReady() || $moduleId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM platform_modules WHERE id = ? LIMIT 1');
        $st->execute([$moduleId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findTesterCommunityById(int $id): ?array
    {
        if (!$this->testerCommunitiesTableReady() || $id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM tester_communities WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findChannelById(int $channelId): ?array
    {
        if (!$this->schemaReady() || $channelId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM deployment_channels WHERE id = ? LIMIT 1');
        $st->execute([$channelId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findVersionById(int $versionId): ?array
    {
        if (!$this->schemaReady() || $versionId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM platform_module_versions WHERE id = ? LIMIT 1');
        $st->execute([$versionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function insertPlatformModule(string $code, string $name, ?string $description, bool $isActive, bool $isPublic): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO platform_modules (code, name, description, is_active, is_public) VALUES (?,?,?,?,?)'
        );
        $st->execute([$code, $name, $description, $isActive ? 1 : 0, $isPublic ? 1 : 0]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updatePlatformModule(int $id, string $name, ?string $description, bool $isActive, bool $isPublic): void
    {
        $st = $this->pdo()->prepare(
            'UPDATE platform_modules SET name = ?, description = ?, is_active = ?, is_public = ? WHERE id = ?'
        );
        $st->execute([$name, $description, $isActive ? 1 : 0, $isPublic ? 1 : 0, $id]);
    }

    public function insertModuleVersion(int $moduleId, string $version, string $status, ?int $createdBy): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO platform_module_versions (module_id, version, status, created_by) VALUES (?,?,?,?)'
        );
        $st->execute([$moduleId, $version, $status, $createdBy]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateModuleVersionStatus(int $versionId, string $status): void
    {
        $st = $this->pdo()->prepare('UPDATE platform_module_versions SET status = ? WHERE id = ?');
        $st->execute([$status, $versionId]);
    }

    /**
     * Définit la version courante pour un module sur un canal (une seule ligne courante par couple module/canal).
     */
    public function setCurrentReleaseForModuleChannel(int $moduleId, int $channelId, int $moduleVersionId, ?int $deployedBy): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $clear = $pdo->prepare(
                'UPDATE platform_module_channel_releases SET is_current = 0
                 WHERE module_id = ? AND channel_id = ? AND is_current = 1'
            );
            $clear->execute([$moduleId, $channelId]);
            $ins = $pdo->prepare(
                'INSERT INTO platform_module_channel_releases (module_id, module_version_id, channel_id, is_current, deployed_by)
                 VALUES (?,?,?,1,?)'
            );
            $ins->execute([$moduleId, $moduleVersionId, $channelId, $deployedBy]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function testerCommunitiesTableReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tester_communities' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listTesterCommunities(): array
    {
        if (!$this->testerCommunitiesTableReady()) {
            return [];
        }
        $st = $this->pdo()->query(
            'SELECT * FROM tester_communities ORDER BY priority ASC, name ASC'
        );
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listTesterCommunityMembers(int $communityId): array
    {
        if (!$this->testerMembershipSchemaReady() || $communityId < 1) {
            return [];
        }
        $sql = 'SELECT tcm.*, u.callsign, u.email
                FROM tester_community_members tcm
                INNER JOIN users u ON u.id = tcm.user_id
                WHERE tcm.community_id = ?
                ORDER BY tcm.joined_at DESC';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$communityId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function insertTesterCommunityMember(int $communityId, int $userId, ?string $expiresAt): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO tester_community_members (community_id, user_id, expires_at, status)
             VALUES (?,?,?,\'active\')
             ON DUPLICATE KEY UPDATE status = \'active\', expires_at = VALUES(expires_at), joined_at = joined_at'
        );
        $st->execute([$communityId, $userId, $expiresAt !== null && $expiresAt !== '' ? $expiresAt : null]);
    }

    public function removeTesterCommunityMember(int $communityId, int $userId): void
    {
        $st = $this->pdo()->prepare(
            'DELETE FROM tester_community_members WHERE community_id = ? AND user_id = ?'
        );
        $st->execute([$communityId, $userId]);
    }

    public function updateTesterCommunityMeta(
        int $id,
        string $name,
        ?string $description,
        bool $isActive,
        ?string $validFrom,
        ?string $validUntil,
        int $priority,
    ): void {
        $st = $this->pdo()->prepare(
            'UPDATE tester_communities SET name = ?, description = ?, is_active = ?, valid_from = ?, valid_until = ?, priority = ? WHERE id = ?'
        );
        $st->execute([
            $name,
            $description,
            $isActive ? 1 : 0,
            $validFrom !== null && $validFrom !== '' ? $validFrom : null,
            $validUntil !== null && $validUntil !== '' ? $validUntil : null,
            $priority,
            $id,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listAccessRulesForModule(int $moduleId): array
    {
        if (!$this->schemaReady() || $moduleId < 1) {
            return [];
        }
        $sql = 'SELECT mar.*, tc.name AS community_name, dc.code AS environment_channel_code
                FROM platform_module_access_rules mar
                LEFT JOIN tester_communities tc ON tc.id = mar.community_id
                LEFT JOIN deployment_channels dc ON dc.id = mar.environment_channel_id
                WHERE mar.module_id = ?
                ORDER BY mar.is_active DESC, mar.priority DESC, mar.id DESC';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$moduleId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function insertModuleAccessRule(
        int $moduleId,
        string $ruleType,
        ?int $communityId,
        ?int $appliesToVersionId,
        ?int $environmentChannelId,
        int $priority,
        bool $isActive,
    ): int {
        $st = $this->pdo()->prepare(
            'INSERT INTO platform_module_access_rules
             (module_id, rule_type, community_id, applies_to_version_id, environment_channel_id, priority, is_active)
             VALUES (?,?,?,?,?,?,?)'
        );
        $st->execute([
            $moduleId,
            $ruleType,
            $communityId,
            $appliesToVersionId,
            $environmentChannelId,
            $priority,
            $isActive ? 1 : 0,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function deleteModuleAccessRule(int $ruleId): void
    {
        $st = $this->pdo()->prepare('DELETE FROM platform_module_access_rules WHERE id = ?');
        $st->execute([$ruleId]);
    }

    public function setModuleAccessRuleActive(int $ruleId, bool $active): void
    {
        $st = $this->pdo()->prepare('UPDATE platform_module_access_rules SET is_active = ? WHERE id = ?');
        $st->execute([$active ? 1 : 0, $ruleId]);
    }
}
