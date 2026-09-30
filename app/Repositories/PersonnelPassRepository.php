<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PersonnelPassRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_passes' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function hasRequiredPassColumn(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, 'required_pass_id']);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listPasses(int $tenantId, bool $activeOnly = false, ?string $forUse = null): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $sql = 'SELECT * FROM personnel_passes WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        if ($forUse === 'post') {
            $sql .= ' AND use_for_post = 1';
        } elseif ($forUse === 'advancement') {
            $sql .= ' AND use_for_advancement = 1';
        } elseif ($forUse === 'notation') {
            $sql .= ' AND use_for_notation = 1';
        }
        $sql .= ' ORDER BY label ASC, id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady() || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM personnel_passes WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $createdBy = null): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO personnel_passes
             (tenant_id, code, label, description, logic, use_for_post, use_for_advancement, use_for_notation, is_active, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            $this->normalizeCode((string) ($data['code'] ?? ''), (string) ($data['label'] ?? '')),
            mb_substr(trim((string) ($data['label'] ?? '')), 0, 160),
            $this->nullableText($data['description'] ?? null),
            ((string) ($data['logic'] ?? 'all')) === 'any' ? 'any' : 'all',
            !empty($data['use_for_post']) ? 1 : 0,
            !empty($data['use_for_advancement']) ? 1 : 0,
            !empty($data['use_for_notation']) ? 1 : 0,
            !array_key_exists('is_active', $data) || !empty($data['is_active']) ? 1 : 0,
            $createdBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $tenantId, int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE personnel_passes SET
                code = ?, label = ?, description = ?, logic = ?,
                use_for_post = ?, use_for_advancement = ?, use_for_notation = ?, is_active = ?
             WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([
            $this->normalizeCode((string) ($data['code'] ?? ''), (string) ($data['label'] ?? '')),
            mb_substr(trim((string) ($data['label'] ?? '')), 0, 160),
            $this->nullableText($data['description'] ?? null),
            ((string) ($data['logic'] ?? 'all')) === 'any' ? 'any' : 'all',
            !empty($data['use_for_post']) ? 1 : 0,
            !empty($data['use_for_advancement']) ? 1 : 0,
            !empty($data['use_for_notation']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
            $tenantId,
            $id,
        ]);
    }

    public function delete(int $tenantId, int $id): void
    {
        $st = $this->pdo->prepare('DELETE FROM personnel_passes WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listConditions(int $tenantId, int $passId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM personnel_pass_conditions WHERE tenant_id = ? AND pass_id = ? ORDER BY position ASC, id ASC'
        );
        $st->execute([$tenantId, $passId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Remplace toutes les conditions du PASS.
     *
     * @param list<array<string, mixed>> $conditions
     */
    public function replaceConditions(int $tenantId, int $passId, array $conditions): void
    {
        $del = $this->pdo->prepare('DELETE FROM personnel_pass_conditions WHERE tenant_id = ? AND pass_id = ?');
        $del->execute([$tenantId, $passId]);
        $ins = $this->pdo->prepare(
            'INSERT INTO personnel_pass_conditions
             (tenant_id, pass_id, condition_type, qualification_id, training_module_id, grade_id,
              threshold_value, window_days, require_validity, hour_category, session_kind, avis_kind, bilan_kind, position)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $pos = 1;
        foreach ($conditions as $c) {
            if (!is_array($c)) {
                continue;
            }
            $type = trim((string) ($c['condition_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $ins->execute([
                $tenantId,
                $passId,
                mb_substr($type, 0, 64),
                $this->nullableId($c['qualification_id'] ?? null),
                $this->nullableId($c['training_module_id'] ?? null),
                $this->nullableId($c['grade_id'] ?? null),
                (float) ($c['threshold_value'] ?? 0),
                isset($c['window_days']) && (int) $c['window_days'] > 0 ? (int) $c['window_days'] : null,
                !empty($c['require_validity']) ? 1 : 0,
                $this->nullableShort($c['hour_category'] ?? null, 80),
                $this->nullableShort($c['session_kind'] ?? null, 40),
                $this->nullableShort($c['avis_kind'] ?? null, 40),
                $this->nullableShort($c['bilan_kind'] ?? null, 40),
                $pos++,
            ]);
        }
    }

    public function hasFavorableAvis(int $tenantId, int $subjectUserId, string $avisKind, ?int $passId = null): bool
    {
        if (!$this->schemaReady()) {
            return false;
        }
        $sql = 'SELECT 1 FROM personnel_hierarchical_opinions
                WHERE tenant_id = ? AND subject_user_id = ? AND avis_kind = ? AND opinion = \'favorable\'';
        $params = [$tenantId, $subjectUserId, $avisKind];
        if ($passId !== null && $passId > 0) {
            $sql .= ' AND (pass_id IS NULL OR pass_id = ?)';
            $params[] = $passId;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (bool) $st->fetchColumn();
    }

    /**
     * @return array{average: float, count: int}
     */
    public function bilanAverage(int $tenantId, int $userId, ?string $kind = null, ?int $windowDays = null): array
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_stage_bilans' LIMIT 1"
            );
            if ($st === false || !(bool) $st->fetchColumn()) {
                return ['average' => 0.0, 'count' => 0];
            }
        } catch (\Throwable) {
            return ['average' => 0.0, 'count' => 0];
        }
        $sql = 'SELECT AVG(rating) AS avg_rating, COUNT(*) AS n
                FROM personnel_stage_bilans
                WHERE tenant_id = ? AND user_id = ? AND rating IS NOT NULL';
        $params = [$tenantId, $userId];
        if ($kind !== null && $kind !== '') {
            $sql .= ' AND kind = ?';
            $params[] = $kind;
        }
        if ($windowDays !== null && $windowDays > 0) {
            $sql .= ' AND created_at >= (NOW() - INTERVAL ? DAY)';
            $params[] = $windowDays;
        }
        $q = $this->pdo->prepare($sql);
        $q->execute($params);
        $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'average' => (float) ($row['avg_rating'] ?? 0),
            'count' => (int) ($row['n'] ?? 0),
        ];
    }

    private function normalizeCode(string $code, string $label): string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            $code = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '-', $label) ?? 'PASS');
        }
        $code = trim($code, '-_');
        if ($code === '') {
            $code = 'PASS';
        }

        return mb_substr($code, 0, 64);
    }

    private function nullableText(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }

    private function nullableShort(mixed $v, int $max): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        return mb_substr($s, 0, $max);
    }

    private function nullableId(mixed $v): ?int
    {
        $n = (int) ($v ?? 0);

        return $n > 0 ? $n : null;
    }
}
