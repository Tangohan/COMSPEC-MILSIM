<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class PositionRepository
{
    public const CATEGORY_OPERATIONAL = 'operational';

    public const CATEGORY_STAFF = 'staff';

    public const CATEGORY_ADMINISTRATIVE = 'administrative';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_OPERATIONAL,
        self::CATEGORY_STAFF,
        self::CATEGORY_ADMINISTRATIVE,
    ];

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        self::CATEGORY_OPERATIONAL => 'Opérationnel',
        self::CATEGORY_STAFF => 'État-major / encadrement',
        self::CATEGORY_ADMINISTRATIVE => 'Administratif',
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableExists(): bool
    {
        static $ok;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'positions' LIMIT 1");
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    private function hasCategoryColumn(): bool
    {
        static $ok;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'positions' AND COLUMN_NAME = 'category' LIMIT 1"
            );
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    private function hasDefaultRoleSetColumn(): bool
    {
        static $ok;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'positions' AND COLUMN_NAME = 'default_role_set_id' LIMIT 1"
            );
            $ok = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? self::CATEGORY_LABELS[self::CATEGORY_OPERATIONAL];
    }

    public static function normalizeCategory(string $category): string
    {
        $category = trim($category);

        return in_array($category, self::CATEGORIES, true) ? $category : self::CATEGORY_OPERATIONAL;
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        if ($this->hasCategoryColumn() && $this->hasDefaultRoleSetColumn()) {
            $st = $this->pdo->prepare(
                'SELECT p.*, rs.name AS default_role_set_name
                 FROM positions p
                 LEFT JOIN role_sets rs ON rs.id = p.default_role_set_id AND rs.tenant_id = p.tenant_id
                 WHERE p.tenant_id = ?
                 ORDER BY FIELD(p.category, \'administrative\', \'staff\', \'operational\'), p.name ASC'
            );
        } else {
            $st = $this->pdo->prepare('SELECT * FROM positions WHERE tenant_id = ? ORDER BY name ASC');
        }
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findForTenant(int $tenantId, int $positionId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1 || $positionId < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM positions WHERE id = ? AND tenant_id = ? LIMIT 1');
        $st->execute([$positionId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(
        int $tenantId,
        string $name,
        ?string $description,
        bool $isTemporary,
        string $category = self::CATEGORY_OPERATIONAL,
        ?int $defaultRoleSetId = null
    ): int {
        if (!$this->tableExists()) {
            return 0;
        }
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $category = self::normalizeCategory($category);
        $desc = $description !== null ? mb_substr(trim($description), 0, 500) : null;
        if ($desc === '') {
            $desc = null;
        }
        if ($defaultRoleSetId !== null && $defaultRoleSetId < 1) {
            $defaultRoleSetId = null;
        }

        if ($this->hasCategoryColumn() && $this->hasDefaultRoleSetColumn()) {
            $st = $this->pdo->prepare(
                'INSERT INTO positions (tenant_id, name, description, category, is_temporary, default_role_set_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $st->execute([
                $tenantId,
                mb_substr($name, 0, 160),
                $desc,
                $category,
                $isTemporary ? 1 : 0,
                $defaultRoleSetId,
            ]);
        } else {
            $st = $this->pdo->prepare(
                'INSERT INTO positions (tenant_id, name, description, is_temporary, created_at) VALUES (?, ?, ?, ?, NOW())'
            );
            $st->execute([$tenantId, mb_substr($name, 0, 160), $desc, $isTemporary ? 1 : 0]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $positionId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM positions WHERE id = ? AND tenant_id = ?');

        return $st->execute([$positionId, $tenantId]) && $st->rowCount() > 0;
    }

    public function assignUser(int $tenantId, int $userId, int $positionId, string $startsAt, ?string $endsAt, ?int $assignedBy): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $st = $this->pdo->prepare(
            'INSERT INTO user_positions (tenant_id, user_id, position_id, starts_at, ends_at, assigned_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );

        return $st->execute([
            $tenantId,
            $userId,
            $positionId,
            $startsAt,
            $endsAt !== null && $endsAt !== '' ? $endsAt : null,
            $assignedBy,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForUser(int $tenantId, int $userId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        if ($this->hasCategoryColumn()) {
            $st = $this->pdo->prepare(
                'SELECT up.*, p.name AS position_name, p.category AS position_category,
                        p.description AS position_description
                 FROM user_positions up
                 INNER JOIN positions p ON p.id = up.position_id AND p.tenant_id = up.tenant_id
                 WHERE up.tenant_id = ? AND up.user_id = ?
                 AND up.starts_at <= CURDATE() AND (up.ends_at IS NULL OR up.ends_at >= CURDATE())
                 ORDER BY FIELD(p.category, \'administrative\', \'staff\', \'operational\'), up.starts_at DESC'
            );
        } else {
            $st = $this->pdo->prepare(
                'SELECT up.*, p.name AS position_name FROM user_positions up
                 INNER JOIN positions p ON p.id = up.position_id AND p.tenant_id = up.tenant_id
                 WHERE up.tenant_id = ? AND up.user_id = ?
                 AND up.starts_at <= CURDATE() AND (up.ends_at IS NULL OR up.ends_at >= CURDATE())
                 ORDER BY up.starts_at DESC'
            );
        }
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
