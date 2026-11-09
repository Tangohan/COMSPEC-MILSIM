<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Historique des personnes sans compte (roster_fiche_events). Toute lecture et écriture filtre sur la communauté.
 * Si la table n'existe pas encore (migration non lancée), les lectures renvoient du vide et l'écriture est ignorée.
 */
class RosterFicheEventRepository
{
    public const EVENTS = ['created', 'edited', 'invited', 'merged', 'imported', 'note'];
    public const SOURCES = ['manual', 'import', 'invite', 'legacy'];

    private ?PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function record(int $tenantId, int $userId, ?int $actorId, string $event, string $source = '', string $detail = ''): void
    {
        if ($tenantId < 1 || $userId < 1 || !in_array($event, self::EVENTS, true)) {
            return;
        }
        $source = in_array($source, self::SOURCES, true) ? $source : '';
        try {
            $st = $this->pdo()->prepare(
                'INSERT INTO roster_fiche_events (tenant_id, user_id, actor_user_id, event, source, detail, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $st->execute([$tenantId, $userId, $actorId !== null && $actorId > 0 ? $actorId : null, $event, $source, mb_substr(trim($detail), 0, 255, 'UTF-8')]);
        } catch (\Throwable $e) {
            error_log('[roster-fiche] historique non écrit : ' . $e->getMessage());
        }
    }

    /** @return list<array{event: string, source: string, detail: string, created_at: string, actor_name: string}> */
    public function listForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if ($tenantId < 1 || $userId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        try {
            $st = $this->pdo()->prepare(
                "SELECT e.event, e.source, e.detail, e.created_at, COALESCE(NULLIF(TRIM(a.display_name), ''), NULLIF(TRIM(a.callsign), ''), '') AS actor_name
                 FROM roster_fiche_events e
                 LEFT JOIN users a ON a.id = e.actor_user_id
                 WHERE e.tenant_id = ? AND e.user_id = ?
                 ORDER BY e.id DESC LIMIT {$limit}"
            );
            $st->execute([$tenantId, $userId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $r): array => [
            'event' => (string) $r['event'],
            'source' => (string) $r['source'],
            'detail' => (string) $r['detail'],
            'created_at' => (string) $r['created_at'],
            'actor_name' => (string) $r['actor_name'],
        ], $rows);
    }

    /**
     * Source d'origine (événement « created » ou « imported ») et dernière activité, par personne.
     *
     * @param list<int> $userIds
     * @return array<int, array{source: string, last_at: string}>
     */
    public function summaryForUsers(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($tenantId < 1 || $userIds === []) {
            return [];
        }
        $out = [];
        try {
            foreach (array_chunk($userIds, 500) as $chunk) {
                $in = implode(',', array_fill(0, count($chunk), '?'));
                $st = $this->pdo()->prepare(
                    "SELECT user_id, MAX(created_at) AS last_at,
                            MAX(CASE WHEN event IN ('created', 'imported') THEN source ELSE NULL END) AS origin
                     FROM roster_fiche_events
                     WHERE tenant_id = ? AND user_id IN ({$in})
                     GROUP BY user_id"
                );
                $st->execute(array_merge([$tenantId], $chunk));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $out[(int) $r['user_id']] = ['source' => (string) ($r['origin'] ?? ''), 'last_at' => (string) $r['last_at']];
                }
            }
        } catch (\Throwable) {
            return [];
        }

        return $out;
    }
}
