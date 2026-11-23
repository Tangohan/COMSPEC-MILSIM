<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\RoleplaySessionAppRepository;
use App\Repositories\UserRepository;
use App\Services\Security\FileRateLimiter;
use App\Support\RoleplaySessionBoard;
use App\Support\SessionJoinCode;

/**
 * App Session du téléphone (contrat v1 : docs/technical/ICD-SESSION-JEU.md) : rejoindre une session avec le code annoncé,
 * lire la session en cours, pointer sa présence, la quitter. Chaque méthode rend [code HTTP, corps JSON].
 *
 * Rattachement : une ligne de roleplay_game_session_members par joueur et par session (jamais de doublon, appels répétés
 * sans effet). Session déjà démarrée : rejoindre pointe aussi la présence (le code annoncé prouve que le joueur est là) ;
 * session prévue : le joueur est inscrit, il pointe ensuite au démarrage. Le temps de jeu reste compté par le mod comme
 * avant (game-session/heartbeat) : ces routes ne changent rien au pointage existant.
 */
final class RoleplaySessionGameApp
{
    public const VERSION = 1;
    public const JOIN_PER_PLAYER = 10;
    public const JOIN_PER_IP = 30;
    public const JOIN_WINDOW = 600;
    public const ACTION_PER_PLAYER = 30;
    public const ENDED_VISIBLE_HOURS = 2;
    public const UPCOMING_VISIBLE_HOURS = 24;

    private RoleplaySessionCodeService $codes;
    private RoleplaySessionAppRepository $app;
    private RoleplayGameSessionRepository $sessions;
    private RoleplayGameSessionService $service;
    private FileRateLimiter $limiter;
    private ?RoleplaySessionNotifier $notifier;

    public function __construct(
        ?RoleplaySessionCodeService $codes = null,
        ?RoleplayGameSessionService $service = null,
        ?FileRateLimiter $limiter = null,
        ?RoleplaySessionNotifier $notifier = null,
    ) {
        $this->codes = $codes ?? new RoleplaySessionCodeService();
        $this->app = $this->codes->repository();
        $this->sessions = $this->codes->sessions();
        $this->service = $service ?? new RoleplayGameSessionService($this->sessions, new UserRepository());
        $this->limiter = $limiter ?? new FileRateLimiter();
        $this->notifier = $notifier;
    }

    private function now(): int
    {
        return $this->codes->now();
    }

    private function stamp(): string
    {
        return date('Y-m-d H:i:s', $this->now());
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private static function error(int $status, string $code, array $extra = []): array
    {
        return [$status, ['v' => self::VERSION, 'ok' => false, 'error' => $code] + $extra];
    }

    /** @return array{0: int, 1: array<string, mixed>}|null refus commun (fonction absente, compte non relié ou inactif) */
    private function guard(int $tenantId, int $userId): ?array
    {
        if (!$this->codes->ready()) {
            return self::error(503, 'feature_unavailable');
        }
        if ($tenantId < 1) {
            return self::error(403, 'tenant_context_required');
        }
        if ($userId < 1) {
            return self::error(401, 'account_link_required');
        }
        $user = $this->app->user($tenantId, $userId);
        if ($user === null || $user['status'] !== 'active') {
            return self::error(403, 'not_allowed');
        }

        return null;
    }

    private function limited(string $key, int $max, int $window): ?int
    {
        if ($this->limiter->tooManyAttempts($key, $max, $window)) {
            return $this->limiter->secondsUntilReset($key, $window);
        }

        return null;
    }

    /**
     * POST /api/atak/session/join {"code": "K7M-4QX"}
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function join(int $tenantId, int $userId, string $steamUid, mixed $rawCode, string $ip): array
    {
        $refused = $this->guard($tenantId, $userId);
        if ($refused !== null) {
            return $refused;
        }
        // Débit compté à chaque essai, réussi ou non : par joueur et par adresse (plusieurs comptes derrière une même adresse).
        foreach ([['rp_session_join|u|' . $tenantId . '|' . $userId, self::JOIN_PER_PLAYER], ['rp_session_join|ip|' . ($ip !== '' ? $ip : 'unknown'), self::JOIN_PER_IP]] as [$key, $max]) {
            $wait = $this->limited($key, $max, self::JOIN_WINDOW);
            if ($wait !== null) {
                return self::error(429, 'rate_limited', ['retry_after' => $wait]);
            }
        }
        $code = SessionJoinCode::normalize($rawCode);
        if ($code === null) {
            return self::error(422, 'invalid_format');
        }
        $row = $this->app->findCode($tenantId, $code);
        $session = $row !== null ? $this->sessions->findById($tenantId, (int) $row['session_id']) : null;
        // Code inconnu, révoqué ou remplacé : même réponse (on ne révèle pas qu'un code a existé).
        if ($row === null || $session === null || in_array((string) $row['status'], ['revoked', 'replaced'], true)) {
            $this->refusal($tenantId, (int) ($row['session_id'] ?? 0), $userId, 'code_unknown');

            return self::error(404, 'code_unknown');
        }
        $sessionId = (int) $session['id'];
        if ($this->codes->state($session, $row) === 'expired') {
            $this->refusal($tenantId, $sessionId, $userId, 'code_expired');

            return self::error(410, 'code_expired');
        }
        if (RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::PAST) {
            $this->refusal($tenantId, $sessionId, $userId, 'session_closed');

            return self::error(409, 'session_closed');
        }
        $member = $this->sessions->findMember($tenantId, $sessionId, $userId);
        $already = $member !== null;
        $now = $this->stamp();
        if (!$already) {
            $memberId = $this->sessions->upsertMember($tenantId, $sessionId, [
                'user_id' => $userId,
                'steam_uid' => $steamUid !== '' ? $steamUid : null,
                'attendance_source' => 'code',
            ]);
            $member = ['id' => $memberId, 'session_id' => $sessionId, 'checked_in_at' => null];
        }
        $checkedIn = false;
        if (RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::LIVE && trim((string) ($member['checked_in_at'] ?? '')) === '') {
            $this->checkInMember($tenantId, $sessionId, (int) $member["id"], $userId, $now);
            $checkedIn = true;
        }
        if (!$already) {
            $this->app->log($tenantId, $sessionId, 'join', 'join:' . $userId, $userId, $checkedIn ? 'Rejoint et pointé' : 'Rejoint', $now);
        }

        [, $body] = $this->describe($tenantId, $userId, $session);
        $body['joined'] = ['already' => $already, 'checked_in_now' => $checkedIn];

        return [200, $body];
    }

    /** Journal d'un refus (sans le code saisi). */
    private function refusal(int $tenantId, int $sessionId, int $userId, string $reason): void
    {
        try {
            $this->app->log($tenantId, max(0, $sessionId), 'join_refused', null, $userId, $reason, $this->stamp());
        } catch (\Throwable) {
        }
    }

    private function checkInMember(int $tenantId, int $sessionId, int $memberId, int $userId, string $now): void
    {
        $this->sessions->updateMember($tenantId, $memberId, ['checked_in_at' => $now]);
        $this->sessions->addSegment($tenantId, $sessionId, $memberId, 'check_in', $now, $now, 0);
        // Session en cours d'enregistrement : le temps du joueur pointé court dès maintenant (comme un pointage de l'animateur).
        if ($this->sessions->openSegment($tenantId, $memberId) === null) {
            $this->sessions->addSegment($tenantId, $sessionId, $memberId, 'join', $now, null, 0);
            $member = $this->sessions->findMember($tenantId, $sessionId, $userId);
            if ($member === null || trim((string) ($member['joined_at'] ?? '')) === '') {
                $this->sessions->updateMember($tenantId, $memberId, ['joined_at' => $now]);
            }
        }
        $this->service->refreshMember($tenantId, $sessionId, $memberId);
    }

    /**
     * GET /api/atak/session/current
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function current(int $tenantId, int $userId): array
    {
        $refused = $this->guard($tenantId, $userId);
        if ($refused !== null) {
            return $refused;
        }
        if ($this->notifier !== null && !$this->limiter->tooManyAttempts('rp_session_tick|' . $tenantId, 1, 60)) {
            try {
                $this->notifier->tickReminders($tenantId);
            } catch (\Throwable) {
            }
        }

        return $this->describe($tenantId, $userId, null);
    }

    /**
     * POST /api/atak/session/checkin {"session_id": 12}
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function checkin(int $tenantId, int $userId, int $sessionId): array
    {
        $ctx = $this->memberContext($tenantId, $userId, $sessionId);
        if (!isset($ctx['session'])) {
            return $ctx;
        }
        ['session' => $session, 'member' => $member] = $ctx;
        if (RoleplaySessionBoard::phase($session) !== RoleplaySessionBoard::LIVE) {
            return self::error(409, RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::PAST ? 'session_closed' : 'session_not_started');
        }
        $already = trim((string) ($member['checked_in_at'] ?? '')) !== '';
        if (!$already) {
            $now = $this->stamp();
            $this->checkInMember($tenantId, (int) $session["id"], (int) $member["id"], $userId, $now);
            $this->app->log($tenantId, (int) $session['id'], 'checkin', 'checkin:' . $userId, $userId, 'Pointé depuis l’app Session', $now);
        }
        [, $body] = $this->describe($tenantId, $userId, $session);
        $body['checkin'] = ['already' => $already];

        return [200, $body];
    }

    /**
     * POST /api/atak/session/leave {"session_id": 12}
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function leave(int $tenantId, int $userId, int $sessionId): array
    {
        $ctx = $this->memberContext($tenantId, $userId, $sessionId);
        if (!isset($ctx['session'])) {
            return $ctx;
        }
        ['session' => $session, 'member' => $member] = $ctx;
        $already = trim((string) ($member['left_at'] ?? '')) !== '' && $this->sessions->openSegment($tenantId, (int) $member['id']) === null;
        if (!$already) {
            $now = $this->stamp();
            $this->service->leaveMember($tenantId, $member, $now);
            $this->app->log($tenantId, (int) $session['id'], 'leave', null, $userId, 'Départ annoncé depuis l’app Session', $now);
        }
        [, $body] = $this->describe($tenantId, $userId, $session);
        $body['leave'] = ['already' => $already];

        return [200, $body];
    }

    /** @return array{session: array<string, mixed>, member: array<string, mixed>}|array{0: int, 1: array<string, mixed>} */
    private function memberContext(int $tenantId, int $userId, int $sessionId): array
    {
        $refused = $this->guard($tenantId, $userId);
        if ($refused !== null) {
            return $refused;
        }
        $wait = $this->limited('rp_session_act|' . $tenantId . '|' . $userId, self::ACTION_PER_PLAYER, self::JOIN_WINDOW);
        if ($wait !== null) {
            return self::error(429, 'rate_limited', ['retry_after' => $wait]);
        }
        $session = $sessionId > 0 ? $this->sessions->findById($tenantId, $sessionId) : null;
        $member = $session !== null ? $this->sessions->findMember($tenantId, $sessionId, $userId) : null;
        // Session d'une autre communauté, inconnue, ou que le joueur n'a pas rejointe : même réponse.
        if ($session === null || $member === null) {
            return self::error(404, 'not_joined');
        }

        return ['session' => $session, 'member' => $member];
    }

    /**
     * Corps commun des réponses : état de l'app, session, jauge, présence du joueur.
     *
     * @param array<string, mixed>|null $session session déjà connue (sinon : celle du joueur, puis celle de la communauté)
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function describe(int $tenantId, int $userId, ?array $session): array
    {
        $now = $this->now();
        $member = null;
        if ($session === null) {
            $mine = $this->app->memberSession($tenantId, $userId, date('Y-m-d H:i:s', $now - self::ENDED_VISIBLE_HOURS * 3600));
            if ($mine !== null) {
                $session = $mine;
            } else {
                $session = $this->app->communitySession(
                    $tenantId,
                    date('Y-m-d H:i:s', $now - RoleplaySessionBoard::STALE_HOURS * 3600),
                    date('Y-m-d H:i:s', $now + self::UPCOMING_VISIBLE_HOURS * 3600)
                );
            }
        }
        $base = ['v' => self::VERSION, 'ok' => true, 'server_time' => date('c', $now)];
        if ($session === null) {
            return [200, $base + ['app_state' => 'no_session', 'session' => null, 'me' => null]];
        }
        $sessionId = (int) $session['id'];
        $member ??= $this->sessions->findMember($tenantId, $sessionId, $userId);
        $phase = RoleplaySessionBoard::phase($session);
        $status = (string) ($session['status'] ?? '');
        $state = $status === 'cancelled' ? 'cancelled' : ($phase === RoleplaySessionBoard::PAST ? 'ended' : ($phase === RoleplaySessionBoard::LIVE ? 'live' : 'upcoming'));
        $checkedIn = $member !== null && trim((string) ($member['checked_in_at'] ?? '')) !== '';
        $appState = match (true) {
            $state === 'ended' || $state === 'cancelled' => 'ended',
            $member === null => 'code_required',
            $state === 'live' && !$checkedIn => 'checkin',
            default => 'active',
        };
        $settings = $this->app->settings($tenantId, $sessionId);
        $host = (int) ($session['created_by_user_id'] ?? 0) > 0 ? $this->app->user($tenantId, (int) $session['created_by_user_id']) : null;
        $iso = static function (mixed $v): ?string {
            $s = trim((string) $v);
            $t = $s !== '' ? strtotime($s) : false;

            return $t === false ? null : date('c', $t);
        };
        $gauge = $this->app->gauge($tenantId, $sessionId);

        return [200, $base + [
            'app_state' => $appState,
            'session' => [
                'id' => $sessionId,
                'name' => (string) ($session['mission_name'] ?? '') ?: 'Session',
                'state' => $state,
                'kind' => (string) ($session['session_kind'] ?? 'libre'),
                'category' => (string) ($session['hour_category'] ?? ''),
                'planned_start' => $iso($session['planned_starts_at'] ?? null),
                'planned_end' => $iso($session['planned_ends_at'] ?? null),
                'started_at' => $iso($session['started_at'] ?? null),
                'ended_at' => $iso($session['ended_at'] ?? null),
                'host' => $host !== null && $host['label'] !== '' ? $host['label'] : null,
                'briefing' => $settings['briefing'] !== '' ? $settings['briefing'] : null,
                'attendance_required' => !empty($session['attendance_enabled']),
                'min_minutes' => (int) ($session['min_minutes'] ?? 0),
                'gauge' => $gauge,
            ],
            'me' => $member === null ? null : [
                'joined' => true,
                'checked_in_at' => $iso($member['checked_in_at'] ?? null),
                'left_at' => $iso($member['left_at'] ?? null),
                'playing' => $this->sessions->openSegment($tenantId, (int) $member['id']) !== null,
                'raw_seconds' => (int) ($member['raw_seconds'] ?? 0),
                'validated_seconds' => !empty($member['rh_excluded']) ? 0 : (int) ($member['validated_seconds'] ?? 0),
                'counted' => !empty($member['attendance_valid']) && empty($member['rh_excluded']),
                'attendance_status' => (string) ($member['attendance_status'] ?? 'absent'),
            ],
        ]];
    }
}
