<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Historique des identités (étape 4250) : chaque valeur écartée lors d'une reprise Athena ↔ jeu, champ par champ,
 * restaurable. Toute requête est bornée à la communauté et au membre.
 */
final class SseIdentityHistoryRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array{user_id: int, identity_id: int, field: string, kept_value: ?string, lost_value: ?string, lost_side: string, decision: string, game_cover_id?: ?int, actor_user_id?: ?int} $row
     */
    public function add(int $tenantId, array $row): int
    {
        $cut = static fn (?string $v): ?string => $v === null ? null : mb_substr($v, 0, 255);

        return $this->db->insert(
            'INSERT INTO sse_identity_history (tenant_id, user_id, identity_id, field, kept_value, lost_value, lost_side, decision, game_cover_id, actor_user_id, created_at)
             VALUES (:t, :u, :i, :f, :k, :l, :side, :d, :gc, :a, :now)',
            [
                't' => $tenantId, 'u' => $row['user_id'], 'i' => $row['identity_id'], 'f' => mb_substr($row['field'], 0, 32),
                'k' => $cut($row['kept_value']), 'l' => $cut($row['lost_value']), 'side' => $row['lost_side'], 'd' => $row['decision'],
                'gc' => $row['game_cover_id'] ?? null, 'a' => $row['actor_user_id'] ?? null, 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $userId, int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_identity_history WHERE id = :id AND tenant_id = :t AND user_id = :u',
            ['id' => $id, 't' => $tenantId, 'u' => $userId]
        );
    }

    /**
     * Les plus récentes d'abord, pour les identités données (celles que voit le responsable).
     *
     * @param list<int> $identityIds
     * @return list<array<string, mixed>>
     */
    public function listForIdentities(int $tenantId, int $userId, array $identityIds, int $limit = 50): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $identityIds), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $params = ['t' => $tenantId, 'u' => $userId];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':i' . $k;
            $params['i' . $k] = $id;
        }

        return array_values($this->db->fetchAll(
            'SELECT * FROM sse_identity_history WHERE tenant_id = :t AND user_id = :u AND identity_id IN (' . implode(', ', $marks) . ')
             ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(200, $limit)),
            $params
        ));
    }

    public function markRestored(int $tenantId, int $id, ?int $by): bool
    {
        return $this->db->execute(
            'UPDATE sse_identity_history SET restored_at = :now, restored_by_user_id = :by WHERE id = :id AND tenant_id = :t AND restored_at IS NULL',
            ['now' => gmdate('Y-m-d H:i:s'), 'by' => $by, 'id' => $id, 't' => $tenantId]
        ) > 0;
    }
}
