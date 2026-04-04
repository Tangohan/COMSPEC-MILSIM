<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumBannedWordRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, tenant_id, word, severity, created_at FROM forum_banned_words WHERE tenant_id = ? ORDER BY word ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $tenantId, string $word, string $severity = 'block'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO forum_banned_words (tenant_id, word, severity, created_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $word, $severity]);
        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM forum_banned_words WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }
}
