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
}
