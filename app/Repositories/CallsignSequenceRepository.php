<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CallsignSequenceRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'organization_callsign_sequences' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM organization_callsign_sequences
             WHERE tenant_id = ?
             ORDER BY is_default DESC, is_active DESC, name ASC, id ASC'
        );
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM organization_callsign_sequences WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findDefault(int $tenantId): ?array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM organization_callsign_sequences
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY is_default DESC, id ASC
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array{
     *   name: string,
     *   code: string,
     *   mode?: string,
     *   prefix?: string,
     *   suffix?: string,
     *   pattern?: string,
     *   start_number?: int,
     *   current_number?: int,
     *   increment_by?: int,
     *   padding?: int,
     *   reuse_released?: bool,
     *   allow_manual_override?: bool,
     *   unit_change_policy?: string,
     *   unit_id?: ?int,
     *   is_default?: bool,
     *   is_active?: bool
     * } $data
     */
    public function insert(int $tenantId, array $data): ?int
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return null;
        }
        $start = max(1, (int) ($data['start_number'] ?? 1));
        $st = $this->pdo()->prepare(
            'INSERT INTO organization_callsign_sequences
                (tenant_id, name, code, mode, prefix, suffix, pattern, start_number, current_number,
                 increment_by, padding, reuse_released, allow_manual_override, unit_change_policy,
                 unit_id, is_default, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $tenantId,
            trim((string) $data['name']),
            trim((string) $data['code']),
            (string) ($data['mode'] ?? 'PREFIX_NUMERIC'),
            (string) ($data['prefix'] ?? ''),
            (string) ($data['suffix'] ?? ''),
            (string) ($data['pattern'] ?? '{PREFIX}-{NUMBER:02}'),
            $start,
            max($start, (int) ($data['current_number'] ?? $start)),
            max(1, (int) ($data['increment_by'] ?? 1)),
            max(0, min(8, (int) ($data['padding'] ?? 2))),
            !empty($data['reuse_released']) ? 1 : 0,
            array_key_exists('allow_manual_override', $data) ? (!empty($data['allow_manual_override']) ? 1 : 0) : 1,
            (string) ($data['unit_change_policy'] ?? 'keep'),
            isset($data['unit_id']) && (int) $data['unit_id'] > 0 ? (int) $data['unit_id'] : null,
            !empty($data['is_default']) ? 1 : 0,
            array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
        ]);
        $id = (int) $this->pdo()->lastInsertId();

        return $id > 0 ? $id : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        if (!$this->schemaReady() || $tenantId < 1 || $id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE organization_callsign_sequences SET
                name = ?, code = ?, mode = ?, prefix = ?, suffix = ?, pattern = ?,
                start_number = ?, increment_by = ?, padding = ?, reuse_released = ?,
                allow_manual_override = ?, unit_change_policy = ?, unit_id = ?,
                is_default = ?, is_active = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([
            trim((string) $data['name']),
            trim((string) $data['code']),
            (string) ($data['mode'] ?? 'PREFIX_NUMERIC'),
            (string) ($data['prefix'] ?? ''),
            (string) ($data['suffix'] ?? ''),
            (string) ($data['pattern'] ?? '{PREFIX}-{NUMBER:02}'),
            max(1, (int) ($data['start_number'] ?? 1)),
            max(1, (int) ($data['increment_by'] ?? 1)),
            max(0, min(8, (int) ($data['padding'] ?? 2))),
            !empty($data['reuse_released']) ? 1 : 0,
            !empty($data['allow_manual_override']) ? 1 : 0,
            (string) ($data['unit_change_policy'] ?? 'keep'),
            isset($data['unit_id']) && (int) $data['unit_id'] > 0 ? (int) $data['unit_id'] : null,
            !empty($data['is_default']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
            $tenantId,
            $id,
        ]) && $st->rowCount() >= 0;
    }

    public function clearDefaultFlags(int $tenantId, ?int $exceptId = null): void
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return;
        }
        if ($exceptId !== null && $exceptId > 0) {
            $st = $this->pdo()->prepare(
                'UPDATE organization_callsign_sequences SET is_default = 0 WHERE tenant_id = ? AND id <> ?'
            );
            $st->execute([$tenantId, $exceptId]);

            return;
        }
        $st = $this->pdo()->prepare(
            'UPDATE organization_callsign_sequences SET is_default = 0 WHERE tenant_id = ?'
        );
        $st->execute([$tenantId]);
    }

    /**
     * Consomme atomiquement le prochain numéro libre (hors plages réservées).
     * Retourne [number, row] ou null.
     *
     * @return array{number: int, sequence: array<string, mixed>}|null
     */
    public function consumeNextNumber(int $tenantId, int $sequenceId): ?array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $sequenceId < 1) {
            return null;
        }
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'SELECT * FROM organization_callsign_sequences
                 WHERE tenant_id = ? AND id = ? AND is_active = 1
                 FOR UPDATE'
            );
            $st->execute([$tenantId, $sequenceId]);
            $seq = $st->fetch(PDO::FETCH_ASSOC);
            if (!is_array($seq)) {
                $pdo->rollBack();

                return null;
            }
            $increment = max(1, (int) ($seq['increment_by'] ?? 1));
            $candidate = max((int) ($seq['start_number'] ?? 1), (int) ($seq['current_number'] ?? 1));
            $reserved = $this->listReservedRangesLocked($tenantId, $sequenceId);
            $guard = 0;
            while ($guard < 10000) {
                ++$guard;
                if (!$this->numberInReservedRanges($candidate, $reserved)) {
                    break;
                }
                $candidate += $increment;
            }
            $nextCursor = $candidate + $increment;
            $upd = $pdo->prepare(
                'UPDATE organization_callsign_sequences
                 SET current_number = ?, updated_at = NOW()
                 WHERE tenant_id = ? AND id = ?'
            );
            $upd->execute([$nextCursor, $tenantId, $sequenceId]);
            $pdo->commit();
            $seq['current_number'] = $nextCursor;

            return ['number' => $candidate, 'sequence' => $seq];
        } catch (\Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return null;
        }
    }

    /** @return list<array{range_start: int, range_end: int, label: string, purpose: string}> */
    public function listReservedRanges(int $tenantId, int $sequenceId): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $sequenceId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT range_start, range_end, label, purpose FROM organization_callsign_reserved_ranges
             WHERE tenant_id = ? AND sequence_id = ?
             ORDER BY range_start ASC'
        );
        $st->execute([$tenantId, $sequenceId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? array_map(static function (array $r): array {
            return [
                'range_start' => (int) ($r['range_start'] ?? 0),
                'range_end' => (int) ($r['range_end'] ?? 0),
                'label' => (string) ($r['label'] ?? ''),
                'purpose' => (string) ($r['purpose'] ?? ''),
            ];
        }, $rows) : [];
    }

    public function replaceReservedRanges(int $tenantId, int $sequenceId, array $ranges): void
    {
        if (!$this->schemaReady() || $tenantId < 1 || $sequenceId < 1) {
            return;
        }
        $pdo = $this->pdo();
        $del = $pdo->prepare(
            'DELETE FROM organization_callsign_reserved_ranges WHERE tenant_id = ? AND sequence_id = ?'
        );
        $del->execute([$tenantId, $sequenceId]);
        $ins = $pdo->prepare(
            'INSERT INTO organization_callsign_reserved_ranges
                (tenant_id, sequence_id, label, range_start, range_end, purpose)
             VALUES (?,?,?,?,?,?)'
        );
        foreach ($ranges as $range) {
            if (!is_array($range)) {
                continue;
            }
            $start = (int) ($range['range_start'] ?? 0);
            $end = (int) ($range['range_end'] ?? 0);
            if ($start < 1 || $end < $start) {
                continue;
            }
            $ins->execute([
                $tenantId,
                $sequenceId,
                trim((string) ($range['label'] ?? 'Réservé')) ?: 'Réservé',
                $start,
                $end,
                trim((string) ($range['purpose'] ?? 'command')) ?: 'command',
            ]);
        }
    }

    public function isForbidden(int $tenantId, string $callsign): bool
    {
        if (!$this->schemaReady() || $tenantId < 1 || trim($callsign) === '') {
            return false;
        }
        $st = $this->pdo()->prepare(
            'SELECT 1 FROM organization_callsign_forbidden
             WHERE tenant_id = ? AND LOWER(callsign) = LOWER(?)
             LIMIT 1'
        );
        $st->execute([$tenantId, trim($callsign)]);

        return (bool) $st->fetchColumn();
    }

    public function appendHistory(
        int $tenantId,
        int $userId,
        ?int $sequenceId,
        ?string $oldCallsign,
        string $newCallsign,
        string $reason,
        ?int $changedBy,
        ?string $metadataJson = null
    ): ?int {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1 || trim($newCallsign) === '') {
            return null;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO personnel_callsign_history
                (tenant_id, user_id, sequence_id, old_callsign, new_callsign, reason, changed_by, metadata, changed_at)
             VALUES (?,?,?,?,?,?,?,?,NOW())'
        );
        $st->execute([
            $tenantId,
            $userId,
            $sequenceId,
            $oldCallsign !== null && trim($oldCallsign) !== '' ? trim($oldCallsign) : null,
            trim($newCallsign),
            trim($reason) !== '' ? trim($reason) : 'Changement d’indicatif',
            $changedBy,
            $metadataJson,
        ]);
        $id = (int) $this->pdo()->lastInsertId();

        return $id > 0 ? $id : null;
    }

    /** @return list<array<string, mixed>> */
    public function listHistoryForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if (!$this->schemaReady() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM personnel_callsign_history
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY changed_at DESC, id DESC
             LIMIT ' . max(1, min(200, $limit))
        );
        $st->execute([$tenantId, $userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array{range_start: int, range_end: int}> */
    private function listReservedRangesLocked(int $tenantId, int $sequenceId): array
    {
        $st = $this->pdo()->prepare(
            'SELECT range_start, range_end FROM organization_callsign_reserved_ranges
             WHERE tenant_id = ? AND sequence_id = ?
             FOR UPDATE'
        );
        $st->execute([$tenantId, $sequenceId]);
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'range_start' => (int) ($row['range_start'] ?? 0),
                'range_end' => (int) ($row['range_end'] ?? 0),
            ];
        }

        return $out;
    }

    /** @param list<array{range_start: int, range_end: int}> $ranges */
    private function numberInReservedRanges(int $number, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if ($number >= $range['range_start'] && $number <= $range['range_end']) {
                return true;
            }
        }

        return false;
    }
}
