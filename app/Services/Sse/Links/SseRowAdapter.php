<?php

declare(strict_types=1);

namespace App\Services\Sse\Links;

use App\Core\Database;

/**
 * Adaptateur à partir d'une requête : la plupart des éléments du portail tiennent en une ligne de table. La requête, la
 * colonne d'identifiant et la fonction qui transforme une ligne en description sont données à la construction ; la
 * communauté est toujours une condition de la requête.
 */
final class SseRowAdapter implements SseLinkAdapter
{
    /**
     * @param string $cols colonnes sélectionnées (la colonne d'identifiant s'appelle « id »)
     * @param string $from « FROM table alias [JOIN ...] »
     * @param string $idCol colonne d'identifiant qualifiée (« d.id »)
     * @param string $tenantCol colonne de communauté qualifiée (« d.tenant_id »)
     * @param \Closure(array<string, mixed>): array<string, mixed> $map ligne => description
     * @param list<string> $searchCols colonnes qualifiées cherchées par « contient »
     */
    public function __construct(
        private readonly string $type,
        private readonly string $cols,
        private readonly string $from,
        private readonly string $idCol,
        private readonly string $tenantCol,
        private readonly \Closure $map,
        private readonly array $searchCols = [],
        private readonly ?Database $db = null,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    private function db(): Database
    {
        return $this->db ?? Database::getInstance();
    }

    public function describe(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), static fn (string $i): bool => $i !== '')));
        if ($tenantId < 1 || $ids === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 150) as $chunk) {
            $params = ['t' => $tenantId];
            $marks = [];
            foreach ($chunk as $i => $id) {
                $params['i' . $i] = ctype_digit($id) ? (int) $id : $id;
                $marks[] = ':i' . $i;
            }
            $rows = $this->db()->fetchAll(
                "SELECT {$this->cols} {$this->from} WHERE {$this->tenantCol} = :t AND {$this->idCol} IN (" . implode(',', $marks) . ')',
                $params
            );
            foreach ($rows as $row) {
                $desc = ($this->map)($row);
                $out[(string) $row['id']] = $desc + ['ref' => '', 'title' => '', 'summary' => '', 'href' => '', 'date' => '', 'secret' => false, 'member' => null, 'classification' => null, 'interest' => false];
            }
        }

        return $out;
    }

    public function search(int $tenantId, string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($tenantId < 1 || mb_strlen($query) < 2 || $this->searchCols === []) {
            return [];
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
        $or = [];
        foreach ($this->searchCols as $i => $col) {
            $or[] = "{$col} LIKE :q{$i} ESCAPE '\\'";
        }
        $params = ['t' => $tenantId];
        foreach (array_keys($this->searchCols) as $i) {
            $params['q' . $i] = $like;
        }
        $limit = max(1, min(25, $limit));
        $rows = $this->db()->fetchAll(
            "SELECT {$this->cols} {$this->from} WHERE {$this->tenantCol} = :t AND (" . implode(' OR ', $or) . ") ORDER BY {$this->idCol} DESC LIMIT " . $limit,
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $d = ($this->map)($row);
            $out[] = ['id' => (string) $row['id'], 'ref' => (string) ($d['ref'] ?? ''), 'title' => (string) ($d['title'] ?? ''), 'summary' => (string) ($d['summary'] ?? ''), '_desc' => $d];
        }

        return $out;
    }
}
