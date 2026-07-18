<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Médailles / distinctions attribuées aux membres (`badges` + pivot `user_badges`).
 */
class BadgeRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Médailles par utilisateur (les plus récentes en premier) — une seule requête pour tout un lot
     * d’utilisateurs, pour éviter le N+1 dans les listes (annuaire, fiches groupées, etc.).
     *
     * @param list<int> $userIds
     * @return array<int, list<array{name: string, description: ?string, icon_url: ?string, granted_at: ?string}>>
     */
    public function listForUsers(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($tenantId < 1 || $userIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $out = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT ub.user_id, b.name, b.description, b.icon_url, ub.granted_at
                 FROM user_badges ub
                 INNER JOIN badges b ON b.id = ub.badge_id AND b.tenant_id = ?
                 WHERE ub.tenant_id = ? AND ub.user_id IN ({$placeholders})
                 ORDER BY ub.granted_at DESC"
            );
            $stmt->execute(array_merge([$tenantId, $tenantId], $userIds));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid < 1) {
                    continue;
                }
                $out[$uid][] = [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'description' => $row['description'] !== null ? (string) $row['description'] : null,
                    'icon_url' => $row['icon_url'] !== null ? (string) $row['icon_url'] : null,
                    'granted_at' => $row['granted_at'] !== null ? (string) $row['granted_at'] : null,
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }
}
