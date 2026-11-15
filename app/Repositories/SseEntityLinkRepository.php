<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseCerbere;
use App\Support\SseLinkCatalog;

/**
 * Liens de provenance entre éléments du portail SSE (table sse_entity_links, étape sse_entity_links).
 *
 * Toute requête porte la communauté ; un couple d'éléments n'a qu'un lien (clé unique). Sans la table, ready() est faux :
 * le widget « Liaisons » passe en mode dégradé (liens déduits des colonnes existantes) et rien n'est écrit.
 */
final class SseEntityLinkRepository
{
    private static ?\WeakMap $readyByConnection = null;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** La table de l'étape sse_entity_links existe-t-elle ? Résultat positif gardé par connexion. */
    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_entity_links WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    /** Oublie le résultat gardé (tests qui créent la table après coup). */
    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /**
     * Enregistre un lien. Un couple déjà lié (dans un sens ou dans l'autre) n'est jamais doublé : 0 est rendu.
     *
     * @return int identifiant du lien créé, 0 si le couple était déjà lié ou si la ligne est invalide
     */
    public function add(
        int $tenantId,
        string $sourceType,
        string $sourceId,
        string $targetType,
        string $targetId,
        string $relation,
        string $reason,
        ?string $ruleCode,
        string $createdBy,
        ?int $userId = null,
        ?string $at = null
    ): int {
        if ($tenantId < 1 || !SseLinkCatalog::hasType($sourceType) || !SseLinkCatalog::hasType($targetType)
            || $sourceId === '' || $targetId === '' || ($sourceType === $targetType && $sourceId === $targetId)) {
            return 0;
        }
        if (!isset(SseLinkCatalog::RELATIONS[$relation])) {
            $relation = 'related';
        }
        if ($this->pair($tenantId, $sourceType, $sourceId, $targetType, $targetId) !== null) {
            return 0;
        }
        $createdBy = $createdBy === SseCerbere::ACTOR_USER ? SseCerbere::ACTOR_USER : SseCerbere::ACTOR_SYSTEM;
        try {
            return $this->db->insert(
                'INSERT INTO sse_entity_links (tenant_id, source_type, source_id, target_type, target_id, relation, reason, rule_code, created_by, created_by_user_id, created_at)
                 VALUES (:t, :st, :si, :tt, :ti, :rel, :reason, :rule, :by, :uid, :at)',
                [
                    't' => $tenantId, 'st' => $sourceType, 'si' => mb_substr($sourceId, 0, 40), 'tt' => $targetType, 'ti' => mb_substr($targetId, 0, 40),
                    'rel' => $relation, 'reason' => mb_substr(trim($reason), 0, SseLinkCatalog::MANUAL_REASON_MAX),
                    'rule' => $ruleCode !== null ? mb_substr($ruleCode, 0, 64) : null,
                    'by' => $createdBy, 'uid' => $userId !== null && $userId > 0 ? $userId : null,
                    'at' => $at ?? gmdate('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            // Course avec un autre enregistrement du même couple (clé unique) : le lien existe, c'est le but.
            return 0;
        }
    }

    /**
     * Le lien entre ces deux éléments, dans un sens ou dans l'autre.
     *
     * @return array<string, mixed>|null
     */
    public function pair(int $tenantId, string $aType, string $aId, string $bType, string $bId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sse_entity_links WHERE tenant_id = :t AND ((source_type = :a AND source_id = :ai AND target_type = :b AND target_id = :bi)
                OR (source_type = :b2 AND source_id = :bi2 AND target_type = :a2 AND target_id = :ai2)) LIMIT 1',
            ['t' => $tenantId, 'a' => $aType, 'ai' => $aId, 'b' => $bType, 'bi' => $bId, 'b2' => $bType, 'bi2' => $bId, 'a2' => $aType, 'ai2' => $aId]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM sse_entity_links WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);
    }

    public function delete(int $tenantId, int $id): bool
    {
        return $this->db->execute('DELETE FROM sse_entity_links WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]) > 0;
    }

    /**
     * Les liens qui touchent un ensemble d'éléments (une requête pour tous les types d'un niveau du parcours).
     *
     * @param array<string, list<string>> $frontier type => identifiants
     * @return list<array<string, mixed>>
     */
    public function touching(int $tenantId, array $frontier, int $limit = SseLinkCatalog::QUERY_LIMIT): array
    {
        $params = ['t' => $tenantId];
        $clauses = [];
        $n = 0;
        foreach ($frontier as $type => $ids) {
            $ids = array_values(array_unique(array_map('strval', $ids)));
            if ($ids === [] || !SseLinkCatalog::hasType((string) $type)) {
                continue;
            }
            foreach (array_chunk($ids, 80) as $chunk) {
                // Chaque valeur a son propre paramètre : un même nom utilisé deux fois est refusé quand l'émulation est coupée.
                $marks = [];
                $marks2 = [];
                foreach ($chunk as $id) {
                    $key = 'i' . $n++;
                    $key2 = 'i' . $n++;
                    $params[$key] = $id;
                    $params[$key2] = $id;
                    $marks[] = ':' . $key;
                    $marks2[] = ':' . $key2;
                }
                $tk = 'ty' . $n++;
                $tk2 = 'ty' . $n++;
                $params[$tk] = $type;
                $params[$tk2] = $type;
                $clauses[] = "(source_type = :{$tk} AND source_id IN (" . implode(',', $marks) . ")) OR (target_type = :{$tk2} AND target_id IN (" . implode(',', $marks2) . '))';
            }
        }
        if ($clauses === []) {
            return [];
        }
        $limit = max(1, min(1000, $limit));

        return $this->db->fetchAll(
            'SELECT * FROM sse_entity_links WHERE tenant_id = :t AND (' . implode(' OR ', $clauses) . ') ORDER BY created_at ASC, id ASC LIMIT ' . $limit,
            $params
        );
    }

    /**
     * Compte des liens posés par un traitement, par règle (page « Cerbères »).
     *
     * @return array<string, array{n: int, last: ?string}>
     */
    public function countsByRule(int $tenantId): array
    {
        $out = [];
        foreach ($this->db->fetchAll(
            "SELECT rule_code, COUNT(*) AS n, MAX(created_at) AS last_at FROM sse_entity_links WHERE tenant_id = :t AND rule_code LIKE 'cerbere.%' GROUP BY rule_code",
            ['t' => $tenantId]
        ) as $row) {
            $out[(string) $row['rule_code']] = ['n' => (int) $row['n'], 'last' => $row['last_at'] !== null ? (string) $row['last_at'] : null];
        }

        return $out;
    }

    /**
     * Derniers liens posés par un traitement.
     *
     * @return list<array<string, mixed>>
     */
    public function recentCerbere(int $tenantId, int $limit = 12): array
    {
        $limit = max(1, min(50, $limit));

        return $this->db->fetchAll(
            "SELECT * FROM sse_entity_links WHERE tenant_id = :t AND rule_code LIKE 'cerbere.%' ORDER BY id DESC LIMIT " . $limit,
            ['t' => $tenantId]
        );
    }
}
