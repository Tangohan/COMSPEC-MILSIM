<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

final class OrbatChartTypeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['tenant_orbat_chart_types']);

        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array{slug: string, label: string}> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT slug, label FROM tenant_orbat_chart_types WHERE tenant_id = ? ORDER BY label ASC'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'slug' => (string) ($row['slug'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
            ];
        }

        return $out;
    }

    public function findBySlug(int $tenantId, string $slug): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $slugEq = SqlText::equals($this->pdo, 'slug');
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, label FROM tenant_orbat_chart_types WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function create(int $tenantId, string $slug, string $label): bool
    {
        if (!$this->tableExists() || $slug === '' || $label === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_orbat_chart_types (tenant_id, slug, label, created_at) VALUES (?, ?, ?, NOW())'
        );

        try {
            return $stmt->execute([$tenantId, $slug, mb_substr($label, 0, 120)]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function delete(int $tenantId, string $slug): bool
    {
        if (!$this->tableExists() || $slug === '') {
            return false;
        }
        $slugEq = SqlText::equals($this->pdo, 'slug');
        $stmt = $this->pdo->prepare(
            'DELETE FROM tenant_orbat_chart_types WHERE tenant_id = ? AND ' . $slugEq . ' LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);

        return $stmt->rowCount() > 0;
    }
}
