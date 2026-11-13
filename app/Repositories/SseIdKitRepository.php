<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * COMSPEC IDENTITY — kit de papiers qu'un joueur porte en mission.
 *
 * Une ligne = un papier dans le kit d'un membre pour une portée : par défaut (carte NULL, opération NULL), pour une
 * carte (context_id) ou pour une opération (op_key, éventuellement sur une carte). Au chargement d'une partie, la
 * portée la plus précise qui contient au moins un papier l'emporte : opération > carte > défaut.
 *
 * Le serveur de jeu reçoit ces kits dans le pack (kits[]) avec le Steam ID du compte comme clé de liaison
 * (getPlayerUID côté Arma).
 */
final class SseIdKitRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    /**
     * Remplace le kit d'un membre pour une portée (liste vide = portée retirée). Seuls les papiers rattachés à une
     * identité du membre sont retenus.
     *
     * @param list<int> $documentIds
     * @return int nombre de papiers retenus
     */
    public function replace(int $tenantId, int $userId, ?int $contextId, ?string $opKey, array $documentIds, ?int $by = null): int
    {
        $contextId = $contextId !== null && $contextId > 0 ? $contextId : null;
        $opKey = $opKey !== null && trim($opKey) !== '' ? mb_substr(trim($opKey), 0, 64) : null;
        [$scopeSql, $params] = self::scopeWhere($contextId, $opKey);
        $this->db->execute(
            'DELETE FROM sse_id_kits WHERE tenant_id = :t AND user_id = :u AND ' . $scopeSql,
            ['t' => $tenantId, 'u' => $userId] + $params
        );
        $ids = array_values(array_unique(array_filter(array_map('intval', $documentIds), static fn (int $i): bool => $i > 0)));
        $kept = 0;
        foreach (array_slice($ids, 0, 12) as $docId) {
            $own = $this->db->fetchOne(
                'SELECT d.id FROM sse_id_documents d JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id
                 WHERE d.id = :d AND d.tenant_id = :t AND i.user_id = :u',
                ['d' => $docId, 't' => $tenantId, 'u' => $userId]
            );
            if ($own === null) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO sse_id_kits (tenant_id, user_id, document_id, context_id, op_key, created_by_user_id, created_at)
                 VALUES (:t, :u, :d, :ctx, :op, :by, :now)',
                ['t' => $tenantId, 'u' => $userId, 'd' => $docId, 'ctx' => $contextId, 'op' => $opKey, 'by' => $by, 'now' => gmdate('Y-m-d H:i:s')]
            );
            $kept++;
        }

        return $kept;
    }

    /**
     * Kits d'un membre regroupés par portée.
     *
     * @return list<array{context_id: ?int, op_key: ?string, scope: string, document_ids: list<int>}>
     */
    public function listForUser(int $tenantId, int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT document_id, context_id, op_key FROM sse_id_kits WHERE tenant_id = :t AND user_id = :u ORDER BY id',
            ['t' => $tenantId, 'u' => $userId]
        );
        $groups = [];
        foreach ($rows as $r) {
            $ctx = ($r['context_id'] ?? null) !== null ? (int) $r['context_id'] : null;
            $op = ($r['op_key'] ?? null) !== null ? (string) $r['op_key'] : null;
            $key = ($ctx ?? 0) . '|' . ($op ?? '');
            $groups[$key] ??= ['context_id' => $ctx, 'op_key' => $op, 'scope' => self::scopeName($ctx, $op), 'document_ids' => []];
            $groups[$key]['document_ids'][] = (int) $r['document_id'];
        }

        return array_values($groups);
    }

    /**
     * Kits à envoyer au serveur de jeu pour une carte et une opération : seulement les papiers émis valables sur
     * cette carte et cette opération, portée la plus précise par joueur, joueurs dont le compte est lié à Steam.
     *
     * @return list<array{uid: string, user_id: int, scope: string, documents: list<int>}>
     */
    public function forPack(int $tenantId, int $mapId, ?string $opKey): array
    {
        $params = ['t' => $tenantId, 'map1' => $mapId, 'map2' => $mapId];
        $opSql = 'k.op_key IS NULL';
        $docOpSql = 'd.op_key IS NULL';
        if ($opKey !== null && $opKey !== '') {
            $opSql = '(k.op_key IS NULL OR k.op_key = :op1)';
            $docOpSql = '(d.op_key IS NULL OR d.op_key = :op2)';
            $params['op1'] = $params['op2'] = $opKey;
        }
        $rows = $this->db->fetchAll(
            "SELECT k.user_id, k.document_id, k.context_id, k.op_key, u.steam_id
             FROM sse_id_kits k
             JOIN sse_id_documents d ON d.id = k.document_id AND d.tenant_id = k.tenant_id
             JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id AND i.user_id = k.user_id
             JOIN users u ON u.id = k.user_id
             WHERE k.tenant_id = :t AND d.workflow = 'issued'
               AND (k.context_id IS NULL OR k.context_id = :map1) AND {$opSql}
               AND (d.context_id IS NULL OR d.context_id = :map2) AND {$docOpSql}
             ORDER BY k.user_id, k.id",
            $params
        );
        $best = [];
        foreach ($rows as $r) {
            $uid = (string) preg_replace('/\D/', '', (string) ($r['steam_id'] ?? ''));
            if ($uid === '') {
                continue;
            }
            $userId = (int) $r['user_id'];
            $ctx = ($r['context_id'] ?? null) !== null ? (int) $r['context_id'] : null;
            $op = ($r['op_key'] ?? null) !== null ? (string) $r['op_key'] : null;
            $tier = ($op !== null ? 2 : 0) + ($ctx !== null ? 1 : 0);
            if (!isset($best[$userId]) || $tier > $best[$userId]['tier']) {
                $best[$userId] = ['tier' => $tier, 'uid' => $uid, 'scope' => self::scopeName($ctx, $op), 'documents' => []];
            }
            if ($tier === $best[$userId]['tier'] && !in_array((int) $r['document_id'], $best[$userId]['documents'], true)) {
                $best[$userId]['documents'][] = (int) $r['document_id'];
            }
        }
        $out = [];
        foreach ($best as $userId => $b) {
            $out[] = ['uid' => $b['uid'], 'user_id' => $userId, 'scope' => $b['scope'], 'documents' => $b['documents']];
        }

        return $out;
    }

    /**
     * Kit automatique (portée « auto ») des membres qui n'ont aucun kit composé : sans lui, un membre dont l'identité et
     * les papiers sont émis sur Athena arrivait en jeu sans papier, et la fiche du jeu montrait l'identité générée par le
     * mod (cause du décalage Athena / jeu). Règles, toutes nécessaires :
     *  - compte lié à Steam, aucune ligne dans sse_id_kits (un kit composé, même pour une autre portée, l'emporte) ;
     *  - identité : celle de la fiche de couverture du membre pour cette carte / opération ($coverIdentityByUser, si elle
     *    est à lui et a des papiers retenus), sinon l'identité principale (civile la plus ancienne, comme la fiche) ;
     *  - papiers émis, valables (ni révoqués, ni suspendus), non déclarés volés ou perdus, de cette carte et de cette
     *    opération ; un seul papier par type (le plus récent).
     *
     * @param list<int> $exceptUserIds membres déjà servis par un kit composé
     * @param array<int, int> $coverIdentityByUser user_id → identité de couverture (briefs du pack)
     * @return list<array{uid: string, user_id: int, scope: string, documents: list<int>}>
     */
    public function autoForPack(int $tenantId, int $mapId, ?string $opKey, array $exceptUserIds = [], array $coverIdentityByUser = []): array
    {
        $params = ['t' => $tenantId, 't2' => $tenantId, 'map' => $mapId];
        $docOpSql = 'd.op_key IS NULL';
        if ($opKey !== null && $opKey !== '') {
            $docOpSql = '(d.op_key IS NULL OR d.op_key = :op)';
            $params['op'] = $opKey;
        }
        $rows = $this->db->fetchAll(
            "SELECT i.user_id, i.id AS identity_id, i.kind, d.id AS document_id, d.doc_type, u.steam_id
             FROM sse_identities i
             JOIN sse_id_documents d ON d.identity_id = i.id AND d.tenant_id = i.tenant_id
             JOIN users u ON u.id = i.user_id
             WHERE i.tenant_id = :t AND i.user_id IS NOT NULL AND d.workflow = 'issued'
               AND COALESCE(d.st_validity, 'valid') NOT IN ('revoked', 'suspended')
               AND COALESCE(d.st_report, 'none') NOT IN ('stolen', 'lost')
               AND (d.context_id IS NULL OR d.context_id = :map) AND {$docOpSql}
               AND NOT EXISTS (SELECT 1 FROM sse_id_kits k WHERE k.tenant_id = :t2 AND k.user_id = i.user_id)
             ORDER BY i.user_id, i.id, d.id",
            $params
        );
        $except = array_flip(array_map('intval', $exceptUserIds));
        // Identité principale = civile la plus ancienne du membre (même règle que SseIdentityRepository::listForUser).
        $primaryByUser = [];
        foreach ($this->db->fetchAll(
            "SELECT user_id, MIN(id) AS id FROM sse_identities WHERE tenant_id = :t AND kind = 'civil' AND user_id IS NOT NULL GROUP BY user_id",
            ['t' => $tenantId]
        ) as $r) {
            $primaryByUser[(int) $r['user_id']] = (int) $r['id'];
        }
        $byUser = [];
        foreach ($rows as $r) {
            $userId = (int) $r['user_id'];
            $uid = (string) preg_replace('/\D/', '', (string) ($r['steam_id'] ?? ''));
            if ($uid === '' || isset($except[$userId])) {
                continue;
            }
            $byUser[$userId]['uid'] = $uid;
            // Le plus récent par type écrase le précédent (ORDER BY d.id).
            $byUser[$userId]['ids'][(int) $r['identity_id']][(string) $r['doc_type']] = (int) $r['document_id'];
        }
        $out = [];
        foreach ($byUser as $userId => $u) {
            $cover = (int) ($coverIdentityByUser[$userId] ?? 0);
            $identityId = $cover > 0 && isset($u['ids'][$cover]) ? $cover : ($primaryByUser[$userId] ?? 0);
            $docs = $u['ids'][$identityId] ?? [];
            if ($docs === []) {
                continue;
            }
            $out[] = ['uid' => $u['uid'], 'user_id' => $userId, 'scope' => 'auto', 'documents' => array_values($docs)];
        }

        return $out;
    }

    public static function scopeName(?int $contextId, ?string $opKey): string
    {
        if ($opKey !== null && $opKey !== '') {
            return 'operation';
        }

        return $contextId !== null && $contextId > 0 ? 'map' : 'default';
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private static function scopeWhere(?int $contextId, ?string $opKey): array
    {
        $params = [];
        $sql = $contextId === null ? 'context_id IS NULL' : 'context_id = :ctx';
        if ($contextId !== null) {
            $params['ctx'] = $contextId;
        }
        $sql .= $opKey === null ? ' AND op_key IS NULL' : ' AND op_key = :op';
        if ($opKey !== null) {
            $params['op'] = $opKey;
        }

        return [$sql, $params];
    }
}
