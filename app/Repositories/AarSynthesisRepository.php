<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Synthèses de plusieurs comptes rendus, propres à une communauté. */
final class AarSynthesisRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aar_syntheses' LIMIT 1");

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT s.*, u.display_name AS author_name FROM aar_syntheses s
             LEFT JOIN users u ON u.id = s.author_user_id
             WHERE s.tenant_id = ? ORDER BY s.created_at DESC, s.id DESC LIMIT 200'
        );
        $st->execute([$tenantId]);

        return array_map([$this, 'present'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT s.*, u.display_name AS author_name FROM aar_syntheses s
             LEFT JOIN users u ON u.id = s.author_user_id WHERE s.tenant_id = ? AND s.id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->present($row) : null;
    }

    /**
     * @param list<int> $reportIds
     */
    public function create(int $tenantId, int $userId, string $title, array $reportIds, string $improvements, string $lessons, string $conclusion): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO aar_syntheses (tenant_id, author_user_id, title, report_ids_json, improvements, lessons, conclusion)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $tenantId,
            $userId > 0 ? $userId : null,
            mb_substr(trim($title), 0, 200),
            json_encode(array_values(array_map('intval', $reportIds)), JSON_UNESCAPED_UNICODE),
            mb_substr($improvements, 0, 20000),
            mb_substr($lessons, 0, 20000),
            mb_substr($conclusion, 0, 20000),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $tenantId, int $id): bool
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM aar_syntheses WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $ids = json_decode((string) ($row['report_ids_json'] ?? ''), true);
        $row['report_ids'] = is_array($ids) ? array_values(array_map('intval', $ids)) : [];

        return $row;
    }
}
