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

    /**
     * Crée (si besoin) le badge « Donateur ATAK » pour le tenant et l’attribue au membre.
     * Idempotent grâce à uk_user_badge / uk_badges_tenant_slug.
     */
    public function ensureAndGrantDonorAtak(int $tenantId, int $userId, ?int $grantedByUserId = null): bool
    {
        return $this->ensureAndGrant(
            $tenantId,
            $userId,
            'donateur-atak',
            'Donateur ATAK',
            'A soutenu le financement du module ATAK sur Athena.',
            null,
            $grantedByUserId
        );
    }

    /**
     * @return bool true si attribution effectuée ou déjà présente
     */
    public function ensureAndGrant(
        int $tenantId,
        int $userId,
        string $slug,
        string $name,
        ?string $description = null,
        ?string $iconUrl = null,
        ?int $grantedByUserId = null
    ): bool {
        if ($tenantId < 1 || $userId < 1 || $slug === '') {
            return false;
        }
        try {
            $badgeId = $this->ensureBadgeId($tenantId, $slug, $name, $description, $iconUrl);
            if ($badgeId < 1) {
                return false;
            }
            $stmt = $this->pdo->prepare(
                'INSERT IGNORE INTO user_badges (tenant_id, user_id, badge_id, granted_at, granted_by_user_id)
                 VALUES (?, ?, ?, NOW(), ?)'
            );
            $stmt->execute([
                $tenantId,
                $userId,
                $badgeId,
                $grantedByUserId !== null && $grantedByUserId > 0 ? $grantedByUserId : null,
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function ensureBadgeId(
        int $tenantId,
        string $slug,
        string $name,
        ?string $description,
        ?string $iconUrl
    ): int {
        $find = $this->pdo->prepare('SELECT id FROM badges WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $find->execute([$tenantId, $slug]);
        $existing = $find->fetchColumn();
        if ($existing !== false) {
            return (int) $existing;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO badges (tenant_id, slug, name, description, icon_url, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $ins->execute([$tenantId, $slug, $name, $description, $iconUrl]);

        return (int) $this->pdo->lastInsertId();
    }
}
