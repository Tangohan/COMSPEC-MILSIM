<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\PlayerSessionRepository;

/**
 * Rattrapage des sessions de jeu à partir de l'historique existant (segments de présence des sessions de mission) :
 * outil tools/rebuild-player-sessions.php. Essai à blanc par défaut ; --apply écrit par lots. Rejouable sans doublon :
 * une session retrouvée par son Steam et son début n'est pas dupliquée. Les sessions courtes sont écrites aussi
 * (historique) : seul le seuil de lecture décide de ce qui compte.
 */
final class PlayerSessionRebuildService
{
    public function __construct(private PlayerSessionRepository $sessions) {}

    /**
     * @param callable(string): void|null $log
     * @return array{tenant_id: int, players: int, sessions: int, qualifying: int, inserted: int, updated: int, unchanged: int, applied: bool, ready: bool}
     */
    public function run(int $tenantId, bool $apply, int $gapSeconds, int $minSeconds, int $batchSize = 200, ?int $sinceTs = null, ?callable $log = null): array
    {
        $stats = ['tenant_id' => $tenantId, 'players' => 0, 'sessions' => 0, 'qualifying' => 0, 'inserted' => 0, 'updated' => 0, 'unchanged' => 0, 'applied' => $apply, 'ready' => $this->sessions->schemaReady()];
        if (!$stats['ready']) {
            if ($log !== null) {
                $log("La table player_sessions est absente : lancez d'abord l'étape de migration player_sessions.\n");
            }

            return $stats;
        }
        $events = $this->sessions->historicalEvents($tenantId, $sinceTs);
        $stats['players'] = count($events);
        $batchSize = max(1, $batchSize);
        $done = 0;
        foreach ($events as $steam => $pack) {
            foreach (PlayerSessionService::buildSessions($pack['events'], $gapSeconds, null) as $s) {
                $stats['sessions']++;
                if (PlayerSessionService::qualifies($s, $minSeconds)) {
                    $stats['qualifying']++;
                }
                $res = $this->sessions->upsertSession(
                    $tenantId, (string) $steam, $pack['user_id'] ?? null, $s['started_at'], $s['last_seen_at'], $s['ended_at'],
                    $s['duration_s'], $s['aberrant'], 'backfill', $s['server_id'], !$apply
                );
                $stats[$res]++;
                if ($apply && ++$done % $batchSize === 0 && $log !== null) {
                    $log('  lot : ' . $done . " sessions traitées\n");
                }
            }
        }

        return $stats;
    }
}
