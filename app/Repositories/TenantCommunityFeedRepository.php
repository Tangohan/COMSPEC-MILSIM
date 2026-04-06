<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TenantCommunityFeedRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function insert(int $tenantId, string $category, string $title, string $body = '', ?string $linkUrl = null, ?int $actorUserId = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tenant_community_feed (tenant_id, category, title, body, link_url, actor_user_id) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$tenantId, $category, $title, $body === '' ? null : $body, $linkUrl, $actorUserId]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return;
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listRecentForTenant(int $tenantId, int $limit = 20, ?string $categoryPrefix = null): array
    {
        try {
            if ($categoryPrefix !== null && $categoryPrefix !== '') {
                $stmt = $this->pdo->prepare(
                    'SELECT * FROM tenant_community_feed WHERE tenant_id = ? AND category LIKE ? ORDER BY created_at DESC LIMIT ' . (int) max(1, min(100, $limit))
                );
                $stmt->execute([$tenantId, $categoryPrefix . '%']);
            } else {
                $stmt = $this->pdo->prepare(
                    'SELECT * FROM tenant_community_feed WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ' . (int) max(1, min(100, $limit))
                );
                $stmt->execute([$tenantId]);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }
}
