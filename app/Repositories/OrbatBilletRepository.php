<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Postes ORBAT (billets) indépendants des personnels.
 * Schéma de base : orbat_billets / orbat_billet_holders (lot 2).
 */
final class OrbatBilletRepository
{
    private ?PDO $pdo = null;

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::connection();
    }

    public function schemaReady(): bool
    {
        return $this->tableExists('orbat_billets') && $this->tableExists('orbat_billet_holders');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUnit(int $tenantId, int $unitId, bool $includeArchived = false): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $unitId < 1) {
            return [];
        }
        $sql = 'SELECT b.* FROM orbat_billets b WHERE b.tenant_id = ? AND b.unit_id = ?';
        if (!$includeArchived) {
            if ($this->columnExists('orbat_billets', 'status')) {
                $sql .= " AND COALESCE(b.status, 'active') <> 'deleted'";
                if ($this->columnExists('orbat_billets', 'archived_at')) {
                    $sql .= ' AND b.archived_at IS NULL';
                }
            } else {
                $sql .= ' AND b.is_active = 1';
            }
        }
        $sql .= $this->columnExists('orbat_billets', 'sort_order')
            ? ' ORDER BY b.sort_order ASC, b.title ASC'
            : ' ORDER BY b.title ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute([$tenantId, $unitId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $tenantId, int $billetId): ?array
    {
        if (!$this->schemaReady() || $billetId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM orbat_billets WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $billetId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $unitId = (int) ($data['unit_id'] ?? 0);
        $code = mb_substr(trim((string) ($data['code'] ?? '')), 0, 64);
        $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 150);
        if ($unitId < 1 || $code === '' || $title === '') {
            return 0;
        }
        $slots = max(1, (int) ($data['authorized_slots'] ?? 1));

        $cols = ['tenant_id', 'unit_id', 'code', 'title', 'authorized_slots', 'is_active'];
        $vals = [$tenantId, $unitId, $code, $title, $slots, isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : 1];

        $map = [
            'job_role_id' => isset($data['job_role_id']) && (int) $data['job_role_id'] > 0 ? (int) $data['job_role_id'] : null,
            'min_grade_id' => isset($data['min_grade_id']) && (int) $data['min_grade_id'] > 0 ? (int) $data['min_grade_id'] : null,
            'required_pack_id' => isset($data['required_pack_id']) && (int) $data['required_pack_id'] > 0 ? (int) $data['required_pack_id'] : null,
            'is_critical' => !empty($data['is_critical']) ? 1 : 0,
            'stable_code' => $this->newUuid(),
            'org_callsign' => ($t = trim((string) ($data['org_callsign'] ?? ''))) === '' ? null : mb_substr($t, 0, 80),
            'function_label' => ($t = trim((string) ($data['function_label'] ?? ''))) === '' ? null : mb_substr($t, 0, 150),
            'status' => in_array((string) ($data['status'] ?? 'active'), ['active', 'frozen', 'deleted'], true)
                ? (string) $data['status'] : 'active',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_key_post' => !empty($data['is_key_post']) ? 1 : 0,
            'key_post_kind' => ($t = trim((string) ($data['key_post_kind'] ?? ''))) === '' ? null : mb_substr($t, 0, 40),
            'notes' => ($t = trim((string) ($data['notes'] ?? ''))) === '' ? null : $t,
            'effective_from' => ($t = trim((string) ($data['effective_from'] ?? ''))) === '' ? null : $t,
            'effective_to' => ($t = trim((string) ($data['effective_to'] ?? ''))) === '' ? null : $t,
        ];
        foreach ($map as $col => $val) {
            if (!$this->columnExists('orbat_billets', $col)) {
                continue;
            }
            if (!array_key_exists($col, $data) && !in_array($col, ['stable_code', 'status', 'sort_order', 'is_critical', 'is_key_post'], true)) {
                continue;
            }
            $cols[] = $col;
            $vals[] = $val;
        }

        $sql = 'INSERT INTO orbat_billets (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->pdo()->prepare($sql)->execute($vals);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $billetId, array $data): bool
    {
        if ($this->findById($tenantId, $billetId) === null) {
            return false;
        }
        $allowed = [
            'unit_id', 'code', 'title', 'authorized_slots', 'job_role_id', 'min_grade_id',
            'required_pack_id', 'is_critical', 'is_active', 'org_callsign', 'function_label',
            'status', 'sort_order', 'is_key_post', 'key_post_kind', 'notes',
            'effective_from', 'effective_to', 'archived_at',
        ];
        $fields = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data) || !$this->columnExists('orbat_billets', $col)) {
                continue;
            }
            $fields[] = $col . ' = ?';
            $v = $data[$col];
            if (in_array($col, ['unit_id', 'authorized_slots', 'sort_order'], true)) {
                $params[] = (int) $v;
            } elseif (in_array($col, ['job_role_id', 'min_grade_id', 'required_pack_id'], true)) {
                $params[] = ($v === null || $v === '' || (int) $v < 1) ? null : (int) $v;
            } elseif (in_array($col, ['is_critical', 'is_active', 'is_key_post'], true)) {
                $params[] = !empty($v) ? 1 : 0;
            } else {
                $t = $v === null ? null : trim((string) $v);
                $params[] = ($t === null || $t === '') ? null : $t;
            }
        }
        if ($fields === []) {
            return true;
        }
        $params[] = $billetId;
        $params[] = $tenantId;

        return $this->pdo()->prepare(
            'UPDATE orbat_billets SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?'
        )->execute($params);
    }

    public function softDelete(int $tenantId, int $billetId): bool
    {
        $data = ['is_active' => 0];
        if ($this->columnExists('orbat_billets', 'status')) {
            $data['status'] = 'deleted';
        }
        if ($this->columnExists('orbat_billets', 'archived_at')) {
            $data['archived_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($tenantId, $billetId, $data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeHolders(int $tenantId, int $billetId, ?string $asOfDate = null): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $asOf = $asOfDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $asOfDate)
            ? substr($asOfDate, 0, 10)
            : date('Y-m-d');
        $st = $this->pdo()->prepare(
            'SELECT h.*, u.display_name, u.callsign, u.email
             FROM orbat_billet_holders h
             LEFT JOIN users u ON u.id = h.user_id
             WHERE h.tenant_id = ? AND h.billet_id = ?
               AND h.starts_at <= ?
               AND (h.ends_at IS NULL OR h.ends_at >= ?)
             ORDER BY FIELD(h.holder_role, \'PRIMARY\', \'ALTERNATE\'), h.starts_at ASC'
        );
        $st->execute([$tenantId, $billetId, $asOf, $asOf]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignHolder(int $tenantId, int $billetId, array $data): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }
        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId < 1 || $billetId < 1) {
            return 0;
        }
        $role = strtoupper(trim((string) ($data['holder_role'] ?? 'PRIMARY')));
        if (!in_array($role, ['PRIMARY', 'ALTERNATE'], true)) {
            $role = 'PRIMARY';
        }
        $occupancy = strtolower(trim((string) ($data['occupancy_type'] ?? 'primary')));
        if (!in_array($occupancy, ['primary', 'acting', 'deputy', 'alternate'], true)) {
            $occupancy = $role === 'ALTERNATE' ? 'alternate' : 'primary';
        }
        $starts = trim((string) ($data['starts_at'] ?? date('Y-m-d')));
        $starts = preg_match('/^\d{4}-\d{2}-\d{2}/', $starts) ? substr($starts, 0, 10) : date('Y-m-d');
        $endsRaw = trim((string) ($data['ends_at'] ?? ''));
        $ends = preg_match('/^\d{4}-\d{2}-\d{2}/', $endsRaw) ? substr($endsRaw, 0, 10) : null;

        $cols = ['tenant_id', 'billet_id', 'user_id', 'holder_role', 'starts_at', 'ends_at'];
        $vals = [$tenantId, $billetId, $userId, $role, $starts, $ends];
        $optional = [
            'occupancy_type' => $occupancy,
            'movement_reason' => ($t = trim((string) ($data['movement_reason'] ?? ''))) === '' ? null : mb_substr($t, 0, 64),
            'notes' => ($t = trim((string) ($data['notes'] ?? ''))) === '' ? null : mb_substr($t, 0, 500),
            'effective_at' => ($t = trim((string) ($data['effective_at'] ?? ''))) === '' ? null : $t,
            'created_by' => isset($data['created_by']) && (int) $data['created_by'] > 0 ? (int) $data['created_by'] : null,
            'keeps_organic_billet' => !empty($data['keeps_organic_billet']) ? 1 : 0,
            'organic_billet_id' => isset($data['organic_billet_id']) && (int) $data['organic_billet_id'] > 0
                ? (int) $data['organic_billet_id'] : null,
        ];
        foreach ($optional as $col => $val) {
            if ($this->columnExists('orbat_billet_holders', $col)) {
                $cols[] = $col;
                $vals[] = $val;
            }
        }
        $sql = 'INSERT INTO orbat_billet_holders (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->pdo()->prepare($sql)->execute($vals);

        return (int) $this->pdo()->lastInsertId();
    }

    public function endHolder(int $tenantId, int $holderId, ?string $endsAt = null): bool
    {
        $ends = $endsAt !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $endsAt)
            ? substr($endsAt, 0, 10)
            : date('Y-m-d');
        $st = $this->pdo()->prepare(
            'UPDATE orbat_billet_holders SET ends_at = ?
             WHERE id = ? AND tenant_id = ? AND (ends_at IS NULL OR ends_at > ?)'
        );

        return $st->execute([$ends, $holderId, $tenantId, $ends]);
    }

    /**
     * @return array{authorized: int, filled: int, vacant: int, billets: list<array<string, mixed>>}
     */
    public function manningForUnit(int $tenantId, int $unitId, ?string $asOfDate = null): array
    {
        $billets = $this->listForUnit($tenantId, $unitId, false);
        $asOf = $asOfDate ?? date('Y-m-d');
        $authorized = 0;
        $filled = 0;
        $out = [];
        foreach ($billets as $b) {
            $status = strtolower((string) ($b['status'] ?? 'active'));
            if ($status === 'deleted' || (int) ($b['is_active'] ?? 1) !== 1) {
                continue;
            }
            $effFrom = trim((string) ($b['effective_from'] ?? ''));
            $effTo = trim((string) ($b['effective_to'] ?? ''));
            if ($effFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $effFrom) && substr($effFrom, 0, 10) > $asOf) {
                continue;
            }
            if ($effTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $effTo) && substr($effTo, 0, 10) < $asOf) {
                continue;
            }
            $slots = max(1, (int) ($b['authorized_slots'] ?? 1));
            $holders = $this->activeHolders($tenantId, (int) $b['id'], $asOf);
            $seatFilled = 0;
            foreach ($holders as $h) {
                $occ = strtolower((string) ($h['occupancy_type'] ?? ''));
                $role = strtoupper((string) ($h['holder_role'] ?? 'PRIMARY'));
                if ($occ === '') {
                    $occ = $role === 'ALTERNATE' ? 'alternate' : 'primary';
                }
                if (in_array($occ, ['primary', 'acting'], true)) {
                    ++$seatFilled;
                }
            }
            $seatFilled = min($slots, $seatFilled);
            $vacant = $status === 'frozen' ? 0 : max(0, $slots - $seatFilled);
            $authorized += $slots;
            $filled += $seatFilled;
            $out[] = [
                'id' => (int) $b['id'],
                'code' => (string) ($b['code'] ?? ''),
                'title' => (string) ($b['title'] ?? ''),
                'org_callsign' => (string) ($b['org_callsign'] ?? ''),
                'function_label' => (string) ($b['function_label'] ?? ''),
                'status' => $status,
                'authorized' => $slots,
                'filled' => $seatFilled,
                'vacant' => $vacant,
                'is_critical' => !empty($b['is_critical']),
                'is_key_post' => !empty($b['is_key_post']),
                'key_post_kind' => (string) ($b['key_post_kind'] ?? ''),
                'required_pack_id' => isset($b['required_pack_id']) ? (int) $b['required_pack_id'] : null,
                'holders' => array_map(static function (array $h): array {
                    $label = trim((string) ($h['display_name'] ?? ''));
                    if ($label === '') {
                        $label = trim((string) ($h['callsign'] ?? ''));
                    }
                    if ($label === '') {
                        $label = '#' . (int) ($h['user_id'] ?? 0);
                    }
                    $occ = strtolower((string) ($h['occupancy_type'] ?? ''));
                    if ($occ === '') {
                        $occ = strtoupper((string) ($h['holder_role'] ?? '')) === 'ALTERNATE' ? 'alternate' : 'primary';
                    }

                    return [
                        'id' => (int) ($h['id'] ?? 0),
                        'user_id' => (int) ($h['user_id'] ?? 0),
                        'label' => $label,
                        'occupancy_type' => $occ,
                        'holder_role' => (string) ($h['holder_role'] ?? 'PRIMARY'),
                        'starts_at' => (string) ($h['starts_at'] ?? ''),
                        'ends_at' => $h['ends_at'] ?? null,
                        'keeps_organic_billet' => !empty($h['keeps_organic_billet']),
                        'organic_billet_id' => isset($h['organic_billet_id']) ? (int) $h['organic_billet_id'] : null,
                    ];
                }, $holders),
            ];
        }

        return [
            'authorized' => $authorized,
            'filled' => $filled,
            'vacant' => max(0, $authorized - $filled),
            'billets' => $out,
        ];
    }

    /**
     * @return array<int, array{authorized: int, filled: int, vacant: int, billets: list<array<string, mixed>>}>
     */
    public function manningByUnitForTenant(int $tenantId, ?string $asOfDate = null): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT DISTINCT unit_id FROM orbat_billets WHERE tenant_id = ? AND is_active = 1'
        );
        $st->execute([$tenantId]);
        $map = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($row['unit_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $m = $this->manningForUnit($tenantId, $uid, $asOfDate);
            $map[$uid] = [
                'authorized' => $m['authorized'],
                'filled' => $m['filled'],
                'vacant' => $m['vacant'],
                'billets' => $m['billets'],
            ];
        }

        return $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function primaryBilletsForUser(int $tenantId, int $userId, ?string $asOfDate = null): array
    {
        if (!$this->schemaReady() || $userId < 1) {
            return [];
        }
        $asOf = $asOfDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}/', $asOfDate)
            ? substr($asOfDate, 0, 10)
            : date('Y-m-d');
        $st = $this->pdo()->prepare(
            "SELECT h.*, b.title AS billet_title, b.code AS billet_code, b.unit_id, b.org_callsign
             FROM orbat_billet_holders h
             INNER JOIN orbat_billets b ON b.id = h.billet_id AND b.tenant_id = h.tenant_id
             WHERE h.tenant_id = ? AND h.user_id = ?
               AND h.starts_at <= ? AND (h.ends_at IS NULL OR h.ends_at >= ?)
               AND b.is_active = 1
               AND (
                    COALESCE(h.occupancy_type, '') IN ('', 'primary')
                    OR (h.occupancy_type IS NULL AND h.holder_role = 'PRIMARY')
               )
             ORDER BY h.starts_at DESC"
        );
        $st->execute([$tenantId, $userId, $asOf, $asOf]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function newUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        $st = $this->pdo()->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);
        $cache[$table] = (bool) $st->fetchColumn();

        return $cache[$table];
    }

    private function columnExists(string $table, string $column): bool
    {
        $st = $this->pdo()->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }
}
