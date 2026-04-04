<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class FireTableRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * @return array<int, array{range: int|float, elevation_mils?: int|float, charge?: int, tof?: float}>
     */
    public function getTable(string $weaponSystem, string $ammoType): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT table_json FROM fire_tables WHERE weapon_system = ? AND ammo_type = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$weaponSystem, $ammoType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['table_json'])) {
            return null;
        }
        $decoded = json_decode($row['table_json'], true);
        return is_array($decoded) ? $decoded : null;
    }

    public function listWeaponSystems(): array
    {
        $stmt = $this->pdo->query('SELECT DISTINCT weapon_system, ammo_type FROM fire_tables ORDER BY weapon_system, ammo_type');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $weaponSystem, string $ammoType, int $minRange, int $maxRange, string $tableJson): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fire_tables (weapon_system, ammo_type, min_range, max_range, table_json) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$weaponSystem, $ammoType, $minRange, $maxRange, $tableJson]);
        return (int) $this->pdo->lastInsertId();
    }
}
