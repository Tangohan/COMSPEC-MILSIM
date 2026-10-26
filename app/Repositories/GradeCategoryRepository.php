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
        // Catalogue partagé seulement : les systèmes / catégories sur mesure ont leur propre écran.
        try {
            $stmt = $this->pdo->query(
                'SELECT id, code, label, sort_order, is_active FROM grade_categories WHERE is_active = 1 AND owner_tenant_id IS NULL ORDER BY sort_order ASC, id ASC'
            );
        } catch (\Throwable) {
            $stmt = false;
        }
        if ($stmt === false) {
            $stmt = $this->pdo->query(
                'SELECT id, code, label, sort_order, is_active FROM grade_categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
            );
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($r) {
            $r['id'] = (int) $r['id'];
            $r['sort_order'] = (int) ($r['sort_order'] ?? 0);
            $r['is_active'] = (int) ($r['is_active'] ?? 1);
            return $r;
        }, $rows);
    }

    /**
     * Catégories proposées dans les fiches membres : catalogue partagé + catégories des systèmes sur mesure
     * de la communauté (classées après le catalogue, du plus haut au plus bas).
     *
     * @return list<array{id: int, code: string, label: string, sort_order: int, is_active: int}>
     */
    public function listForTenant(int $tenantId): array
    {
        $rows = $this->listActive();
        if ($tenantId < 1) {
            return $rows;
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT c.id, c.code, c.label, c.sort_order, c.is_active FROM grade_categories c
                 INNER JOIN grade_systems s ON s.id = c.grade_system_id AND s.owner_tenant_id = ?
                 WHERE c.owner_tenant_id = ? AND c.is_active = 1 ORDER BY s.id ASC, c.sort_order DESC, c.id ASC'
            );
            $stmt->execute([$tenantId, $tenantId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $r['id'] = (int) $r['id'];
                $r['sort_order'] = (int) ($r['sort_order'] ?? 0);
                $r['is_active'] = (int) ($r['is_active'] ?? 1);
                $rows[] = $r;
            }
        } catch (\Throwable) {
            // migration custom_grade_systems pas encore passée
        }

        return $rows;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM grade_categories WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
