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
     * Kits envoyés au serveur de jeu, complets : kits composés, puis kit automatique (portée « auto ») des membres qui
     * n'en ont aucun. Sans kit automatique, un membre dont l'identité et les papiers sont émis sur Athena arrivait en jeu
     * sans papier, et la fiche du jeu montrait l'identité générée par le mod (cause du décalage Athena / jeu).
     *
     * Papier retenu : émis, valable (ni révoqué, ni suspendu), non déclaré volé ou perdu, de cette carte et de cette
     * opération ; un seul par type et par identité (le plus récent). Identité réelle = principale (civile la plus ancienne,
     * comme la fiche) ; couverture = celle de la fiche de couverture ($coverIdentityByUser), sinon la première identité
     * de couverture du membre qui a un papier retenu.
     *
     * $realWithCover (réglage « Transmettre aussi l'identité réelle avec la couverture ») :
     *  - kit automatique : oui → papiers réels + papiers de couverture ; non → couverture seule si une fiche de couverture
     *    l'attribue, sinon identité réelle seule ;
     *  - kit composé qui contient une couverture sans aucun papier de l'identité réelle : oui → les papiers réels sont
     *    ajoutés ; non → tel quel.
     * Le jeu reconnaît le rôle de chaque papier (réel / couverture) par la nature de son identité, dans le paquet.
     *
     * @param list<array{uid: string, user_id: int, scope: string, documents: list<int>}> $composed forPack()
     * @param array<int, int> $coverIdentityByUser user_id → identité de couverture (briefs du pack)
     * @return list<array{uid: string, user_id: int, scope: string, documents: list<int>}>
     */
    public function completeForPack(int $tenantId, int $mapId, ?string $opKey, array $composed, array $coverIdentityByUser = [], bool $realWithCover = true): array
    {
        $params = ['t' => $tenantId, 'map' => $mapId];
        $docOpSql = 'd.op_key IS NULL';
        if ($opKey !== null && $opKey !== '') {
            $docOpSql = '(d.op_key IS NULL OR d.op_key = :op)';
            $params['op'] = $opKey;
        }
        $rows = $this->db->fetchAll(
            "SELECT i.user_id, i.id AS identity_id, i.kind, d.id AS document_id, d.doc_type, d.st_validity, d.st_report, u.steam_id,
                    (SELECT COUNT(*) FROM sse_id_kits k WHERE k.tenant_id = i.tenant_id AND k.user_id = i.user_id) AS kit_rows
             FROM sse_identities i
             JOIN sse_id_documents d ON d.identity_id = i.id AND d.tenant_id = i.tenant_id
             JOIN users u ON u.id = i.user_id
             WHERE i.tenant_id = :t AND i.user_id IS NOT NULL AND d.workflow = 'issued'
               AND (d.context_id IS NULL OR d.context_id = :map) AND {$docOpSql}
             ORDER BY i.user_id, i.id, d.id",
            $params
        );
        $primaryByUser = [];
        foreach ($this->db->fetchAll(
            "SELECT user_id, MIN(id) AS id FROM sse_identities WHERE tenant_id = :t AND kind = 'civil' AND user_id IS NOT NULL GROUP BY user_id",
            ['t' => $tenantId]
        ) as $r) {
            $primaryByUser[(int) $r['user_id']] = (int) $r['id'];
        }
        $identityOfDoc = [];
        $users = [];
        foreach ($rows as $r) {
            $userId = (int) $r['user_id'];
            $identityOfDoc[(int) $r['document_id']] = [(int) $r['identity_id'], (string) $r['kind']];
            $users[$userId]['uid'] = (string) preg_replace('/\D/', '', (string) ($r['steam_id'] ?? ''));
            $users[$userId]['kit_rows'] = (int) $r['kit_rows'];
            $usable = !in_array((string) ($r['st_validity'] ?? 'valid'), ['revoked', 'suspended'], true)
                && !in_array((string) ($r['st_report'] ?? 'none'), ['stolen', 'lost'], true);
            if ($usable) {
                // Le plus récent par type écrase le précédent (ORDER BY d.id).
                $users[$userId]['ids'][(int) $r['identity_id']][(string) $r['doc_type']] = (int) $r['document_id'];
                if ((string) $r['kind'] === 'cover') {
                    $users[$userId]['covers'][(int) $r['identity_id']] = true;
                }
            }
        }
        $real = static fn (int $userId): array => array_values($users[$userId]['ids'][$primaryByUser[$userId] ?? 0] ?? []);

        $out = [];
        $served = [];
        foreach ($composed as $kit) {
            $userId = (int) $kit['user_id'];
            $served[$userId] = true;
            if ($realWithCover && isset($primaryByUser[$userId])) {
                $hasCover = false;
                $hasReal = false;
                foreach ($kit['documents'] as $d) {
                    [$iid, $kind] = $identityOfDoc[(int) $d] ?? [0, ''];
                    $hasCover = $hasCover || in_array($kind, ['cover', 'alias'], true);
                    $hasReal = $hasReal || $iid === $primaryByUser[$userId];
                }
                if ($hasCover && !$hasReal) {
                    $kit['documents'] = array_values(array_unique(array_merge($kit['documents'], $real($userId))));
                }
            }
            $out[] = $kit;
        }
        foreach ($users as $userId => $u) {
            if (isset($served[$userId]) || $u['kit_rows'] > 0 || $u['uid'] === '') {
                continue;
            }
            $brief = (int) ($coverIdentityByUser[$userId] ?? 0);
            $cover = $brief > 0 && isset($u['ids'][$brief]) ? $brief : (int) (array_key_first($u['covers'] ?? []) ?? 0);
            $coverDocs = $cover > 0 ? array_values($u['ids'][$cover] ?? []) : [];
            $briefCover = $brief > 0 && $coverDocs !== [];
            $docs = match (true) {
                $realWithCover => array_merge($real($userId), $coverDocs),
                $briefCover => $coverDocs,
                default => $real($userId),
            };
            if ($docs === []) {
                continue;
            }
            $out[] = ['uid' => $u['uid'], 'user_id' => $userId, 'scope' => 'auto', 'documents' => array_values(array_unique($docs))];
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
