<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class LaserCodeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function list(int $tenantId, int $mapId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_laser_codes WHERE tenant_id = ? AND map_id = ? ORDER BY call_sign');
        $stmt->execute([$tenantId, $mapId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function upsert(int $tenantId, int $mapId, string $callSign, string $laserCode, ?float $posX = null, ?float $posY = null, string $status = 'ACTIVE'): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM atak_laser_codes WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $stmt->execute([$tenantId, $mapId, $callSign]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastUpdate = time();
        if ($existing) {
            $this->pdo->prepare('UPDATE atak_laser_codes SET laser_code = ?, pos_x = ?, pos_y = ?, status = ?, last_update = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$laserCode, $posX, $posY, $status, $lastUpdate, $existing['id']]);
        } else {
            $this->pdo->prepare('INSERT INTO atak_laser_codes (tenant_id, map_id, call_sign, laser_code, pos_x, pos_y, status, last_update) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$tenantId, $mapId, $callSign, $laserCode, $posX, $posY, $status, $lastUpdate]);
        }
        $stmt = $this->pdo->prepare('SELECT * FROM atak_laser_codes WHERE tenant_id = ? AND map_id = ? AND call_sign = ?');
        $stmt->execute([$tenantId, $mapId, $callSign]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
