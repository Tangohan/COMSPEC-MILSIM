<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Couvertures nées en jeu et contenus de la bibliothèque de l'éditeur attribués à un joueur. Toute requête est bornée à
 * la communauté. Ces lignes restent à part des identités du registre : un contrôle ou une fiche membre ne les croise pas.
 */
class SseIdCoverLinkRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function scopeKey(?int $contextId, ?string $opKey): string
    {
        return ($contextId ?? 0) . '|' . ($opKey ?? '');
    }

    /** @return array<string, mixed>|null */
    public function findGameCover(int $tenantId, int $userId, string $scopeKey): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_id_game_covers WHERE tenant_id = :t AND user_id = :u AND scope_key = :s',
            ['t' => $tenantId, 'u' => $userId, 's' => $scopeKey]
        );
    }

    public function saveGameCover(int $tenantId, int $userId, ?int $contextId, ?string $opKey, string $hash, string $json, string $now): int
    {
        $scope = self::scopeKey($contextId, $opKey);
        $row = $this->findGameCover($tenantId, $userId, $scope);
        if ($row !== null) {
            $this->db->execute(
                'UPDATE sse_id_game_covers SET content_hash = :h, cover_json = :j, updated_at = :now WHERE id = :id AND tenant_id = :t',
                ['h' => $hash, 'j' => $json, 'now' => $now, 'id' => (int) $row['id'], 't' => $tenantId]
            );

            return (int) $row['id'];
        }

        return $this->db->insert(
            'INSERT INTO sse_id_game_covers (tenant_id, user_id, scope_key, context_id, op_key, content_hash, cover_json, created_at, updated_at)
             VALUES (:t, :u, :s, :ctx, :op, :h, :j, :now, :now2)',
            ['t' => $tenantId, 'u' => $userId, 's' => $scope, 'ctx' => $contextId, 'op' => $opKey, 'h' => $hash, 'j' => $json, 'now' => $now, 'now2' => $now]
        );
    }

    /**
     * Les plus récentes d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function gameCoversForUser(int $tenantId, int $userId): array
    {
        return array_values($this->db->fetchAll(
            'SELECT * FROM sse_id_game_covers WHERE tenant_id = :t AND user_id = :u ORDER BY updated_at DESC, id DESC LIMIT 20',
            ['t' => $tenantId, 'u' => $userId]
        ));
    }

    /** @return array<string, mixed>|null identité remontée du jeu, de ce membre et de cette communauté seulement */
    public function findGameCoverById(int $tenantId, int $userId, int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_id_game_covers WHERE id = :id AND tenant_id = :t AND user_id = :u',
            ['id' => $id, 't' => $tenantId, 'u' => $userId]
        );
    }

    /**
     * Différence avec le jeu examinée pour ce contenu : elle ne revient que si le jeu envoie autre chose (étape 4250).
     */
    public function markGameCoverReviewed(int $tenantId, int $userId, int $id, string $hash, string $now): bool
    {
        return $this->db->execute(
            'UPDATE sse_id_game_covers SET reviewed_hash = :h, reviewed_at = :now WHERE id = :id AND tenant_id = :t AND user_id = :u',
            ['h' => $hash, 'now' => $now, 'id' => $id, 't' => $tenantId, 'u' => $userId]
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function findAssignment(int $tenantId, int $itemId, int $userId, string $scopeKey): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_editor_item_assignments WHERE tenant_id = :t AND item_id = :i AND user_id = :u AND scope_key = :s',
            ['t' => $tenantId, 'i' => $itemId, 'u' => $userId, 's' => $scopeKey]
        );
    }

    /**
     * @param array{context_id: ?int, op_key: ?string, identity_id: int, document_ids: list<int>, item_version: int} $values
     */
    public function saveAssignment(int $tenantId, int $itemId, int $userId, array $values, ?int $by): int
    {
        $scope = self::scopeKey($values['context_id'], $values['op_key']);
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->findAssignment($tenantId, $itemId, $userId, $scope);
        if ($row !== null) {
            $this->db->execute(
                'UPDATE sse_editor_item_assignments SET item_version = :v, updated_at = :now WHERE id = :id AND tenant_id = :t',
                ['v' => $values['item_version'], 'now' => $now, 'id' => (int) $row['id'], 't' => $tenantId]
            );

            return (int) $row['id'];
        }

        return $this->db->insert(
            'INSERT INTO sse_editor_item_assignments (tenant_id, item_id, user_id, scope_key, context_id, op_key, identity_id, document_ids, item_version, created_by_user_id, created_at, updated_at)
             VALUES (:t, :i, :u, :s, :ctx, :op, :identity, :docs, :v, :by, :now, :now2)',
            [
                't' => $tenantId, 'i' => $itemId, 'u' => $userId, 's' => $scope, 'ctx' => $values['context_id'], 'op' => $values['op_key'],
                'identity' => $values['identity_id'], 'docs' => (string) json_encode($values['document_ids']), 'v' => $values['item_version'],
                'by' => $by, 'now' => $now, 'now2' => $now,
            ]
        );
    }

    /**
     * Attributions de ces contenus, avec le nom du joueur et son Steam ID (lié ou non).
     *
     * @param list<int> $itemIds
     * @return array<int, list<array<string, mixed>>> contenu -> attributions
     */
    public function assignmentsForItems(int $tenantId, array $itemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll(
            'SELECT a.*, u.display_name AS user_name, u.steam_id FROM sse_editor_item_assignments a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.tenant_id = :t AND a.item_id IN (' . implode(', ', $ids) . ') ORDER BY a.updated_at DESC, a.id DESC',
            ['t' => $tenantId]
        ) as $row) {
            $out[(int) $row['item_id']][] = $row;
        }

        return $out;
    }
}
