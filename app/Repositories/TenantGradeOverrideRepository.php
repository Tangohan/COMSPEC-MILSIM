<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantGradeOverrideRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_grade_overrides' LIMIT 1");

        return $stmt && (bool) $stmt->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function listByTenant(int $tenantId): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT grade_id, label_short_override, label_long_override, sort_order_override, is_enabled
             FROM tenant_grade_overrides WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['grade_id']] = $row;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $gradeId): ?array
    {
        if (!$this->tableExists() || $tenantId < 1 || $gradeId < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM tenant_grade_overrides WHERE tenant_id = ? AND grade_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $gradeId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{label_short?: ?string, label_long?: ?string, sort_order?: ?int, is_enabled?: bool} $data
     */
    public function upsert(int $tenantId, int $gradeId, array $data): void
    {
        if (!$this->tableExists() || $tenantId < 1 || $gradeId < 1) {
            return;
        }
        $current = $this->find($tenantId, $gradeId);
        $short = array_key_exists('label_short', $data)
            ? $this->nullableString($data['label_short'] ?? null)
            : ($current['label_short_override'] ?? null);
        $long = array_key_exists('label_long', $data)
            ? $this->nullableString($data['label_long'] ?? null)
            : ($current['label_long_override'] ?? null);
        $order = array_key_exists('sort_order', $data)
            ? (isset($data['sort_order']) && $data['sort_order'] !== '' && $data['sort_order'] !== null ? (int) $data['sort_order'] : null)
            : (isset($current['sort_order_override']) ? (int) $current['sort_order_override'] : null);
        $enabled = array_key_exists('is_enabled', $data)
            ? (!empty($data['is_enabled']) ? 1 : 0)
            : (int) ($current['is_enabled'] ?? 1);

        $st = $this->pdo->prepare(
            'INSERT INTO tenant_grade_overrides
                (tenant_id, grade_id, label_short_override, label_long_override, sort_order_override, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                label_short_override = VALUES(label_short_override),
                label_long_override = VALUES(label_long_override),
                sort_order_override = VALUES(sort_order_override),
                is_enabled = VALUES(is_enabled),
                updated_at = NOW()'
        );
        $st->execute([$tenantId, $gradeId, $short, $long, $order, $enabled]);
    }

    /** @param list<array{grade_id: int, label_short: ?string, label_long: ?string, sort_order: ?int, is_enabled: bool}> $rows */
    public function replaceForTenant(int $tenantId, array $rows): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $del = $this->pdo->prepare('DELETE FROM tenant_grade_overrides WHERE tenant_id = ?');
        $del->execute([$tenantId]);
        if ($rows === []) {
            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO tenant_grade_overrides (tenant_id, grade_id, label_short_override, label_long_override, sort_order_override, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        foreach ($rows as $r) {
            $ins->execute([
                $tenantId,
                $r['grade_id'],
                $r['label_short'] !== null && $r['label_short'] !== '' ? $r['label_short'] : null,
                $r['label_long'] !== null && $r['label_long'] !== '' ? $r['label_long'] : null,
                $r['sort_order'] !== null ? $r['sort_order'] : null,
                $r['is_enabled'] ? 1 : 0,
            ]);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
