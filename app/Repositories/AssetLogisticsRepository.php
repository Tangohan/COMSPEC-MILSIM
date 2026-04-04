<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AssetLogisticsRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listByMission(string $missionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM asset_logistics_status WHERE mission_id = ? ORDER BY callsign');
        $stmt->execute([$missionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            if (!empty($r['ammo_state_json'])) {
                $r['ammo_state_json'] = json_decode($r['ammo_state_json'], true);
            }
        }
        return $rows;
    }

    public function upsert(string $missionId, string $assetId, array $data): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM asset_logistics_status WHERE mission_id = ? AND asset_id = ?');
        $stmt->execute([$missionId, $assetId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $ammoJson = isset($data['ammo_state_json']) ? (is_string($data['ammo_state_json']) ? $data['ammo_state_json'] : json_encode($data['ammo_state_json'])) : null;
        if ($existing) {
            $this->pdo->prepare(
                'UPDATE asset_logistics_status SET callsign = ?, vehicle_class = ?, fuel_ratio = ?, ammo_state_json = ?, damage_ratio = ?, crew_count = ?, cargo_slots_free = ?, slingload_capable = ?, last_update_at = NOW() WHERE id = ?'
            )->execute([
                $data['callsign'] ?? '',
                $data['vehicle_class'] ?? null,
                isset($data['fuel_ratio']) ? (float) $data['fuel_ratio'] : null,
                $ammoJson,
                isset($data['damage_ratio']) ? (float) $data['damage_ratio'] : null,
                isset($data['crew_count']) ? (int) $data['crew_count'] : null,
                isset($data['cargo_slots_free']) ? (int) $data['cargo_slots_free'] : null,
                isset($data['slingload_capable']) ? (int) (bool) $data['slingload_capable'] : 0,
                $existing['id'],
            ]);
            $row = $this->pdo->prepare('SELECT * FROM asset_logistics_status WHERE id = ?');
            $row->execute([$existing['id']]);
            $r = $row->fetch(PDO::FETCH_ASSOC);
            if ($r && !empty($r['ammo_state_json'])) {
                $r['ammo_state_json'] = json_decode($r['ammo_state_json'], true);
            }
            return $r ?? [];
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO asset_logistics_status (mission_id, asset_id, callsign, vehicle_class, fuel_ratio, ammo_state_json, damage_ratio, crew_count, cargo_slots_free, slingload_capable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $missionId,
            $assetId,
            $data['callsign'] ?? '',
            $data['vehicle_class'] ?? null,
            isset($data['fuel_ratio']) ? (float) $data['fuel_ratio'] : null,
            $ammoJson,
            isset($data['damage_ratio']) ? (float) $data['damage_ratio'] : null,
            isset($data['crew_count']) ? (int) $data['crew_count'] : null,
            isset($data['cargo_slots_free']) ? (int) $data['cargo_slots_free'] : null,
            isset($data['slingload_capable']) ? (int) (bool) $data['slingload_capable'] : 0,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $row = $this->pdo->prepare('SELECT * FROM asset_logistics_status WHERE id = ?');
        $row->execute([$id]);
        $r = $row->fetch(PDO::FETCH_ASSOC);
        if ($r && !empty($r['ammo_state_json'])) {
            $r['ammo_state_json'] = json_decode($r['ammo_state_json'], true);
        }
        return $r ?? [];
    }
}
