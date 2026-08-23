<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Charges ACE (minuterie ou déclenchement à la demande) pour l’ATAK web.
 */
class AtakExplosiveTimerRepository
{
    use LazyDatabaseConnection;

    private const STATUSES = ['armed', 'detonated', 'defused'];

    private const TRIGGER_KINDS = ['timer', 'clacker', 'cellphone', 'command'];

    private ?bool $tablesReady = null;

    private ?bool $commandColumnsReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tablesReady(): bool
    {
        if ($this->tablesReady !== null) {
            return $this->tablesReady;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_explosive_timers' LIMIT 1"
            );
            $this->tablesReady = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $this->tablesReady = false;
        }

        return $this->tablesReady;
    }

    public function commandColumnsReady(): bool
    {
        if ($this->commandColumnsReady !== null) {
            return $this->commandColumnsReady;
        }
        if (!$this->tablesReady()) {
            $this->commandColumnsReady = false;

            return false;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'atak_explosive_timers'
                   AND COLUMN_NAME = 'detonate_requested_at' LIMIT 1"
            );
            $this->commandColumnsReady = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $this->commandColumnsReady = false;
        }

        return $this->commandColumnsReady;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForMap(int $tenantId, int $mapId, int $recentMinutes = 30): array
    {
        if (!$this->tablesReady()) {
            return [];
        }
        $recentMinutes = max(5, min(240, $recentMinutes));
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM atak_explosive_timers
             WHERE tenant_id = :tenant_id AND map_id = :map_id
               AND (status = 'armed' OR updated_at >= (NOW() - INTERVAL {$recentMinutes} MINUTE))
             ORDER BY CASE status WHEN 'armed' THEN 0 ELSE 1 END,
                      detonates_at IS NULL, detonates_at ASC, id ASC"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'map_id' => $mapId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $now = time();
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->present($row, $now);
        }

        return $out;
    }

    /**
     * @return list<array{id:int,charge_id:string,requested_by:string}>
     */
    public function listPendingDetonations(int $tenantId, int $mapId): array
    {
        if (!$this->commandColumnsReady()) {
            return [];
        }
        $stmt = $this->pdo()->prepare(
            "SELECT id, charge_id, detonate_requested_by
             FROM atak_explosive_timers
             WHERE tenant_id = :tenant_id AND map_id = :map_id
               AND status = 'armed'
               AND detonate_requested_at IS NOT NULL
               AND detonate_ack_at IS NULL
             ORDER BY detonate_requested_at ASC, id ASC
             LIMIT 40"
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'map_id' => $mapId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $chargeId = trim((string) ($row['charge_id'] ?? ''));
            if ($chargeId === '') {
                continue;
            }
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'charge_id' => $chargeId,
                'requested_by' => (string) ($row['detonate_requested_by'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public function upsert(int $tenantId, int $mapId, array $data): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }
        $chargeId = trim((string) ($data['charge_id'] ?? ''));
        if ($chargeId === '' || strlen($chargeId) > 96) {
            return null;
        }
        $status = $this->normalizeStatus((string) ($data['status'] ?? 'armed'));
        $existing = $this->findByCharge($tenantId, $mapId, $chargeId);
        $cmd = $this->commandColumnsReady();

        if ($status !== 'armed') {
            if ($existing === null) {
                return null;
            }
            if (($existing['status'] ?? '') === 'armed') {
                $ackSql = $cmd ? ', detonate_ack_at = IFNULL(detonate_ack_at, NOW())' : '';
                $this->pdo()->prepare(
                    "UPDATE atak_explosive_timers
                     SET status = :status, ended_at = NOW(){$ackSql}
                     WHERE id = :id"
                )->execute([
                    'status' => $status,
                    'id' => (int) $existing['id'],
                ]);
            }

            return $this->findById((int) $existing['id']);
        }

        $kind = $this->normalizeTriggerKind((string) ($data['trigger_kind'] ?? $data['triggerKind'] ?? ''));
        $fuse = (int) ($data['fuse_seconds'] ?? $data['timer_seconds'] ?? 0);
        if ($kind === '' && $existing !== null) {
            $kind = $this->normalizeTriggerKind((string) ($existing['trigger_kind'] ?? ''));
        }
        $fuse = max(0, min(86400, $fuse));
        if ($kind === '') {
            $kind = $fuse >= 5 ? 'timer' : 'command';
        }
        if ($kind === 'timer' && $fuse < 1) {
            $kind = 'command';
        }

        $author = mb_substr(trim((string) ($data['author'] ?? '')), 0, 120);
        $label = mb_substr(trim((string) ($data['magazine_label'] ?? $data['label'] ?? '')), 0, 160);
        $grid = mb_substr(trim((string) ($data['grid'] ?? $data['grid_ref'] ?? '')), 0, 48);
        $posX = (float) ($data['pos_x'] ?? 0);
        $posY = (float) ($data['pos_y'] ?? 0);
        $detonateExpr = $fuse < 1
            ? 'NULL'
            : "DATE_ADD(NOW(), INTERVAL {$fuse} SECOND)";

        if ($existing !== null) {
            $keepCountdown = $kind === 'timer'
                && $fuse >= 1
                && $this->normalizeTriggerKind((string) ($existing['trigger_kind'] ?? '')) === 'timer'
                && (int) ($existing['fuse_seconds'] ?? 0) === $fuse
                && ($existing['status'] ?? '') === 'armed'
                && trim((string) ($existing['detonates_at'] ?? '')) !== '';
            $updateDetonateExpr = $keepCountdown ? 'detonates_at' : $detonateExpr;
            $params = [
                'author' => $author !== '' ? $author : (string) ($existing['author'] ?? ''),
                'magazine_label' => $label !== '' ? $label : (string) ($existing['magazine_label'] ?? ''),
                'grid_ref' => $grid !== '' ? $grid : (string) ($existing['grid_ref'] ?? ''),
                'pos_x' => $posX,
                'pos_y' => $posY,
                'fuse_seconds' => $fuse,
                'id' => (int) $existing['id'],
            ];
            $kindSql = '';
            if ($cmd) {
                $kindSql = 'trigger_kind = :trigger_kind,';
                $params['trigger_kind'] = $kind;
            }
            $this->pdo()->prepare(
                "UPDATE atak_explosive_timers SET
                    author = :author,
                    magazine_label = :magazine_label,
                    grid_ref = :grid_ref,
                    pos_x = :pos_x,
                    pos_y = :pos_y,
                    fuse_seconds = :fuse_seconds,
                    {$kindSql}
                    status = 'armed',
                    started_at = IF(status = 'armed', started_at, NOW()),
                    detonates_at = {$updateDetonateExpr},
                    ended_at = NULL
                 WHERE id = :id"
            )->execute($params);

            return $this->findById((int) $existing['id']);
        }

        if ($cmd) {
            $this->pdo()->prepare(
                "INSERT INTO atak_explosive_timers (
                    tenant_id, map_id, charge_id, author, magazine_label, grid_ref,
                    pos_x, pos_y, fuse_seconds, trigger_kind, status, started_at, detonates_at
                ) VALUES (
                    :tenant_id, :map_id, :charge_id, :author, :magazine_label, :grid_ref,
                    :pos_x, :pos_y, :fuse_seconds, :trigger_kind, 'armed', NOW(), {$detonateExpr}
                )"
            )->execute([
                'tenant_id' => $tenantId,
                'map_id' => $mapId,
                'charge_id' => $chargeId,
                'author' => $author,
                'magazine_label' => $label,
                'grid_ref' => $grid,
                'pos_x' => $posX,
                'pos_y' => $posY,
                'fuse_seconds' => $fuse,
                'trigger_kind' => $kind,
            ]);
        } else {
            $legacyFuse = $fuse;
            // Ancien schéma : detonates_at parfois NOT NULL — ne jamais inventer 1 s.
            $legacyDetonate = $legacyFuse >= 1
                ? "DATE_ADD(NOW(), INTERVAL {$legacyFuse} SECOND)"
                : 'NOW()';
            $this->pdo()->prepare(
                "INSERT INTO atak_explosive_timers (
                    tenant_id, map_id, charge_id, author, magazine_label, grid_ref,
                    pos_x, pos_y, fuse_seconds, status, started_at, detonates_at
                ) VALUES (
                    :tenant_id, :map_id, :charge_id, :author, :magazine_label, :grid_ref,
                    :pos_x, :pos_y, :fuse_seconds, 'armed', NOW(), {$legacyDetonate}
                )"
            )->execute([
                'tenant_id' => $tenantId,
                'map_id' => $mapId,
                'charge_id' => $chargeId,
                'author' => $author,
                'magazine_label' => $label,
                'grid_ref' => $grid,
                'pos_x' => $posX,
                'pos_y' => $posY,
                'fuse_seconds' => $legacyFuse,
            ]);
        }
        $id = (int) $this->pdo()->lastInsertId();

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function requestDetonate(int $tenantId, int $mapId, int $id, string $by): ?array
    {
        if (!$this->commandColumnsReady() || $id < 1) {
            return null;
        }
        $row = $this->findRawById($id);
        if ($row === null) {
            return null;
        }
        if ((int) ($row['tenant_id'] ?? 0) !== $tenantId || (int) ($row['map_id'] ?? 0) !== $mapId) {
            return null;
        }
        if ($this->normalizeStatus((string) ($row['status'] ?? '')) !== 'armed') {
            return null;
        }
        $by = mb_substr(trim($by), 0, 120);
        $already = trim((string) ($row['detonate_requested_at'] ?? '')) !== ''
            && trim((string) ($row['detonate_ack_at'] ?? '')) === '';
        if (!$already) {
            $this->pdo()->prepare(
                "UPDATE atak_explosive_timers
                 SET detonate_requested_at = NOW(),
                     detonate_requested_by = :by,
                     detonate_ack_at = NULL
                 WHERE id = :id AND status = 'armed'"
            )->execute([
                'by' => $by,
                'id' => $id,
            ]);
        }

        return $this->findById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByCharge(int $tenantId, int $mapId, string $chargeId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM atak_explosive_timers WHERE tenant_id = ? AND map_id = ? AND charge_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $mapId, $chargeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRawById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_explosive_timers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findById(int $id): ?array
    {
        $row = $this->findRawById($id);
        if ($row === null) {
            return null;
        }

        return $this->present($row, time());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row, int $now): array
    {
        $status = $this->normalizeStatus((string) ($row['status'] ?? 'armed'));
        $fuse = (int) ($row['fuse_seconds'] ?? 0);
        $kind = $this->normalizeTriggerKind((string) ($row['trigger_kind'] ?? ''));
        if ($kind === '') {
            $kind = $fuse >= 5 ? 'timer' : 'command';
        }
        $detonatesAt = (string) ($row['detonates_at'] ?? '');
        $hasCountdown = $status === 'armed' && $kind === 'timer' && $fuse >= 1 && $detonatesAt !== '';
        $remaining = 0;
        if ($hasCountdown) {
            $ts = strtotime($detonatesAt);
            if ($ts !== false) {
                $remaining = max(0, $ts - $now);
            }
        }
        $pending = $status === 'armed'
            && trim((string) ($row['detonate_requested_at'] ?? '')) !== ''
            && trim((string) ($row['detonate_ack_at'] ?? '')) === '';

        return [
            'id' => (int) ($row['id'] ?? 0),
            'charge_id' => (string) ($row['charge_id'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'magazine_label' => (string) ($row['magazine_label'] ?? ''),
            'grid_ref' => (string) ($row['grid_ref'] ?? ''),
            'pos_x' => isset($row['pos_x']) ? (float) $row['pos_x'] : null,
            'pos_y' => isset($row['pos_y']) ? (float) $row['pos_y'] : null,
            'fuse_seconds' => $fuse,
            'trigger_kind' => $kind,
            'trigger_label' => $this->triggerLabelFr($kind),
            'status' => $status,
            'status_label' => $this->statusLabelFr($status, $pending),
            'started_at' => (string) ($row['started_at'] ?? ''),
            'detonates_at' => $detonatesAt,
            'ended_at' => $row['ended_at'] ?? null,
            'remaining_seconds' => $hasCountdown ? $remaining : null,
            'has_countdown' => $hasCountdown,
            'detonate_pending' => $pending,
            'detonate_requested_by' => (string) ($row['detonate_requested_by'] ?? ''),
            'server_now' => $now,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $k = strtolower(trim($status));
        if ($k === 'exploded' || $k === 'detonate') {
            $k = 'detonated';
        }
        if ($k === 'disarmed' || $k === 'cleared') {
            $k = 'defused';
        }

        return in_array($k, self::STATUSES, true) ? $k : 'armed';
    }

    private function normalizeTriggerKind(string $kind): string
    {
        $k = strtolower(trim($kind));
        if ($k === 'cell' || $k === 'phone') {
            $k = 'cellphone';
        }
        if ($k === 'm57' || $k === 'trigger' || $k === 'remote') {
            $k = 'clacker';
        }
        if ($k === 'on_demand' || $k === 'demand' || $k === 'toc') {
            $k = 'command';
        }

        return in_array($k, self::TRIGGER_KINDS, true) ? $k : '';
    }

    private function triggerLabelFr(string $kind): string
    {
        return match ($kind) {
            'clacker' => 'Déclencheur',
            'cellphone' => 'Téléphone',
            'command' => 'À la demande',
            default => 'À retardement',
        };
    }

    private function statusLabelFr(string $status, bool $pending = false): string
    {
        if ($status === 'armed' && $pending) {
            return 'Déclenchement demandé';
        }

        return match ($status) {
            'detonated' => 'A explosé',
            'defused' => 'Désamorcée',
            default => 'Armée',
        };
    }
}
