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
    }

    public function list(int $tenantId, ?string $missionId = null, ?string $author = null, ?string $dateFrom = null, ?string $dateTo = null, ?int $limit = 100): array
    {
        $sql = 'SELECT * FROM recon_images WHERE tenant_id = ?';
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
            'INSERT INTO recon_images (tenant_id, mission_id, author_callsign, unit_name, side, image_path, thumb_path, caption, pos_x, pos_y, pos_z, grid_ref, heading, altitude, device_type, captured_at, atak_cas_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
}
