<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class OrganizationCatalogRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::getPdo();
    }

    public function tablesExist(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_catalog_items' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    public function upsertOfficial(string $code, string $title, string $summary, int $version, array $definition): int
    {
        $json = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st = $this->pdo->prepare(
            'INSERT INTO organization_catalog_items
                (code, title, summary, kind, visibility, owner_tenant_id, version, definition_json, created_at, updated_at)
             VALUES (?, ?, ?, \'organization_kit\', \'official\', NULL, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                summary = VALUES(summary),
                version = VALUES(version),
                definition_json = VALUES(definition_json),
                updated_at = NOW()'
        );
        $st->execute([$code, $title, $summary, $version, $json]);
        $id = (int) $this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }
        $row = $this->findByCode($code);

        return (int) ($row['id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $definition
     */
    public function createPrivate(
        int $ownerTenantId,
        string $code,
        string $title,
        string $summary,
        int $version,
        array $definition
    ): int {
        $json = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st = $this->pdo->prepare(
            'INSERT INTO organization_catalog_items
                (code, title, summary, kind, visibility, owner_tenant_id, version, definition_json, created_at, updated_at)
             VALUES (?, ?, ?, \'organization_kit\', \'private\', ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([$code, $title, $summary, $ownerTenantId, $version, $json]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id < 1 || !$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM organization_catalog_items WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '' || !$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM organization_catalog_items WHERE code = ? LIMIT 1');
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listVisibleForTenant(int $tenantId): array
    {
        if (!$this->tablesExist()) {
            return [];
        }
        $this->ensureArchivedColumn();
        $st = $this->pdo->prepare(
            'SELECT * FROM organization_catalog_items
             WHERE (visibility = \'official\'
                OR (visibility = \'private\' AND owner_tenant_id = ?))
               AND archived_at IS NULL
             ORDER BY visibility ASC, title ASC, id ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listArchivedForTenant(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->tablesExist()) {
            return [];
        }
        $this->ensureArchivedColumn();
        $st = $this->pdo->prepare(
            'SELECT * FROM organization_catalog_items
             WHERE visibility = \'private\' AND owner_tenant_id = ? AND archived_at IS NOT NULL
             ORDER BY archived_at DESC, id DESC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $report
     */
    public function recordInstall(int $tenantId, int $itemId, int $sourceVersion, ?int $appliedBy, array $report): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO organization_catalog_installs
                (tenant_id, item_id, source_version, applied_at, applied_by, report_json)
             VALUES (?, ?, ?, NOW(), ?, ?)'
        );
        $st->execute([
            $tenantId,
            $itemId,
            $sourceVersion,
            $appliedBy,
            json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function hasInstallForTenant(int $tenantId): bool
    {
        if ($tenantId < 1 || !$this->tablesExist()) {
            return false;
        }
        try {
            $st = $this->pdo->prepare('SELECT 1 FROM organization_catalog_installs WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tenantId]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function countInstallsForTenant(int $tenantId): int
    {
        if ($tenantId < 1 || !$this->tablesExist()) {
            return 0;
        }
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM organization_catalog_installs WHERE tenant_id = ?');
        $st->execute([$tenantId]);

        return (int) $st->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInstallsForTenant(int $tenantId, int $limit = 12): array
    {
        if ($tenantId < 1 || !$this->tablesExist()) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $st = $this->pdo->prepare(
            'SELECT i.*, c.title AS item_title, c.code AS item_code, c.visibility AS item_visibility,
                    c.archived_at AS item_archived_at
             FROM organization_catalog_installs i
             LEFT JOIN organization_catalog_items c ON c.id = i.item_id
             WHERE i.tenant_id = ?
             ORDER BY i.applied_at DESC, i.id DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findInstallForTenant(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1 || !$this->tablesExist()) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT i.*, c.title AS item_title, c.code AS item_code, c.visibility AS item_visibility,
                    c.archived_at AS item_archived_at
             FROM organization_catalog_installs i
             LEFT JOIN organization_catalog_items c ON c.id = i.item_id
             WHERE i.tenant_id = ? AND i.id = ?
             LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function renamePrivate(int $ownerTenantId, string $code, string $title): bool
    {
        $title = trim($title);
        $code = trim($code);
        if ($ownerTenantId < 1 || $code === '' || $title === '') {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE organization_catalog_items
             SET title = ?, updated_at = NOW()
             WHERE code = ? AND visibility = \'private\' AND owner_tenant_id = ?'
        );
        $st->execute([$title, $code, $ownerTenantId]);

        return $st->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $definition
     */
    public function replacePrivateDefinition(
        int $ownerTenantId,
        string $code,
        string $title,
        string $summary,
        int $version,
        array $definition
    ): bool {
        $code = trim($code);
        $title = trim($title);
        if ($ownerTenantId < 1 || $code === '' || $title === '') {
            return false;
        }
        $json = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $st = $this->pdo->prepare(
            'UPDATE organization_catalog_items
             SET title = ?, summary = ?, version = ?, definition_json = ?, updated_at = NOW()
             WHERE code = ? AND visibility = \'private\' AND owner_tenant_id = ?'
        );
        $st->execute([$title, $summary, $version, $json, $code, $ownerTenantId]);

        return $st->rowCount() > 0;
    }

    public function archivePrivate(int $ownerTenantId, string $code): bool
    {
        $this->ensureArchivedColumn();
        $code = trim($code);
        if ($ownerTenantId < 1 || $code === '') {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE organization_catalog_items
             SET archived_at = NOW(), updated_at = NOW()
             WHERE code = ? AND visibility = \'private\' AND owner_tenant_id = ? AND archived_at IS NULL'
        );
        $st->execute([$code, $ownerTenantId]);

        return $st->rowCount() > 0;
    }

    public function restorePrivate(int $ownerTenantId, string $code): bool
    {
        $this->ensureArchivedColumn();
        $code = trim($code);
        if ($ownerTenantId < 1 || $code === '') {
            return false;
        }
        $st = $this->pdo->prepare(
            'UPDATE organization_catalog_items
             SET archived_at = NULL, updated_at = NOW()
             WHERE code = ? AND visibility = \'private\' AND owner_tenant_id = ? AND archived_at IS NOT NULL'
        );
        $st->execute([$code, $ownerTenantId]);

        return $st->rowCount() > 0;
    }

    private function ensureArchivedColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$this->tablesExist()) {
            return;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'organization_catalog_items'
                   AND COLUMN_NAME = 'archived_at'
                 LIMIT 1"
            );
            if ($st !== false && (bool) $st->fetchColumn()) {
                return;
            }
            $this->pdo->exec('ALTER TABLE organization_catalog_items ADD COLUMN archived_at DATETIME NULL DEFAULT NULL');
        } catch (\Throwable) {
        }
    }
}
