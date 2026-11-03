<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseIdentityCatalog;
use App\Support\SseIdWorkshopCatalog;

/**
 * COMSPEC IDENTITY (Lot 6) — préparation de mission : lots de population générés, recherchés et HVT de l'opération,
 * briefs de couverture remis aux joueurs.
 *
 * Portée d'une opération : carte (context_id, NULL = toutes) et opération (op_key, NULL = hors opération). Pour le pack
 * d'une carte et d'une opération : carte NULL ou égale ; opération NULL ou égale si une opération est demandée, sinon
 * NULL seulement (même règle que les kits de papiers).
 */
final class SseIdOperationRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    // ------------------------------------------------------------------ Lots

    /**
     * @param array<string, mixed> $params paramètres de génération (gardés pour mémoire)
     * @param list<string> $visibleSides camps qui voient les recherchés et HVT du lot
     */
    public function createBatch(int $tenantId, ?int $contextId, ?string $opKey, string $label, array $params, int $seed, array $visibleSides, ?int $by): int
    {
        return $this->db->insert(
            'INSERT INTO sse_id_batches (tenant_id, context_id, op_key, label, params_json, seed, visible_sides, created_by_user_id, created_at)
             VALUES (:t, :ctx, :op, :label, :params, :seed, :sides, :by, :now)',
            [
                't' => $tenantId,
                'ctx' => self::ctx($contextId),
                'op' => self::op($opKey),
                'label' => mb_substr(trim($label), 0, 120),
                'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'seed' => max(0, $seed),
                'sides' => implode(',', SseIdWorkshopCatalog::sides($visibleSides)) ?: null,
                'by' => $by,
                'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    public function setBatchCounts(int $id, int $tenantId, int $identities, int $documents): void
    {
        $this->db->execute(
            'UPDATE sse_id_batches SET identities_count = :i, documents_count = :d WHERE id = :id AND tenant_id = :t',
            ['i' => $identities, 'd' => $documents, 'id' => $id, 't' => $tenantId]
        );
    }

    public function archiveBatch(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'UPDATE sse_id_batches SET archived_at = :now WHERE id = :id AND tenant_id = :t AND archived_at IS NULL',
            ['now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function findBatch(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_id_batches WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrateBatch($row);
    }

    /** @return list<array<string, mixed>> */
    public function listBatches(int $tenantId, ?int $contextId = null, ?string $opKey = null, bool $withArchived = true): array
    {
        [$where, $params] = self::exactScope($contextId, $opKey, $contextId !== null || $opKey !== null);
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_id_batches WHERE tenant_id = :t' . $where . ($withArchived ? '' : ' AND archived_at IS NULL') . ' ORDER BY id DESC LIMIT 100',
            ['t' => $tenantId] + $params
        );

        return array_map([self::class, 'hydrateBatch'], $rows);
    }

    /**
     * Population à appliquer aux IA pour une carte et une opération : identités des lots actifs, avec leurs papiers
     * émis. Une entrée par identité.
     *
     * @return list<array{identity_id: int, side: string, category: string, faction: string, zone: string, language: string, documents: list<int>}>
     */
    public function populationForPack(int $tenantId, int $mapId, ?string $opKey, int $limit = 500): array
    {
        [$scope, $params] = self::packScope('b', $mapId, $opKey);
        $rows = $this->db->fetchAll(
            "SELECT i.id, i.side, i.category, i.faction, i.zone, i.language, d.id AS document_id
             FROM sse_identities i
             JOIN sse_id_batches b ON b.id = i.batch_id AND b.tenant_id = i.tenant_id AND b.archived_at IS NULL
             LEFT JOIN sse_id_documents d ON d.identity_id = i.id AND d.tenant_id = i.tenant_id AND d.workflow = 'issued'
             WHERE i.tenant_id = :t AND {$scope}
             ORDER BY i.id, d.id",
            ['t' => $tenantId] + $params
        );
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (!isset($out[$id])) {
                if (count($out) >= $limit) {
                    break;
                }
                $out[$id] = [
                    'identity_id' => $id,
                    'side' => (string) ($r['side'] ?? '') ?: 'civ',
                    'category' => (string) ($r['category'] ?? '') ?: 'civilian',
                    'faction' => (string) ($r['faction'] ?? ''),
                    'zone' => (string) ($r['zone'] ?? ''),
                    'language' => (string) ($r['language'] ?? ''),
                    'documents' => [],
                ];
            }
            if (($r['document_id'] ?? null) !== null) {
                $out[$id]['documents'][] = (int) $r['document_id'];
            }
        }

        return array_values($out);
    }

    /**
     * Identités d'un lot (aperçu de l'opération).
     *
     * @return list<array<string, mixed>>
     */
    public function batchIdentities(int $tenantId, int $batchId, int $limit = 300): array
    {
        $rows = $this->db->fetchAll(
            'SELECT i.*, (SELECT COUNT(*) FROM sse_id_documents d WHERE d.identity_id = i.id AND d.tenant_id = i.tenant_id) AS documents_count
             FROM sse_identities i WHERE i.tenant_id = :t AND i.batch_id = :b ORDER BY i.id LIMIT ' . max(1, min(1000, $limit)),
            ['t' => $tenantId, 'b' => $batchId]
        );

        return array_map([SseIdentityRepository::class, 'hydrate'], $rows);
    }

    // ------------------------------------------------------------ Recherchés

    /**
     * @param list<string> $sides camps qui y ont accès (vide = tous)
     */
    public function addWanted(int $tenantId, ?int $contextId, ?string $opKey, int $identityId, string $level, string $alias, array $sides, string $notes, ?int $batchId, ?int $by): int
    {
        return $this->db->insert(
            'INSERT INTO sse_id_op_wanted (tenant_id, context_id, op_key, identity_id, level, alias, sides, notes, batch_id, created_by_user_id, created_at)
             VALUES (:t, :ctx, :op, :i, :lvl, :alias, :sides, :notes, :b, :by, :now)',
            [
                't' => $tenantId,
                'ctx' => self::ctx($contextId),
                'op' => self::op($opKey),
                'i' => $identityId,
                'lvl' => SseIdentityCatalog::pick(SseIdWorkshopCatalog::WANTED_LEVELS, $level, 'recherche'),
                'alias' => ($a = trim($alias)) !== '' ? mb_substr($a, 0, 120) : null,
                'sides' => implode(',', SseIdWorkshopCatalog::sides($sides)) ?: null,
                'notes' => ($n = trim($notes)) !== '' ? mb_substr($n, 0, 1000) : null,
                'b' => $batchId !== null && $batchId > 0 ? $batchId : null,
                'by' => $by,
                'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    public function removeWanted(int $id, int $tenantId): bool
    {
        return $this->db->execute('DELETE FROM sse_id_op_wanted WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]) > 0;
    }

    public function removeWantedOfBatch(int $batchId, int $tenantId): int
    {
        return $this->db->execute('DELETE FROM sse_id_op_wanted WHERE batch_id = :b AND tenant_id = :t', ['b' => $batchId, 't' => $tenantId]);
    }

    /**
     * Recherchés d'une opération (portée exacte), avec l'identité et ses papiers connus.
     *
     * @return list<array<string, mixed>>
     */
    public function listWanted(int $tenantId, ?int $contextId, ?string $opKey): array
    {
        [$where, $params] = self::exactScope($contextId, $opKey, true, 'w');

        return $this->wantedRows('w.tenant_id = :t' . $where, ['t' => $tenantId] + $params);
    }

    /**
     * Recherchés du pack d'une carte et d'une opération.
     *
     * @return list<array<string, mixed>>
     */
    public function wantedForPack(int $tenantId, int $mapId, ?string $opKey): array
    {
        [$scope, $params] = self::packScope('w', $mapId, $opKey);

        return $this->wantedRows('w.tenant_id = :t AND ' . $scope, ['t' => $tenantId] + $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function wantedRows(string $where, array $params): array
    {
        $rows = $this->db->fetchAll(
            'SELECT w.*, i.last_name, i.first_name, i.kind, i.photo_path, i.zone AS identity_zone, i.alias AS identity_alias, i.person_id
             FROM sse_id_op_wanted w JOIN sse_identities i ON i.id = w.identity_id AND i.tenant_id = w.tenant_id
             WHERE ' . $where . ' ORDER BY w.id LIMIT 300',
            $params
        );
        $docs = [];
        $ids = array_values(array_unique(array_map(static fn (array $r): int => (int) $r['identity_id'], $rows)));
        if ($ids !== []) {
            $marks = [];
            $p = ['t' => (int) ($params['t'] ?? 0)];
            foreach ($ids as $k => $id) {
                $marks[] = ':i' . $k;
                $p['i' . $k] = $id;
            }
            foreach ($this->db->fetchAll(
                "SELECT id, identity_id FROM sse_id_documents WHERE tenant_id = :t AND workflow = 'issued' AND identity_id IN (" . implode(', ', $marks) . ') ORDER BY id',
                $p
            ) as $d) {
                $docs[(int) $d['identity_id']][] = (int) $d['id'];
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $level = (string) $r['level'];
            $out[] = [
                'id' => (int) $r['id'],
                'ref' => 'OPW-' . (int) $r['id'],
                'context_id' => ($r['context_id'] ?? null) !== null ? (int) $r['context_id'] : null,
                'op_key' => ($r['op_key'] ?? null) !== null ? (string) $r['op_key'] : null,
                'identity_id' => (int) $r['identity_id'],
                'person_id' => ($r['person_id'] ?? null) !== null ? (int) $r['person_id'] : null,
                'kind' => (string) ($r['kind'] ?? ''),
                'last_name' => (string) ($r['last_name'] ?? ''),
                'first_name' => (string) ($r['first_name'] ?? ''),
                'alias' => (string) (($r['alias'] ?? '') !== '' ? $r['alias'] : ($r['identity_alias'] ?? '')),
                'level' => $level,
                'level_label' => SseIdWorkshopCatalog::label(SseIdWorkshopCatalog::WANTED_LEVELS, $level),
                'sides' => SseIdWorkshopCatalog::sides((string) ($r['sides'] ?? '')),
                'zone' => (string) ($r['identity_zone'] ?? ''),
                'notes' => (string) ($r['notes'] ?? ''),
                'photo_path' => ($r['photo_path'] ?? null) !== null && $r['photo_path'] !== '' ? (string) $r['photo_path'] : null,
                'batch_id' => ($r['batch_id'] ?? null) !== null ? (int) $r['batch_id'] : null,
                'documents' => $docs[(int) $r['identity_id']] ?? [],
            ];
        }

        return $out;
    }

    // ----------------------------------------------------------------- Briefs

    /**
     * Enregistre le brief d'un joueur pour une portée (un seul par joueur et par portée : remplace l'existant).
     *
     * @param array<string, mixed> $data title, biography, legend, contacts, instructions, phone, address, identity_id, kit_template_id
     */
    public function saveBrief(int $tenantId, int $userId, ?int $contextId, ?string $opKey, array $data, ?int $by): int
    {
        $ctx = self::ctx($contextId);
        $op = self::op($opKey);
        $str = static function (mixed $v, int $max): ?string {
            $s = trim(is_scalar($v) ? (string) $v : '');

            return $s === '' ? null : mb_substr($s, 0, $max);
        };
        $row = [
            'title' => (string) ($str($data['title'] ?? null, 160) ?? ''),
            'bio' => $str($data['biography'] ?? null, 2000),
            'legend' => $str($data['legend'] ?? null, 2000),
            'contacts' => $str($data['contacts'] ?? null, 1000),
            'instr' => $str($data['instructions'] ?? null, 2000),
            'phone' => $str($data['phone'] ?? null, 40),
            'address' => $str($data['address'] ?? null, 255),
            'identity' => (int) ($data['identity_id'] ?? 0) > 0 ? (int) $data['identity_id'] : null,
            'kit' => (int) ($data['kit_template_id'] ?? 0) > 0 ? (int) $data['kit_template_id'] : null,
        ];
        [$scopeSql, $scopeParams] = self::exactScope($ctx, $op, true);
        $existing = $this->db->fetchOne(
            'SELECT id FROM sse_id_briefs WHERE tenant_id = :t AND user_id = :u' . $scopeSql,
            ['t' => $tenantId, 'u' => $userId] + $scopeParams
        );
        $now = gmdate('Y-m-d H:i:s');
        if ($existing !== null) {
            $this->db->execute(
                'UPDATE sse_id_briefs SET title = :title, biography = :bio, legend = :legend, contacts = :contacts, instructions = :instr,
                    phone = :phone, address = :address, identity_id = :identity, kit_template_id = :kit, updated_at = :now
                 WHERE id = :id AND tenant_id = :t',
                $row + ['now' => $now, 'id' => (int) $existing['id'], 't' => $tenantId]
            );

            return (int) $existing['id'];
        }

        return $this->db->insert(
            'INSERT INTO sse_id_briefs (tenant_id, user_id, identity_id, kit_template_id, context_id, op_key, title, biography, legend, contacts,
                instructions, phone, address, created_by_user_id, created_at)
             VALUES (:t, :u, :identity, :kit, :ctx, :op, :title, :bio, :legend, :contacts, :instr, :phone, :address, :by, :now)',
            $row + ['t' => $tenantId, 'u' => $userId, 'ctx' => $ctx, 'op' => $op, 'by' => $by, 'now' => $now]
        );
    }

    public function deleteBrief(int $id, int $tenantId): bool
    {
        return $this->db->execute('DELETE FROM sse_id_briefs WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]) > 0;
    }

    /** @return array<string, mixed>|null */
    public function findBrief(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_id_briefs WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrateBrief($row);
    }

    /** @return list<array<string, mixed>> */
    public function briefsForUser(int $tenantId, int $userId): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM sse_id_briefs WHERE tenant_id = :t AND user_id = :u ORDER BY id DESC LIMIT 50', ['t' => $tenantId, 'u' => $userId]);

        return array_map([self::class, 'hydrateBrief'], $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listBriefs(int $tenantId, ?int $contextId = null, ?string $opKey = null): array
    {
        [$where, $params] = self::exactScope($contextId, $opKey, $contextId !== null || $opKey !== null, 'b');
        $rows = $this->db->fetchAll(
            'SELECT b.*, u.display_name AS user_name FROM sse_id_briefs b LEFT JOIN users u ON u.id = b.user_id
             WHERE b.tenant_id = :t' . $where . ' ORDER BY b.id DESC LIMIT 300',
            ['t' => $tenantId] + $params
        );

        return array_map([self::class, 'hydrateBrief'], $rows);
    }

    /**
     * Briefs à envoyer au serveur de jeu : portée la plus précise par joueur (opération > carte > défaut), joueurs liés
     * à Steam seulement.
     *
     * @return list<array<string, mixed>>
     */
    public function briefsForPack(int $tenantId, int $mapId, ?string $opKey): array
    {
        [$scope, $params] = self::packScope('b', $mapId, $opKey);
        $rows = $this->db->fetchAll(
            'SELECT b.*, u.steam_id, i.last_name AS cover_last, i.first_name AS cover_first
             FROM sse_id_briefs b JOIN users u ON u.id = b.user_id
             LEFT JOIN sse_identities i ON i.id = b.identity_id AND i.tenant_id = b.tenant_id
             WHERE b.tenant_id = :t AND ' . $scope . ' ORDER BY b.user_id, b.id',
            ['t' => $tenantId] + $params
        );
        $best = [];
        foreach ($rows as $r) {
            $uid = (string) preg_replace('/\D/', '', (string) ($r['steam_id'] ?? ''));
            if ($uid === '') {
                continue;
            }
            $ctx = ($r['context_id'] ?? null) !== null ? (int) $r['context_id'] : null;
            $op = ($r['op_key'] ?? null) !== null ? (string) $r['op_key'] : null;
            $tier = ($op !== null ? 2 : 0) + ($ctx !== null ? 1 : 0);
            $userId = (int) $r['user_id'];
            if (isset($best[$userId]) && $best[$userId]['tier'] >= $tier) {
                continue;
            }
            $b = self::hydrateBrief($r);
            $best[$userId] = ['tier' => $tier, 'row' => [
                'uid' => $uid,
                'user_id' => $userId,
                'scope' => SseIdKitRepository::scopeName($ctx, $op),
                'identity_id' => $b['identity_id'],
                'title' => $b['title'],
                'cover_name' => trim(mb_strtoupper((string) ($r['cover_last'] ?? '')) . ' ' . (string) ($r['cover_first'] ?? '')),
                'biography' => self::packText($b['biography'], 1000),
                'legend' => self::packText($b['legend'], 1000),
                'contacts' => self::packText($b['contacts'], 600),
                'instructions' => self::packText($b['instructions'], 1000),
                'phone' => (string) ($b['phone'] ?? ''),
                'address' => (string) ($b['address'] ?? ''),
            ]];
        }

        return array_values(array_map(static fn (array $b): array => $b['row'], $best));
    }

    // ------------------------------------------------------------- Opérations

    /**
     * Opérations préparées (lots, recherchés, briefs) : carte et clé, la plus récente d'abord.
     *
     * @return list<array{context_id: ?int, op_key: ?string}>
     */
    public function scopes(int $tenantId): array
    {
        $out = [];
        foreach (['sse_id_batches', 'sse_id_op_wanted', 'sse_id_briefs'] as $table) {
            foreach ($this->db->fetchAll(
                'SELECT context_id, op_key, MAX(id) AS last_id FROM ' . $table . ' WHERE tenant_id = :t GROUP BY context_id, op_key ORDER BY last_id DESC LIMIT 50',
                ['t' => $tenantId]
            ) as $r) {
                $ctx = ($r['context_id'] ?? null) !== null ? (int) $r['context_id'] : null;
                $op = ($r['op_key'] ?? null) !== null ? (string) $r['op_key'] : null;
                $out[($ctx ?? 0) . '|' . ($op ?? '')] = ['context_id' => $ctx, 'op_key' => $op];
            }
        }

        return array_values($out);
    }

    // ------------------------------------------------------------------ Outils

    private static function ctx(?int $contextId): ?int
    {
        return $contextId !== null && $contextId > 0 ? $contextId : null;
    }

    private static function op(?string $opKey): ?string
    {
        return $opKey !== null && trim($opKey) !== '' ? mb_substr(trim($opKey), 0, 64) : null;
    }

    /**
     * Portée exacte (NULL = NULL) ; $apply = false : aucune contrainte.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private static function exactScope(?int $contextId, ?string $opKey, bool $apply, string $alias = ''): array
    {
        if (!$apply) {
            return ['', []];
        }
        $p = $alias !== '' ? $alias . '.' : '';
        $ctx = self::ctx($contextId);
        $op = self::op($opKey);
        $params = [];
        $sql = $ctx === null ? " AND {$p}context_id IS NULL" : " AND {$p}context_id = :sctx";
        if ($ctx !== null) {
            $params['sctx'] = $ctx;
        }
        $sql .= $op === null ? " AND {$p}op_key IS NULL" : " AND {$p}op_key = :sop";
        if ($op !== null) {
            $params['sop'] = $op;
        }

        return [$sql, $params];
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private static function packScope(string $alias, int $mapId, ?string $opKey): array
    {
        $op = self::op($opKey);
        $params = ['pmap' => $mapId];
        $sql = "({$alias}.context_id IS NULL OR {$alias}.context_id = :pmap)";
        if ($op !== null) {
            $sql .= " AND ({$alias}.op_key IS NULL OR {$alias}.op_key = :pop)";
            $params['pop'] = $op;
        } else {
            $sql .= " AND {$alias}.op_key IS NULL";
        }

        return [$sql, $params];
    }

    private static function packText(?string $s, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $s)), 0, $max);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateBatch(array $row): array
    {
        $params = json_decode((string) ($row['params_json'] ?? ''), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'ref' => 'LOT-' . str_pad((string) (int) ($row['id'] ?? 0), 4, '0', STR_PAD_LEFT),
            'context_id' => ($row['context_id'] ?? null) !== null ? (int) $row['context_id'] : null,
            'op_key' => ($row['op_key'] ?? null) !== null ? (string) $row['op_key'] : null,
            'label' => (string) ($row['label'] ?? ''),
            'params' => is_array($params) ? $params : [],
            'seed' => (int) ($row['seed'] ?? 0),
            'identities_count' => (int) ($row['identities_count'] ?? 0),
            'documents_count' => (int) ($row['documents_count'] ?? 0),
            'visible_sides' => SseIdWorkshopCatalog::sides((string) ($row['visible_sides'] ?? '')),
            'archived' => ($row['archived_at'] ?? null) !== null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateBrief(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'user_name' => (string) ($row['user_name'] ?? ''),
            'identity_id' => ($row['identity_id'] ?? null) !== null ? (int) $row['identity_id'] : null,
            'kit_template_id' => ($row['kit_template_id'] ?? null) !== null ? (int) $row['kit_template_id'] : null,
            'context_id' => ($row['context_id'] ?? null) !== null ? (int) $row['context_id'] : null,
            'op_key' => ($row['op_key'] ?? null) !== null ? (string) $row['op_key'] : null,
            'scope' => SseIdKitRepository::scopeName(($row['context_id'] ?? null) !== null ? (int) $row['context_id'] : null, ($row['op_key'] ?? null) !== null ? (string) $row['op_key'] : null),
            'title' => (string) ($row['title'] ?? ''),
            'biography' => ($row['biography'] ?? null) !== null ? (string) $row['biography'] : null,
            'legend' => ($row['legend'] ?? null) !== null ? (string) $row['legend'] : null,
            'contacts' => ($row['contacts'] ?? null) !== null ? (string) $row['contacts'] : null,
            'instructions' => ($row['instructions'] ?? null) !== null ? (string) $row['instructions'] : null,
            'phone' => ($row['phone'] ?? null) !== null ? (string) $row['phone'] : null,
            'address' => ($row['address'] ?? null) !== null ? (string) $row['address'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
