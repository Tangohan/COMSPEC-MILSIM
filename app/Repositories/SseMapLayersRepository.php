<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Lectures des calques SSE des cartes : contrôles d'identité positionnés, sites, positions des téléphones extraits.
 *
 * Toutes les requêtes sont filtrées par communauté (tenant_id) et par carte (context_id), paramétrées, bornées. Une table
 * absente (ancienne communauté, migration pas encore passée) donne un résultat vide, jamais une erreur.
 */
final class SseMapLayersRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Contrôles d'identité avec une position de jeu, les plus récents d'abord.
     *
     * @param array{0: float, 1: float, 2: float, 3: float}|null $bbox
     * @return list<array<string, mixed>>
     */
    public function controls(int $tenantId, int $mapId, ?string $since, ?string $until, ?array $bbox, int $limit): array
    {
        $where = ['tenant_id = :t', 'context_id = :m', 'pos_x IS NOT NULL', 'pos_y IS NOT NULL'];
        $params = ['t' => $tenantId, 'm' => $mapId];
        $this->timeRange($where, $params, 'created_at', $since, $until);
        $this->boxRange($where, $params, 'pos_x', 'pos_y', $bbox);

        return $this->all(
            'SELECT id, person_id, identity_id, verdict, grid, pos_x, pos_y, game_time, created_at
               FROM sse_id_checks WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . max(1, min(2000, $limit)),
            $params
        );
    }

    /**
     * Sites exploités avec une position de jeu.
     *
     * @param array{0: float, 1: float, 2: float, 3: float}|null $bbox
     * @return list<array<string, mixed>>
     */
    public function sites(int $tenantId, int $mapId, ?array $bbox, int $limit): array
    {
        $where = ['tenant_id = :t', 'context_id = :m', 'pos_x IS NOT NULL', 'pos_y IS NOT NULL'];
        $params = ['t' => $tenantId, 'm' => $mapId];
        $this->boxRange($where, $params, 'pos_x', 'pos_y', $bbox);

        return $this->all(
            'SELECT id, name, site_type, status, grid_reference, pos_x, pos_y, created_at
               FROM sse_sites WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . max(1, min(2000, $limit)),
            $params
        );
    }

    /**
     * Extractions de téléphones visibles de la carte, dans la fenêtre de temps.
     *
     * @return list<array<string, mixed>>
     */
    public function extractions(int $tenantId, int $mapId, ?string $since, ?string $until, int $limit): array
    {
        $where = ['tenant_id = :t', 'context_id = :m', "status = 'visible'"];
        $params = ['t' => $tenantId, 'm' => $mapId];
        $this->timeRange($where, $params, 'created_at', $since, $until);

        return $this->all(
            'SELECT id, ref, device_label, owner_label, owner_kind, created_at
               FROM sse_phone_extractions WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(200, $limit)),
            $params
        );
    }

    /**
     * Extractions visibles qui n'ont pas de carte d'origine (reçues avant que la carte soit enregistrée) : non affichables
     * sans risque de les poser sur le mauvais théâtre.
     */
    public function extractionsWithoutMap(int $tenantId, ?string $since, ?string $until): int
    {
        $where = ['tenant_id = :t', 'context_id IS NULL', "status = 'visible'"];
        $params = ['t' => $tenantId];
        $this->timeRange($where, $params, 'created_at', $since, $until);
        $row = $this->one('SELECT COUNT(*) AS n FROM sse_phone_extractions WHERE ' . implode(' AND ', $where), $params);

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Positions (catégorie « positions ») des extractions données, dans l'ordre de réception.
     *
     * @param list<int> $extractionIds
     * @return list<array<string, mixed>>
     */
    public function positions(int $tenantId, array $extractionIds, int $limit): array
    {
        $ids = $this->ids($extractionIds, 200);
        if ($ids === []) {
            return [];
        }
        $marks = [];
        $params = ['t' => $tenantId];
        foreach ($ids as $i => $id) {
            $marks[] = ':e' . $i;
            $params['e' . $i] = $id;
        }

        return $this->all(
            "SELECT extraction_id, at, grid FROM sse_phone_extraction_items
              WHERE tenant_id = :t AND category = 'positions' AND grid IS NOT NULL AND grid <> ''
                AND extraction_id IN (" . implode(', ', $marks) . ')
              ORDER BY extraction_id ASC, id ASC LIMIT ' . max(1, min(20000, $limit)),
            $params
        );
    }

    /**
     * Noms des fiches personnes (id => prénom, nom, alias).
     *
     * @param list<int> $personIds
     * @return array<int, array{first: string, last: string, alias: string}>
     */
    public function personNames(int $tenantId, array $personIds): array
    {
        return $this->names('sse_persons', 'alias', $tenantId, $personIds, '');
    }

    /**
     * Noms des identités du registre, hors identités d'emprunt : alias, couvertures et faux relèvent de la vérité de jeu et
     * ne sortent jamais d'ici.
     *
     * @param list<int> $identityIds
     * @return array<int, array{first: string, last: string, alias: string}>
     */
    public function plainIdentityNames(int $tenantId, array $identityIds): array
    {
        return $this->names('sse_identities', "''", $tenantId, $identityIds, " AND kind NOT IN ('alias', 'cover', 'forged')");
    }

    /**
     * @param list<int> $ids
     * @return array<int, array{first: string, last: string, alias: string}>
     */
    private function names(string $table, string $aliasExpr, int $tenantId, array $ids, string $extra): array
    {
        $ids = $this->ids($ids, 500);
        if ($ids === []) {
            return [];
        }
        $marks = [];
        $params = ['t' => $tenantId];
        foreach ($ids as $i => $id) {
            $marks[] = ':i' . $i;
            $params['i' . $i] = $id;
        }
        $out = [];
        foreach ($this->all(
            'SELECT id, first_name, last_name, ' . $aliasExpr . ' AS alias_name FROM ' . $table
            . ' WHERE tenant_id = :t' . $extra . ' AND id IN (' . implode(', ', $marks) . ')',
            $params
        ) as $r) {
            $out[(int) $r['id']] = [
                'first' => trim((string) ($r['first_name'] ?? '')),
                'last' => trim((string) ($r['last_name'] ?? '')),
                'alias' => trim((string) ($r['alias_name'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function ids(array $ids, int $max): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));

        return array_slice($ids, 0, $max);
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     */
    private function timeRange(array &$where, array &$params, string $column, ?string $since, ?string $until): void
    {
        if ($since !== null) {
            $where[] = $column . ' >= :since';
            $params['since'] = $since;
        }
        if ($until !== null) {
            $where[] = $column . ' <= :until';
            $params['until'] = $until;
        }
    }

    /**
     * @param list<string> $where
     * @param array<string, mixed> $params
     * @param array{0: float, 1: float, 2: float, 3: float}|null $bbox
     */
    private function boxRange(array &$where, array &$params, string $xCol, string $yCol, ?array $bbox): void
    {
        if ($bbox === null) {
            return;
        }
        $where[] = $xCol . ' BETWEEN :bx0 AND :bx1';
        $where[] = $yCol . ' BETWEEN :by0 AND :by1';
        $params['bx0'] = $bbox[0];
        $params['by0'] = $bbox[1];
        $params['bx1'] = $bbox[2];
        $params['by1'] = $bbox[3];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function all(string $sql, array $params): array
    {
        try {
            return array_values($this->db->fetchAll($sql, $params));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        try {
            return $this->db->fetchOne($sql, $params);
        } catch (\Throwable) {
            return null;
        }
    }
}
