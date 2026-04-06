<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Référentiel grades multi-doctrine (table grades après bascule, ou grades_referentiel avant).
 * Pas de tenant_id : référentiel global.
 */
class GradeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    private function tableName(): string
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'grade_system_id' LIMIT 1");
        return ($stmt && $stmt->fetch()) ? 'grades' : 'grades_referentiel';
    }

    private function selectColumns(): string
    {
        return 'g.id, g.grade_system_id, g.grade_category_id, g.code, g.label_short, g.label_long, g.label_otan, g.sort_order, g.is_commissioned, g.is_active,
            gs.code AS system_code, gs.country_code, gs.format_type AS system_format_type,
            gc.code AS category_code, gc.label AS category_label';
    }

    private function fromClause(): string
    {
        return $this->tableName() . ' g
            INNER JOIN grade_systems gs ON g.grade_system_id = gs.id
            INNER JOIN grade_categories gc ON g.grade_category_id = gc.id';
    }

    /**
     * Liste tous les grades actifs (tous systèmes), pour compatibilité avec listForTenant.
     * @return list<array<string, mixed>>
     */
    public function listActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT ' . $this->selectColumns() . ' FROM ' . $this->fromClause() . ' WHERE g.is_active = 1 ORDER BY gs.country_code ASC, g.sort_order ASC, g.id ASC'
        );
        if ($stmt === false) {
            return [];
        }
        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Liste les grades d'un système par code (ex. FR_CLASSIC, US_CLASSIC).
     * @return list<array<string, mixed>>
     */
    public function listBySystemCode(string $systemCode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->selectColumns() . ' FROM ' . $this->fromClause() . ' WHERE g.is_active = 1 AND gs.code = ? ORDER BY g.sort_order ASC, g.id ASC'
        );
        $stmt->execute([$systemCode]);
        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Filtre optionnel par catégorie (id de grade_categories), pour les écrans d’administration.
     *
     * @return list<array<string, mixed>>
     */
    public function listBySystemCodeAndCategoryId(string $systemCode, ?int $gradeCategoryId): array
    {
        if ($gradeCategoryId === null || $gradeCategoryId < 1) {
            return $this->listBySystemCode($systemCode);
        }
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->selectColumns() . ' FROM ' . $this->fromClause() . ' WHERE g.is_active = 1 AND gs.code = ? AND g.grade_category_id = ? ORDER BY g.sort_order ASC, g.id ASC'
        );
        $stmt->execute([$systemCode, $gradeCategoryId]);

        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Liste les grades d'un système par id.
     * @return list<array<string, mixed>>
     */
    public function listBySystemId(int $systemId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->selectColumns() . ' FROM ' . $this->fromClause() . ' WHERE g.is_active = 1 AND g.grade_system_id = ? ORDER BY g.sort_order ASC, g.id ASC'
        );
        $stmt->execute([$systemId]);
        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Grades visibles pour une communauté : système choisi dans settings (grade_system_code),
     * avec overrides optionnels (tenant_grade_overrides).
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId): array
    {
        $tenantRepo = new TenantRepository();
        $settings = $tenantRepo->getSettings($tenantId);
        $code = isset($settings['grade_system_code']) ? trim((string) $settings['grade_system_code']) : '';
        if ($code === '') {
            return $this->listActive();
        }
        $rows = $this->listBySystemCode($code);
        $overrideRepo = new TenantGradeOverrideRepository();
        if (!$overrideRepo->tableExists()) {
            return $rows;
        }
        $stmt = $this->pdo->prepare(
            'SELECT grade_id, label_short_override, label_long_override, sort_order_override, is_enabled
             FROM tenant_grade_overrides WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);
        $over = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $over[(int) $r['grade_id']] = $r;
        }
        $out = [];
        foreach ($rows as $g) {
            $id = (int) $g['id'];
            if (isset($over[$id]) && (int) ($over[$id]['is_enabled'] ?? 1) === 0) {
                continue;
            }
            if (isset($over[$id])) {
                $o = $over[$id];
                if ($o['label_short_override'] !== null && $o['label_short_override'] !== '') {
                    $g['label_short'] = (string) $o['label_short_override'];
                }
                if ($o['label_long_override'] !== null && $o['label_long_override'] !== '') {
                    $g['label_long'] = (string) $o['label_long_override'];
                }
                if ($o['sort_order_override'] !== null) {
                    $g['sort_order'] = (int) $o['sort_order_override'];
                }
            }
            $out[] = $g;
        }
        usort($out, static function ($a, $b) {
            $oa = (int) ($a['sort_order'] ?? 0);
            $ob = (int) ($b['sort_order'] ?? 0);
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $out;
    }

    /**
     * Récupère un grade avec système et catégorie (référentiel).
     * Si non trouvé et que l'ancienne table grades existe, tente findByIdLegacy (transition).
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . $this->selectColumns() . ' FROM ' . $this->fromClause() . ' WHERE g.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            return $this->normalizeRow($row);
        }
        if ($tenantId !== null) {
            return $this->findByIdLegacy($id, $tenantId);
        }
        return null;
    }

    /**
     * Vérifie si la table référentiel existe (pour bascule migration).
     */
    public function isReferentielTablePresent(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades_referentiel' LIMIT 1");
        if ($stmt && $stmt->fetch()) {
            return true;
        }
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'grade_system_id' LIMIT 1");
        return $stmt && (bool) $stmt->fetch();
    }

    /**
     * Rétrocompat : si l'app utilise encore l'ancienne table grades (tenant), la lire.
     * À utiliser uniquement avant la bascule complète.
     */
    public function findByIdLegacy(int $id, ?int $tenantId = null): ?array
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades_legacy' LIMIT 1");
        $table = ($stmt && $stmt->fetch()) ? 'grades_legacy' : null;
        if ($table === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'grades' AND COLUMN_NAME = 'tenant_id' LIMIT 1");
            if ($stmt && $stmt->fetch()) {
                $table = 'grades';
            }
        }
        if ($table === null) {
            return null;
        }
        $sql = 'SELECT id, name AS label_long, short_name AS label_short, nato_code AS label_otan, rank_order AS sort_order FROM ' . $table . ' WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null && $table === 'grades') {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['country_code'] = 'FR';
        $row['category_code'] = 'OFFICIER';
        $row['system_code'] = 'FR_CLASSIC';
        $row['category_label'] = 'Officier';
        return $row;
    }

    private function normalizeRow(array $r): array
    {
        $r['id'] = (int) $r['id'];
        $r['grade_system_id'] = (int) $r['grade_system_id'];
        $r['grade_category_id'] = (int) $r['grade_category_id'];
        $r['sort_order'] = (int) ($r['sort_order'] ?? 0);
        $r['is_commissioned'] = (int) ($r['is_commissioned'] ?? 0);
        $r['is_active'] = (int) ($r['is_active'] ?? 1);
        return $r;
    }

    /** @param list<array<string, mixed>> $rows */
    private function normalizeRows(array $rows): array
    {
        return array_map([$this, 'normalizeRow'], $rows);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . $this->tableName() . ' (grade_system_id, grade_category_id, code, label_short, label_long, label_otan, sort_order, is_commissioned, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            (int) $data['grade_system_id'],
            (int) $data['grade_category_id'],
            $data['code'],
            $data['label_short'],
            $data['label_long'],
            $data['label_otan'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_commissioned']) ? 1 : 0,
            isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE ' . $this->tableName() . ' SET grade_system_id = ?, grade_category_id = ?, code = ?, label_short = ?, label_long = ?, label_otan = ?, sort_order = ?, is_commissioned = ?, is_active = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([
            (int) $data['grade_system_id'],
            (int) $data['grade_category_id'],
            $data['code'],
            $data['label_short'],
            $data['label_long'],
            $data['label_otan'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_commissioned']) ? 1 : 0,
            isset($data['is_active']) ? (int) $data['is_active'] : 1,
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->pdo->prepare('UPDATE ' . $this->tableName() . ' SET is_active = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
        return $stmt->rowCount() > 0;
    }

    public function updateSortOrder(int $id, int $sortOrder): bool
    {
        $stmt = $this->pdo->prepare('UPDATE ' . $this->tableName() . ' SET sort_order = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$sortOrder, $id]);
        return $stmt->rowCount() > 0;
    }
}
