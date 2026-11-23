<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\RoleplaySessionAppRepository;
use App\Support\RoleplaySessionBoard;

/**
 * Pointage par l'animateur des joueurs ATAK connectés, et lancement de l'enregistrement de la session.
 *
 *  - pointer : une ligne de participant par membre (jamais de doublon), checked_in_at posé une seule fois, plage « check_in »
 *    des segments de présence (motif existant) ; session déjà enregistrée : la plage de temps du joueur s'ouvre aussi ;
 *  - retirer le pointage : checked_in_at effacé, temps encore ouvert fermé (« check_out ») ;
 *  - démarrer l'enregistrement : même règle que « Démarrer » (RoleplayGameSessionService::startSession), puis ouverture du
 *    temps de chaque joueur pointé ; terminer : RoleplayGameSessionService::closeSession (temps figés).
 * Chaque action est inscrite au journal de la session.
 */
final class RoleplaySessionAttendance
{
    public const MAX_BATCH = 200;

    /** @var callable(): int */
    private $clock;

    public function __construct(
        private RoleplayGameSessionRepository $sessions,
        private RoleplayGameSessionService $service,
        private RoleplaySessionAppRepository $app,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    private function stamp(): string
    {
        return date('Y-m-d H:i:s', ($this->clock)());
    }

    private function log(int $tenantId, int $sessionId, string $event, ?int $actor, string $detail): void
    {
        try {
            if ($this->app->schemaReady()) {
                $this->app->log($tenantId, $sessionId, $event, null, $actor, $detail, $this->stamp());
            }
        } catch (\Throwable) {
        }
    }

    /** @param list<mixed> $ids @return list<int> */
    private static function ids(array $ids): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0))), 0, self::MAX_BATCH);
    }

    /**
     * @param array<string, mixed> $session
     * @param list<mixed> $userIds
     * @return array{pointed: int, already: int, refused: int}
     */
    public function point(int $tenantId, array $session, array $userIds, ?int $actor): array
    {
        $sessionId = (int) $session['id'];
        $live = RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::LIVE;
        $out = ['pointed' => 0, 'already' => 0, 'refused' => 0];
        if (RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::PAST) {
            $out['refused'] = count(self::ids($userIds));

            return $out;
        }
        foreach (self::ids($userIds) as $uid) {
            $user = $this->app->user($tenantId, $uid);
            if ($user === null || $user['status'] !== 'active') {
                $out['refused']++;
                continue;
            }
            $member = $this->sessions->findMember($tenantId, $sessionId, $uid);
            if ($member !== null && trim((string) ($member['checked_in_at'] ?? '')) !== '') {
                $out['already']++;
                continue;
            }
            $now = $this->stamp();
            $memberId = $member !== null ? (int) $member['id'] : $this->sessions->upsertMember($tenantId, $sessionId, [
                'user_id' => $uid,
                'steam_uid' => $user['steam_id'] !== '' ? $user['steam_id'] : null,
                'attendance_source' => 'live',
            ]);
            $this->sessions->updateMember($tenantId, $memberId, ['checked_in_at' => $now]);
            $this->sessions->addSegment($tenantId, $sessionId, $memberId, 'check_in', $now, $now, 0);
            if ($live) {
                $this->openTime($tenantId, $sessionId, $memberId, $member, $now);
            }
            $this->service->refreshMember($tenantId, $sessionId, $memberId);
            $out['pointed']++;
        }
        if ($out['pointed'] > 0) {
            $this->log($tenantId, $sessionId, 'staff_checkin', $actor, $out['pointed'] . ' joueur(s) pointé(s)');
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $session
     * @param list<mixed> $userIds
     * @return array{removed: int}
     */
    public function unpoint(int $tenantId, array $session, array $userIds, ?int $actor): array
    {
        $sessionId = (int) $session['id'];
        $removed = 0;
        foreach (self::ids($userIds) as $uid) {
            $member = $this->sessions->findMember($tenantId, $sessionId, $uid);
            if ($member === null || trim((string) ($member['checked_in_at'] ?? '')) === '') {
                continue;
            }
            $now = $this->stamp();
            $open = $this->sessions->openSegment($tenantId, (int) $member['id']);
            if ($open !== null) {
                $start = strtotime((string) $open['started_at']) ?: ($this->clock)();
                $this->sessions->closeSegment($tenantId, (int) $open['id'], $now, max(0, ($this->clock)() - $start));
                $this->sessions->addSegment($tenantId, $sessionId, (int) $member['id'], 'check_out', $now, $now, 0);
            }
            $this->sessions->updateMember($tenantId, (int) $member['id'], ['checked_in_at' => null]);
            $this->service->refreshMember($tenantId, $sessionId, (int) $member['id']);
            $removed++;
        }
        if ($removed > 0) {
            $this->log($tenantId, $sessionId, 'staff_checkout', $actor, $removed . ' pointage(s) retiré(s)');
        }

        return ['removed' => $removed];
    }

    /** @param array<string, mixed>|null $member */
    private function openTime(int $tenantId, int $sessionId, int $memberId, ?array $member, string $now): bool
    {
        if ($this->sessions->openSegment($tenantId, $memberId) !== null) {
            return false;
        }
        $this->sessions->addSegment($tenantId, $sessionId, $memberId, 'join', $now, null, 0);
        // Pas de last_heartbeat_at ici : la clôture fermerait le temps à cette heure-là (zéro minute). Sans signe de vie du
        // mod, le temps court jusqu'à « Terminer » ; avec, la règle existante du dernier signe de vie s'applique.
        if ($member === null || trim((string) ($member['joined_at'] ?? '')) === '') {
            $this->sessions->updateMember($tenantId, $memberId, ['joined_at' => $now]);
        }

        return true;
    }

    /**
     * Démarre l'enregistrement : session démarrée (règle existante), temps ouvert pour chaque joueur pointé.
     *
     * @param array<string, mixed> $session
     * @return array{ok: bool, error?: string, opened: int}
     */
    public function startRecording(int $tenantId, array $session, ?int $actor): array
    {
        $sessionId = (int) $session['id'];
        if (($session['status'] ?? '') === 'cancelled') {
            return ['ok' => false, 'error' => 'cancelled', 'opened' => 0];
        }
        if (RoleplaySessionBoard::phase($session) !== RoleplaySessionBoard::LIVE) {
            $this->service->startSession($tenantId, $sessionId);
        }
        $now = $this->stamp();
        $opened = 0;
        foreach ($this->sessions->listMembers($tenantId, $sessionId) as $m) {
            if ((int) ($m['user_id'] ?? 0) < 1 || trim((string) ($m['checked_in_at'] ?? '')) === '' || !empty($m['rh_excluded'])) {
                continue;
            }
            if ($this->openTime($tenantId, $sessionId, (int) $m['id'], $m, $now)) {
                $this->service->refreshMember($tenantId, $sessionId, (int) $m['id']);
                $opened++;
            }
        }
        $this->log($tenantId, $sessionId, 'recording_started', $actor, $opened . ' joueur(s) enregistré(s)');

        return ['ok' => true, 'opened' => $opened];
    }

    /**
     * @param array<string, mixed> $session
     * @return array{ok: bool, error?: string}
     */
    public function stopRecording(int $tenantId, array $session, ?int $actor): array
    {
        if (RoleplaySessionBoard::phase($session) !== RoleplaySessionBoard::LIVE) {
            return ['ok' => false, 'error' => 'not_recording'];
        }
        $this->service->closeSession($tenantId, (int) $session['id']);
        $this->log($tenantId, (int) $session['id'], 'recording_stopped', $actor, '');

        return ['ok' => true];
    }
}
