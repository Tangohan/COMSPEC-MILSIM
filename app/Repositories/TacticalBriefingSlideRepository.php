<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Diapositives de briefing tactique — gérées côté back-office, consommées in-game (Arma/Eden)
 * via l'extension native qui télécharge l'image et l'applique en texture (setObjectTexture)
 * ou dans le dialog de briefing (RscPicture).
 */
class TacticalBriefingSlideRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function allForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tactical_briefing_slides WHERE tenant_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listActiveForTenant(int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tactical_briefing_slides WHERE tenant_id = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([$tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    public function findByIdForTenant(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tactical_briefing_slides WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function insert(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tactical_briefing_slides (tenant_id, title, image_path, sort_order, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            (string) ($data['title'] ?? ''),
            (string) ($data['image_path'] ?? ''),
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tactical_briefing_slides
             SET title = ?, image_path = ?, sort_order = ?, is_active = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            (string) ($data['title'] ?? ''),
            (string) ($data['image_path'] ?? ''),
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
            $id,
            $tenantId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tactical_briefing_slides WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }
}
