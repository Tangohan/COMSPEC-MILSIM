<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class FireUnitRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listByMission(string $missionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fire_units WHERE mission_id = ? ORDER BY callsign');
        $stmt->execute([$missionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fire_units WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByIdAndMission(int $id, string $missionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fire_units WHERE id = ? AND mission_id = ?');
        $stmt->execute([$id, $missionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $missionId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fire_units (mission_id, callsign, vehicle_class, weapon_system, pos_x, pos_y, pos_z, heading, side, status) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $missionId,
            $data['callsign'] ?? 'UNKNOWN',
            $data['vehicle_class'] ?? null,
            $data['weapon_system'] ?? null,
            (float) ($data['pos_x'] ?? 0),
            (float) ($data['pos_y'] ?? 0),
            (float) ($data['pos_z'] ?? 0),
            isset($data['heading']) ? (float) $data['heading'] : null,
            $data['side'] ?? null,
            $data['status'] ?? 'active',
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->getById($id);
        return $row ?? [];
    }

    public function updatePosition(int $id, string $missionId, float $posX, float $posY, ?float $posZ, ?float $heading): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE fire_units SET pos_x = ?, pos_y = ?, pos_z = ?, heading = ?, last_update_at = NOW() WHERE id = ? AND mission_id = ?'
        );
        $stmt->execute([$posX, $posY, $posZ ?? 0, $heading, $id, $missionId]);
        return $stmt->rowCount() > 0;
    }

    public function upsertByCallsign(string $missionId, string $callsign, array $data): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM fire_units WHERE mission_id = ? AND callsign = ?');
        $stmt->execute([$missionId, $callsign]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->pdo->prepare(
                'UPDATE fire_units SET vehicle_class = ?, weapon_system = ?, pos_x = ?, pos_y = ?, pos_z = ?, heading = ?, side = ?, status = ?, last_update_at = NOW() WHERE id = ?'
            )->execute([
                $data['vehicle_class'] ?? null,
                $data['weapon_system'] ?? null,
                (float) ($data['pos_x'] ?? 0),
                (float) ($data['pos_y'] ?? 0),
                (float) ($data['pos_z'] ?? 0),
                isset($data['heading']) ? (float) $data['heading'] : null,
                $data['side'] ?? null,
                $data['status'] ?? 'active',
                $existing['id'],
            ]);
            $row = $this->getById((int) $existing['id']);
            return $row ?? [];
        }
        return $this->create($missionId, array_merge($data, ['callsign' => $callsign]));
    }
}
