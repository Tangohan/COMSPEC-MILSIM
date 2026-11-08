<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseIdWorkshopCatalog;

/**
 * Profils d'IA du Bureau SSE : un profil pointe vers une identité du registre (une seule source pour le nom et les papiers)
 * et ajoute ce qui ne concerne que l'IA en jeu (camp, catégorie, comportement au contrôle, kit, notes).
 * Éléments gardés (« placements ») : ce qu'un Zeus garde d'une partie à l'autre, par communauté et par carte.
 * Toutes les requêtes sont bornées à la communauté.
 */
final class SseAiProfileRepository
{
    public const SIDES = ['civ', 'west', 'east', 'guer'];
    public const CATEGORIES = ['civilian', 'military', 'suspect', 'hvt'];
    public const PAPERS = ['identity', 'auto', 'none'];
    public const BEHAVIOURS = ['auto', 'coopere', 'nerveux', 'ment', 'refuse', 'fuit', 'agressif', 'rendu'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    public static function normalize(array $d): array
    {
        $pick = static function (mixed $v, array $allowed): string {
            $v = is_string($v) ? trim($v) : '';

            return in_array($v, $allowed, true) ? $v : $allowed[0];
        };
        $text = static function (mixed $v, int $max): ?string {
            $v = mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);

            return $v === '' ? null : $v;
        };
        $identity = is_numeric($d['identity_id'] ?? null) ? (int) $d['identity_id'] : 0;
        $context = is_numeric($d['context_id'] ?? null) ? (int) $d['context_id'] : 0;

        return [
            'identity_id' => $identity > 0 ? $identity : null,
            'context_id' => $context > 0 ? $context : null,
            'op_key' => $text($d['op_key'] ?? null, 64),
            'label' => $text($d['label'] ?? null, 120) ?? 'Profil sans nom',
            'side' => $pick($d['side'] ?? '', self::SIDES),
            'category' => $pick($d['category'] ?? '', self::CATEGORIES),
            'faction' => $text($d['faction'] ?? null, 120),
            'papers' => $pick($d['papers'] ?? '', self::PAPERS),
            'behaviour' => $pick($d['behaviour'] ?? '', self::BEHAVIOURS),
            'kit' => $text($d['kit'] ?? null, 190),
            'notes' => $text($d['notes'] ?? null, 4000),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $by): int
    {
        $r = self::normalize($data);
        $now = gmdate('Y-m-d H:i:s');

        return $this->db->insert(
            'INSERT INTO sse_ai_profiles (tenant_id, identity_id, context_id, op_key, label, side, category, faction, papers, behaviour, kit, notes, created_by_user_id, created_at, updated_at)
             VALUES (:t, :identity, :ctx, :op, :label, :side, :category, :faction, :papers, :behaviour, :kit, :notes, :by, :now, :now2)',
            ['t' => $tenantId, 'identity' => $r['identity_id'], 'ctx' => $r['context_id'], 'op' => $r['op_key'], 'label' => $r['label'], 'side' => $r['side'], 'category' => $r['category'],
                'faction' => $r['faction'], 'papers' => $r['papers'], 'behaviour' => $r['behaviour'], 'kit' => $r['kit'], 'notes' => $r['notes'], 'by' => $by, 'now' => $now, 'now2' => $now]
        );
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $r = self::normalize($data);

        return $this->db->execute(
            'UPDATE sse_ai_profiles SET identity_id = :identity, context_id = :ctx, op_key = :op, label = :label, side = :side, category = :category, faction = :faction,
                papers = :papers, behaviour = :behaviour, kit = :kit, notes = :notes, updated_at = :now
             WHERE id = :id AND tenant_id = :t AND archived_at IS NULL',
            ['identity' => $r['identity_id'], 'ctx' => $r['context_id'], 'op' => $r['op_key'], 'label' => $r['label'], 'side' => $r['side'], 'category' => $r['category'], 'faction' => $r['faction'],
                'papers' => $r['papers'], 'behaviour' => $r['behaviour'], 'kit' => $r['kit'], 'notes' => $r['notes'], 'now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    public function archive(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'UPDATE sse_ai_profiles SET archived_at = :now WHERE id = :id AND tenant_id = :t AND archived_at IS NULL',
            ['now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    public function duplicate(int $id, int $tenantId, ?int $by): ?int
    {
        $row = $this->find($id, $tenantId);
        if ($row === null) {
            return null;
        }
        $row['label'] = mb_substr('Copie de ' . $row['label'], 0, 120);

        return $this->create($tenantId, $row, $by);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_ai_profiles WHERE id = :id AND tenant_id = :t AND archived_at IS NULL', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param array{q?: string, side?: string, category?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, array $filters = [], int $limit = 300): array
    {
        $where = ['p.tenant_id = :t', 'p.archived_at IS NULL'];
        $params = ['t' => $tenantId];
        foreach (['side', 'category'] as $f) {
            if (($filters[$f] ?? '') !== '') {
                $where[] = "p.{$f} = :{$f}";
                $params[$f] = $filters[$f];
            }
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.label LIKE :q1 OR i.last_name LIKE :q2 OR i.first_name LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
        }
        $rows = $this->db->fetchAll(
            'SELECT p.*, i.last_name, i.first_name, i.kind AS identity_kind,
                (SELECT COUNT(*) FROM sse_id_documents d WHERE d.identity_id = p.identity_id AND d.tenant_id = p.tenant_id) AS documents_count
             FROM sse_ai_profiles p
             LEFT JOIN sse_identities i ON i.id = p.identity_id AND i.tenant_id = p.tenant_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.updated_at DESC, p.id DESC LIMIT ' . max(1, min(500, $limit)),
            $params
        );

        return array_map([self::class, 'hydrate'], $rows);
    }

    /** @return list<array<string, mixed>> profils reliés à une identité */
    public function forIdentity(int $tenantId, int $identityId): array
    {
        return array_map([self::class, 'hydrate'], $this->db->fetchAll(
            'SELECT * FROM sse_ai_profiles WHERE tenant_id = :t AND identity_id = :i AND archived_at IS NULL ORDER BY id',
            ['t' => $tenantId, 'i' => $identityId]
        ));
    }

    /**
     * Profils du paquet Identity : mêmes règles de portée que la population (carte NULL ou égale, opération NULL ou égale).
     *
     * @return list<array<string, mixed>>
     */
    public function forPack(int $tenantId, int $mapId, ?string $opKey, int $limit = 300): array
    {
        $params = ['t' => $tenantId, 'pmap' => $mapId];
        $scope = 'p.op_key IS NULL';
        if ($opKey !== null && $opKey !== '') {
            $scope = '(p.op_key IS NULL OR p.op_key = :pop)';
            $params['pop'] = $opKey;
        }
        $rows = $this->db->fetchAll(
            "SELECT p.id, p.identity_id, p.label, p.side, p.category, p.faction, p.papers, p.behaviour, p.kit, i.last_name, i.first_name, d.id AS document_id
             FROM sse_ai_profiles p
             LEFT JOIN sse_identities i ON i.id = p.identity_id AND i.tenant_id = p.tenant_id
             LEFT JOIN sse_id_documents d ON d.identity_id = p.identity_id AND d.tenant_id = p.tenant_id AND d.workflow = 'issued'
             WHERE p.tenant_id = :t AND p.archived_at IS NULL AND (p.context_id IS NULL OR p.context_id = :pmap) AND {$scope}
             ORDER BY p.id, d.id",
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (!isset($out[$id])) {
                if (count($out) >= $limit) {
                    break;
                }
                $out[$id] = [
                    'id' => $id, 'ref' => self::ref($id), 'label' => (string) $r['label'], 'identity_id' => (int) ($r['identity_id'] ?? 0),
                    'name' => trim(((string) ($r['first_name'] ?? '')) . ' ' . ((string) ($r['last_name'] ?? ''))),
                    'side' => (string) $r['side'], 'category' => (string) $r['category'], 'faction' => (string) ($r['faction'] ?? ''),
                    'papers' => (string) $r['papers'], 'behaviour' => (string) $r['behaviour'], 'kit' => (string) ($r['kit'] ?? ''), 'documents' => [],
                ];
            }
            if (($r['document_id'] ?? null) !== null) {
                $out[$id]['documents'][] = (int) $r['document_id'];
            }
        }

        return array_values($out);
    }

    // ------------------------------------------------------------------ Éléments gardés

    /** @param array<string, mixed> $p */
    public function addPlacement(int $tenantId, int $mapId, ?string $opKey, array $p, ?int $by): int
    {
        return $this->db->insert(
            'INSERT INTO sse_ai_placements (tenant_id, context_id, op_key, kind, profile_id, var_name, class_name, pos_x, pos_y, label, config_json, created_by_user_id, created_at)
             VALUES (:t, :ctx, :op, :kind, :profile, :var, :cls, :x, :y, :label, :cfg, :by, :now)',
            [
                't' => $tenantId, 'ctx' => $mapId, 'op' => ($opKey === null || $opKey === '') ? null : mb_substr($opKey, 0, 64),
                'kind' => ($p['kind'] ?? '') === 'item' ? 'item' : 'ai',
                'profile' => is_numeric($p['profile_id'] ?? null) && (int) $p['profile_id'] > 0 ? (int) $p['profile_id'] : null,
                'var' => self::line($p['var_name'] ?? '', 64), 'cls' => self::line($p['class_name'] ?? '', 120),
                'x' => is_numeric($p['x'] ?? null) ? (float) $p['x'] : 0.0, 'y' => is_numeric($p['y'] ?? null) ? (float) $p['y'] : 0.0,
                'label' => self::line($p['label'] ?? '', 190) ?? 'Élément gardé',
                'cfg' => json_encode($p['config'] ?? [], JSON_UNESCAPED_UNICODE), 'by' => $by, 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    public function removePlacement(int $id, int $tenantId): bool
    {
        return $this->db->execute('DELETE FROM sse_ai_placements WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function placementsForPack(int $tenantId, int $mapId, ?string $opKey, int $limit = 300): array
    {
        $params = ['t' => $tenantId, 'pmap' => $mapId];
        $scope = 'op_key IS NULL';
        if ($opKey !== null && $opKey !== '') {
            $scope = '(op_key IS NULL OR op_key = :pop)';
            $params['pop'] = $opKey;
        }
        $rows = $this->db->fetchAll(
            "SELECT id, kind, profile_id, var_name, class_name, pos_x, pos_y, label, config_json FROM sse_ai_placements
             WHERE tenant_id = :t AND context_id = :pmap AND {$scope} ORDER BY id LIMIT " . max(1, min(500, $limit)),
            $params
        );

        return array_map(static function (array $r): array {
            $cfg = json_decode((string) ($r['config_json'] ?? ''), true);

            return [
                'id' => (int) $r['id'], 'kind' => (string) $r['kind'], 'profile_id' => (int) ($r['profile_id'] ?? 0), 'var_name' => (string) ($r['var_name'] ?? ''),
                'class_name' => (string) ($r['class_name'] ?? ''), 'x' => (float) $r['pos_x'], 'y' => (float) $r['pos_y'], 'label' => (string) $r['label'],
                'config' => is_array($cfg) ? $cfg : [],
            ];
        }, $rows);
    }

    public static function ref(int $id): string
    {
        return sprintf('PRF-%04d', $id);
    }

    private static function line(mixed $v, int $max): ?string
    {
        $v = mb_substr(trim(is_scalar($v) ? (string) preg_replace('/\s+/u', ' ', (string) $v) : ''), 0, $max);

        return $v === '' ? null : $v;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['ref'] = self::ref($row['id']);
        $row['identity_id'] = (int) ($row['identity_id'] ?? 0);
        $row['context_id'] = isset($row['context_id']) ? (int) $row['context_id'] : null;
        $row['side_label'] = SseIdWorkshopCatalog::SIDES[$row['side'] ?? ''] ?? (string) ($row['side'] ?? '');

        return $row;
    }
}
