<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class GradeSystemRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array{id: int, code: string, label: string, country_code: string, format_type: string, is_active: int}> */
    public function listActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, code, label, country_code, format_type, is_active FROM grade_systems WHERE is_active = 1 ORDER BY country_code ASC, id ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['is_active'] = (int) ($r['is_active'] ?? 1);
            return $r;
        }, $rows);
    }

    /** @return list<array{id: int, code: string, label: string, country_code: string, format_type: string, is_active: int}> */
    public function listByCountry(string $countryCode): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, label, country_code, format_type, is_active FROM grade_systems WHERE is_active = 1 AND country_code = ? ORDER BY id ASC'
        );
        $stmt->execute([$countryCode]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['is_active'] = (int) ($r['is_active'] ?? 1);
            return $r;
        }, $rows);
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM grade_systems WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
