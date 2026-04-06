<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listPublishedForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM legacy_training_modules WHERE tenant_id = ? AND status = ? ORDER BY title ASC'
        );
        $stmt->execute([$tenantId, 'published']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBySlug(string $slug, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM legacy_training_modules WHERE slug = ? AND status = ?';
        $params = [$slug, 'published'];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Modules legacy publiés liés via document_links (entity_type = training).
     *
     * @param list<int> $documentIds
     * @return list<array{document_id: int, title: string, slug: string}>
     */
    public function listDocumentLinkedLegacyModules(int $tenantId, array $documentIds): array
    {
        $documentIds = array_values(array_unique(array_filter(array_map('intval', $documentIds), static fn (int $i): bool => $i > 0)));
        if ($documentIds === []) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($documentIds), '?'));
            $sql = "SELECT dl.document_id, m.title AS title, m.slug AS slug
                    FROM document_links dl
                    INNER JOIN legacy_training_modules m ON m.id = dl.entity_id AND m.tenant_id = dl.tenant_id
                    WHERE dl.tenant_id = ?
                      AND dl.entity_type = 'training'
                      AND dl.document_id IN ($ph)
                      AND m.status = 'published'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$tenantId], $documentIds));
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $out[] = [
                    'document_id' => (int) ($row['document_id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'slug' => (string) ($row['slug'] ?? ''),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<int> $moduleIds
     * @return array<int, array{title: string, slug: string}>
     */
    public function batchPublishedModulesById(int $tenantId, array $moduleIds): array
    {
        $moduleIds = array_values(array_unique(array_filter(array_map('intval', $moduleIds), static fn (int $i): bool => $i > 0)));
        if ($moduleIds === []) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($moduleIds), '?'));
            $sql = "SELECT id, title, slug FROM legacy_training_modules
                    WHERE tenant_id = ? AND status = 'published' AND id IN ($ph)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge([$tenantId], $moduleIds));
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $out[$id] = [
                        'title' => (string) ($row['title'] ?? ''),
                        'slug' => (string) ($row['slug'] ?? ''),
                    ];
                }
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
