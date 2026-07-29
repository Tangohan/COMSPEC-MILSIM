<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ReconImageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $path = base_path('bootstrap/atak_recon_images_actions_migration.php');
        if (is_file($path)) {
            $migrate = require $path;
            if (is_callable($migrate)) {
                try {
                    $migrate($this->pdo);
                } catch (\Throwable) {
                }
            }
        }
        $done = true;
    }

    public function list(int $tenantId, ?string $missionId = null, ?string $author = null, ?string $dateFrom = null, ?string $dateTo = null, ?int $limit = 100): array
    {
        $sql = 'SELECT * FROM recon_images WHERE tenant_id = ? AND deleted_at IS NULL';
        $params = [$tenantId];
        if ($missionId !== null && $missionId !== '') {
            $sql .= ' AND mission_id = ?';
            $params[] = $missionId;
        }
        if ($author !== null && $author !== '') {
            $sql .= ' AND author_callsign = ?';
            $params[] = $author;
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $sql .= ' AND (captured_at >= ? OR created_at >= ?)';
            $params[] = $dateFrom;
            $params[] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $sql .= ' AND (captured_at <= ? OR created_at <= ?)';
            $params[] = $dateTo;
            $params[] = $dateTo;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get(int $tenantId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM recon_images WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$tenantId, $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $tenantId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO recon_images (tenant_id, mission_id, author_callsign, unit_name, side, image_path, thumb_path, caption, fx_profile, fx_intensity, pos_x, pos_y, pos_z, grid_ref, heading, altitude, device_type, captured_at, atak_cas_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $data['mission_id'] ?? null,
            $data['author_callsign'] ?? $data['author'] ?? 'Unknown',
            $data['unit_name'] ?? null,
            $data['side'] ?? 'WEST',
            $data['image_path'],
            $data['thumb_path'] ?? null,
            $data['caption'] ?? null,
            $data['fx_profile'] ?? null,
            $data['fx_intensity'] ?? null,
            $data['pos_x'] ?? null,
            $data['pos_y'] ?? null,
            $data['pos_z'] ?? null,
            $data['grid_ref'] ?? null,
            $data['heading'] ?? null,
            $data['altitude'] ?? null,
            $data['device_type'] ?? 'CTAB',
            isset($data['captured_at']) ? date('Y-m-d H:i:s', (int) $data['captured_at']) : null,
            $data['atak_cas_id'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->get($tenantId, $id);
        return $row ?? [];
    }

    public function linkToCas(int $tenantId, int $id, int $atakCasId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE recon_images SET atak_cas_id = ? WHERE tenant_id = ? AND id = ?');
        $stmt->execute([$atakCasId, $tenantId, $id]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        return $this->get($tenantId, $id);
    }

    public function updateOps(int $tenantId, int $id, array $data): ?array
    {
        $fields = [];
        $params = ['tenant_id' => $tenantId, 'id' => $id];

        if (array_key_exists('operator_comment', $data)) {
            $fields[] = 'operator_comment = :operator_comment';
            $comment = trim((string) ($data['operator_comment'] ?? ''));
            $params['operator_comment'] = $comment !== '' ? $comment : null;
        }
        if (array_key_exists('is_blurred', $data)) {
            $fields[] = 'is_blurred = :is_blurred';
            $params['is_blurred'] = !empty($data['is_blurred']) ? 1 : 0;
        }
        if (array_key_exists('deleted_at', $data)) {
            $fields[] = 'deleted_at = :deleted_at';
            $params['deleted_at'] = $data['deleted_at'];
        }
        if (array_key_exists('sse_case_id', $data)) {
            $fields[] = 'sse_case_id = :sse_case_id';
            $params['sse_case_id'] = $data['sse_case_id'] !== null ? (int) $data['sse_case_id'] : null;
        }
        if (array_key_exists('sse_evidence_id', $data)) {
            $fields[] = 'sse_evidence_id = :sse_evidence_id';
            $params['sse_evidence_id'] = $data['sse_evidence_id'] !== null ? (int) $data['sse_evidence_id'] : null;
        }
        if (array_key_exists('sse_transferred_at', $data)) {
            $fields[] = 'sse_transferred_at = :sse_transferred_at';
            $params['sse_transferred_at'] = $data['sse_transferred_at'];
        }

        if ($fields === []) {
            return $this->get($tenantId, $id);
        }

        $sql = 'UPDATE recon_images SET ' . implode(', ', $fields) . ' WHERE tenant_id = :tenant_id AND id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->get($tenantId, $id);
    }

    /**
     * Dernière image par feed (unit_name = feed_id) ou par couple auteur + type d’appareil.
     *
     * @param list<string> $feedIds
     * @return array<string, array<string, mixed>> keyed by feed_id or "device:AUTHOR:TYPE"
     */
    public function latestSnapshots(int $tenantId, array $feedIds = [], int $limit = 80): array
    {
        $sql = 'SELECT * FROM recon_images WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byFeed = [];
        $byAuthorDevice = [];
        $feedSet = [];
        foreach ($feedIds as $fid) {
            $fid = trim((string) $fid);
            if ($fid !== '') {
                $feedSet[$fid] = true;
            }
        }
        foreach ($rows as $row) {
            $unit = trim((string) ($row['unit_name'] ?? ''));
            if ($unit !== '' && !isset($byFeed[$unit])) {
                if ($feedSet === [] || isset($feedSet[$unit])) {
                    $byFeed[$unit] = $row;
                }
            }
            $author = strtoupper(trim((string) ($row['author_callsign'] ?? '')));
            $device = strtoupper(trim((string) ($row['device_type'] ?? 'CTAB')));
            if ($author !== '') {
                $key = $author . ':' . $device;
                if (!isset($byAuthorDevice[$key])) {
                    $byAuthorDevice[$key] = $row;
                }
            }
        }

        return [
            'by_feed' => $byFeed,
            'by_author_device' => $byAuthorDevice,
            'recent' => $rows,
        ];
    }
}
