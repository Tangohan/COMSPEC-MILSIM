<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Charges à retardement (minuterie ACE) pour l’ATAK web.
 */
class AtakExplosiveTimerRepository
{
    use LazyDatabaseConnection;

    private const STATUSES = ['armed', 'detonated', 'defused'];

    private ?bool $tablesReady = null;

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
             ORDER BY CASE status WHEN 'armed' THEN 0 ELSE 1 END, detonates_at ASC, id ASC"
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

        if ($status !== 'armed') {
            if ($existing === null) {
                return null;
            }
            if (($existing['status'] ?? '') === 'armed') {
                $this->pdo()->prepare(
                    "UPDATE atak_explosive_timers
                     SET status = :status, ended_at = NOW()
                     WHERE id = :id"
                )->execute([
                    'status' => $status,
                    'id' => (int) $existing['id'],
                ]);
            }

            return $this->findById((int) $existing['id']);
        }

        $fuse = (int) ($data['fuse_seconds'] ?? $data['timer_seconds'] ?? 0);
        $fuse = max(1, min(86400, $fuse));
        $author = mb_substr(trim((string) ($data['author'] ?? '')), 0, 120);
        $label = mb_substr(trim((string) ($data['magazine_label'] ?? $data['label'] ?? '')), 0, 160);
        $grid = mb_substr(trim((string) ($data['grid'] ?? $data['grid_ref'] ?? '')), 0, 48);
        $posX = (float) ($data['pos_x'] ?? 0);
        $posY = (float) ($data['pos_y'] ?? 0);

        if ($existing !== null) {
            $this->pdo()->prepare(
                "UPDATE atak_explosive_timers SET
                    author = :author,
                    magazine_label = :magazine_label,
                    grid_ref = :grid_ref,
                    pos_x = :pos_x,
                    pos_y = :pos_y,
                    fuse_seconds = :fuse_seconds,
                    status = 'armed',
                    started_at = IF(status = 'armed', started_at, NOW()),
                    detonates_at = DATE_ADD(IF(status = 'armed', started_at, NOW()), INTERVAL {$fuse} SECOND),
                    ended_at = NULL
                 WHERE id = :id"
            )->execute([
                'author' => $author !== '' ? $author : (string) ($existing['author'] ?? ''),
                'magazine_label' => $label !== '' ? $label : (string) ($existing['magazine_label'] ?? ''),
                'grid_ref' => $grid !== '' ? $grid : (string) ($existing['grid_ref'] ?? ''),
                'pos_x' => $posX,
                'pos_y' => $posY,
                'fuse_seconds' => $fuse,
                'id' => (int) $existing['id'],
            ]);

            return $this->findById((int) $existing['id']);
        }

        $this->pdo()->prepare(
            "INSERT INTO atak_explosive_timers (
                tenant_id, map_id, charge_id, author, magazine_label, grid_ref,
                pos_x, pos_y, fuse_seconds, status, started_at, detonates_at
            ) VALUES (
                :tenant_id, :map_id, :charge_id, :author, :magazine_label, :grid_ref,
                :pos_x, :pos_y, :fuse_seconds, 'armed', NOW(), DATE_ADD(NOW(), INTERVAL {$fuse} SECOND)
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
        ]);
        $id = (int) $this->pdo()->lastInsertId();

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
    private function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo()->prepare('SELECT * FROM atak_explosive_timers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
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
        $detonatesAt = (string) ($row['detonates_at'] ?? '');
        $remaining = 0;
        if ($status === 'armed' && $detonatesAt !== '') {
            $ts = strtotime($detonatesAt);
            if ($ts !== false) {
                $remaining = max(0, $ts - $now);
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'charge_id' => (string) ($row['charge_id'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'magazine_label' => (string) ($row['magazine_label'] ?? ''),
            'grid_ref' => (string) ($row['grid_ref'] ?? ''),
            'pos_x' => isset($row['pos_x']) ? (float) $row['pos_x'] : null,
            'pos_y' => isset($row['pos_y']) ? (float) $row['pos_y'] : null,
            'fuse_seconds' => $fuse,
            'status' => $status,
            'status_label' => $this->statusLabelFr($status),
            'started_at' => (string) ($row['started_at'] ?? ''),
            'detonates_at' => $detonatesAt,
            'ended_at' => $row['ended_at'] ?? null,
            'remaining_seconds' => $remaining,
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

    private function statusLabelFr(string $status): string
    {
        return match ($status) {
            'detonated' => 'A explosé',
            'defused' => 'Désamorcée',
            default => 'En cours',
        };
    }
}
