<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class GradeCategoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array{id: int, code: string, label: string, sort_order: int, is_active: int}> */
    public function listActive(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, code, label, sort_order, is_active FROM grade_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['sort_order'] = (int) ($r['sort_order'] ?? 0);
            $r['is_active'] = (int) ($r['is_active'] ?? 1);
            return $r;
        }, $rows);
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM grade_categories WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
