<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\AtakUnitMotionSchema;
use App\Support\LazyDatabaseConnection;
use PDO;

final class AtakUnitAssignmentRepository
{
    use LazyDatabaseConnection;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_DETACHED = 'detached';

    public const MODE_DIRECT = 'DIRECT';
    public const MODE_INTERCEPT = 'INTERCEPT';

    public const DEST_MARKER = 'marker';
    public const DEST_ARMA_MARKER = 'arma_marker';
    public const DEST_WAYPOINT = 'waypoint';
    public const DEST_CUSTOM = 'custom';
    public const DEST_UNIT = 'unit';
    public const DEST_LZ = 'lz';
    public const DEST_HLZ = 'hlz';
    public const DEST_RP = 'rp';
    public const DEST_ORP = 'orp';
    public const DEST_CHECKPOINT = 'checkpoint';
    public const DEST_TARGET = 'target';

    public function __construct(?PDO $pdo = null)
    {
        AtakUnitMotionSchema::ensure();
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function create(int $tenantId, int $mapId, array $data): ?array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return null;
        }
        $unitKind = $this->kind($data['unit_kind'] ?? 'ground');
        $unitRef = trim((string) ($data['unit_ref'] ?? ''));
        if ($unitRef === '') {
            return null;
        }
        $destType = $this->destType($data['destination_type'] ?? self::DEST_CUSTOM);
        $mode = strtoupper(trim((string) ($data['assignment_mode'] ?? self::MODE_DIRECT)));
        if ($mode !== self::MODE_INTERCEPT) {
            $mode = self::MODE_DIRECT;
        }

        $this->closeActive($tenantId, $mapId, $unitKind, $unitRef, self::STATUS_DETACHED);

        $this->pdo()->prepare(
            'INSERT INTO atak_unit_assignments (
                tenant_id, map_id, unit_kind, unit_id, unit_ref,
                destination_type, destination_id, destination_label, destination_x, destination_y,
                assignment_mode, status, assigned_by, assigned_by_label, assigned_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $tenantId,
            $mapId,
            $unitKind,
            isset($data['unit_id']) && (int) $data['unit_id'] > 0 ? (int) $data['unit_id'] : null,
            $unitRef,
            $destType,
            $this->nullableString($data['destination_id'] ?? null, 64),
            $this->nullableString($data['destination_label'] ?? null, 160),
            $this->nullableFloat($data['destination_x'] ?? null),
            $this->nullableFloat($data['destination_y'] ?? null),
            $mode,
            self::STATUS_ACTIVE,
            isset($data['assigned_by']) && (int) $data['assigned_by'] > 0 ? (int) $data['assigned_by'] : null,
            $this->nullableString($data['assigned_by_label'] ?? null, 120),
        ]);

        $id = (int) $this->pdo()->lastInsertId();

        return $this->find($tenantId, $id);
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>|null
     */
    public function update(int $tenantId, int $id, array $patch): ?array
    {
        $row = $this->find($tenantId, $id);
        if ($row === null) {
            return null;
        }
        $fields = [];
        $params = [];
        $map = [
            'destination_type' => 's',
            'destination_id' => 's',
            'destination_label' => 's',
            'destination_x' => 'f',
            'destination_y' => 'f',
            'assignment_mode' => 's',
            'status' => 's',
        ];
        foreach ($map as $key => $kind) {
            if (!array_key_exists($key, $patch)) {
                continue;
            }
            $fields[] = "`{$key}` = ?";
            if ($kind === 'f') {
                $params[] = $this->nullableFloat($patch[$key]);
            } else {
                $val = $this->nullableString($patch[$key], $key === 'destination_label' ? 160 : 64);
                if ($key === 'destination_type' && $val !== null) {
                    $val = $this->destType($val);
                }
                if ($key === 'assignment_mode' && $val !== null) {
                    $val = strtoupper($val) === self::MODE_INTERCEPT ? self::MODE_INTERCEPT : self::MODE_DIRECT;
                }
                $params[] = $val;
            }
        }
        if ($fields === []) {
            return $row;
        }
        $params[] = $id;
        $params[] = $tenantId;
        $this->pdo()->prepare(
            'UPDATE atak_unit_assignments SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?'
        )->execute($params);

        return $this->find($tenantId, $id);
    }

    public function markArrived(int $tenantId, int $id): ?array
    {
        $this->pdo()->prepare(
            "UPDATE atak_unit_assignments
             SET status = ?, arrived_at = COALESCE(arrived_at, NOW()), closed_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = ?"
        )->execute([self::STATUS_ARRIVED, $id, $tenantId, self::STATUS_ACTIVE]);

        return $this->find($tenantId, $id);
    }

    public function detach(int $tenantId, int $id): ?array
    {
        $this->pdo()->prepare(
            "UPDATE atak_unit_assignments
             SET status = ?, closed_at = NOW()
             WHERE id = ? AND tenant_id = ? AND status = ?"
        )->execute([self::STATUS_DETACHED, $id, $tenantId, self::STATUS_ACTIVE]);

        return $this->find($tenantId, $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $id): ?array
    {
        if ($tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_unit_assignments WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(int $tenantId, int $mapId): array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_unit_assignments
             WHERE tenant_id = ? AND map_id = ? AND status = ?
             ORDER BY assigned_at ASC'
        );
        $st->execute([$tenantId, $mapId, self::STATUS_ACTIVE]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveForUnit(int $tenantId, int $mapId, string $unitKind, string $unitRef): ?array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_unit_assignments
             WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ? AND status = ?
             ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$tenantId, $mapId, $this->kind($unitKind), $unitRef, self::STATUS_ACTIVE]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByDestination(int $tenantId, int $mapId, string $destinationType, string $destinationId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT * FROM atak_unit_assignments
             WHERE tenant_id = ? AND map_id = ? AND destination_type = ? AND destination_id = ? AND status = ?
             ORDER BY assigned_at ASC'
        );
        $st->execute([$tenantId, $mapId, $this->destType($destinationType), $destinationId, self::STATUS_ACTIVE]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function closeActive(int $tenantId, int $mapId, string $unitKind, string $unitRef, string $status = self::STATUS_DETACHED): void
    {
        $this->pdo()->prepare(
            "UPDATE atak_unit_assignments
             SET status = ?, closed_at = NOW()
             WHERE tenant_id = ? AND map_id = ? AND unit_kind = ? AND unit_ref = ? AND status = ?"
        )->execute([
            $status === self::STATUS_ARRIVED ? self::STATUS_ARRIVED : self::STATUS_DETACHED,
            $tenantId,
            $mapId,
            $this->kind($unitKind),
            $unitRef,
            self::STATUS_ACTIVE,
        ]);
    }

    private function kind(mixed $raw): string
    {
        $k = strtolower(trim((string) $raw));

        return $k === 'air' ? 'air' : 'ground';
    }

    private function destType(mixed $raw): string
    {
        $t = strtolower(trim((string) $raw));
        $ok = [
            self::DEST_MARKER, self::DEST_ARMA_MARKER, self::DEST_WAYPOINT, self::DEST_CUSTOM,
            self::DEST_UNIT, self::DEST_LZ, self::DEST_HLZ, self::DEST_RP, self::DEST_ORP,
            self::DEST_CHECKPOINT, self::DEST_TARGET,
        ];

        return in_array($t, $ok, true) ? $t : self::DEST_CUSTOM;
    }

    private function nullableString(mixed $raw, int $max): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = trim((string) $raw);
        if ($s === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, $max);
        }

        return substr($s, 0, $max);
    }

    private function nullableFloat(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (!is_numeric($raw)) {
            return null;
        }
        $f = (float) $raw;

        return is_finite($f) ? $f : null;
    }
}
