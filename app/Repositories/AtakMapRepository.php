<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class AtakMapRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array> */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM atak_maps ORDER BY display_order ASC, slug ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (isset($row['config']) && is_string($row['config'])) {
                $row['config'] = json_decode($row['config'], true) ?? [];
            }
        }
        return $rows;
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM atak_maps WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        if (isset($row['config']) && is_string($row['config'])) {
            $row['config'] = json_decode($row['config'], true) ?? [];
        }
        return $row;
    }

    /**
     * Returns the map config to use for the overlay (tenant default or fallback to altis).
     */
    public function getDefaultForTenant(int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT default_map_slug FROM tenant_atak_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $slug = $stmt->fetchColumn();
        if ($slug === false || $slug === null || $slug === '') {
            $slug = 'altis';
        }
        return $this->getBySlug((string) $slug);
    }
}
