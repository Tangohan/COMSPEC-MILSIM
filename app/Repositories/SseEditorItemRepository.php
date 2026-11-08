<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Contenus de l'éditeur SSE du mod, par communauté. Toute requête est bornée à la communauté : l'identifiant du contenu
 * vient du jeu et n'est unique qu'à l'intérieur d'une communauté.
 */
class SseEditorItemRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, string $uid): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_editor_items WHERE tenant_id = :t AND item_uid = :u',
            ['t' => $tenantId, 'u' => $uid]
        );
    }

    /**
     * Les plus récents d'abord.
     *
     * @return list<array<string, mixed>>
     */
    public function page(int $tenantId, int $offset, int $limit): array
    {
        return array_values($this->db->fetchAll(
            'SELECT * FROM sse_editor_items WHERE tenant_id = :t ORDER BY updated_at DESC, id DESC LIMIT '
            . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            ['t' => $tenantId]
        ));
    }

    /**
     * Liste de l'écran du Bureau SSE, les plus récents d'abord, avec le nom de l'auteur.
     *
     * @param array{type?: string, state?: string, q?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(int $tenantId, array $filters, int $offset, int $limit): array
    {
        [$where, $params] = self::searchWhere($tenantId, $filters);

        return array_values($this->db->fetchAll(
            'SELECT i.*, u.display_name AS author_name FROM sse_editor_items i
             LEFT JOIN users u ON u.id = i.author_user_id
             WHERE ' . $where . ' ORDER BY i.updated_at DESC, i.id DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $params
        ));
    }

    /** @param array{type?: string, state?: string, q?: string} $filters */
    public function searchCount(int $tenantId, array $filters): int
    {
        [$where, $params] = self::searchWhere($tenantId, $filters);
        $row = $this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_editor_items i WHERE ' . $where, $params);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @param array{type?: string, state?: string, q?: string} $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function searchWhere(int $tenantId, array $filters): array
    {
        $where = ['i.tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (($filters['type'] ?? '') !== '') {
            $where[] = 'i.item_type = :type';
            $params['type'] = $filters['type'];
        }
        if (($filters['state'] ?? '') !== '') {
            $where[] = 'i.state = :state';
            $params['state'] = $filters['state'];
        }
        if (($filters['q'] ?? '') !== '') {
            // « ! » : même sens en MySQL, MariaDB et sqlite, contrairement à la barre oblique inverse.
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $filters['q']) . '%';
            $parts = [];
            foreach (['title', 'body', 'op_label', 'link_label', 'found_by'] as $n => $column) {
                $parts[] = 'i.' . $column . ' LIKE :q' . $n . " ESCAPE '!'";
                $params['q' . $n] = $like;
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @param array<string, mixed> $values colonnes modifiables
     */
    public function save(int $tenantId, string $uid, array $values): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->find($tenantId, $uid);
        if ($existing !== null) {
            $set = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($values)));
            $this->db->execute(
                'UPDATE sse_editor_items SET ' . $set . ', updated_at = :now WHERE id = :id AND tenant_id = :t',
                $values + ['now' => $now, 'id' => (int) $existing['id'], 't' => $tenantId]
            );

            return (int) $existing['id'];
        }
        $cols = array_keys($values);

        return $this->db->insert(
            'INSERT INTO sse_editor_items (tenant_id, item_uid, created_at, updated_at, ' . implode(', ', $cols) . ')
             VALUES (:t, :u, :created, :updated, :' . implode(', :', $cols) . ')',
            $values + ['t' => $tenantId, 'u' => $uid, 'created' => $now, 'updated' => $now]
        );
    }

    /** Le premier joueur qui l'a trouvé reste : renvoie vrai s'il vient d'être inscrit. */
    public function markFound(int $tenantId, string $uid, string $by, string $when): bool
    {
        return $this->db->execute(
            'UPDATE sse_editor_items SET found_by = :b, found_when = :w
             WHERE tenant_id = :t AND item_uid = :u AND found_when IS NULL',
            ['b' => $by, 'w' => $when, 't' => $tenantId, 'u' => $uid]
        ) > 0;
    }
}
