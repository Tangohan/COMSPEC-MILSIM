<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\RoleplayGameSessionRepository;
use App\Repositories\RoleplaySessionAppRepository;
use App\Support\RoleplaySessionBoard;
use App\Support\SessionJoinCode;

/**
 * Cycle de vie du code de session (« K7M-4QX ») que l'animateur annonce et que les joueurs saisissent dans l'app Session.
 *
 *  - émis automatiquement à la première ouverture de la fiche d'une session prévue ou en cours (ou à sa création) ;
 *  - actif jusqu'à la fin de la session + 30 min de marge (clôture ou annulation), au plus 24 h après le démarrage
 *    (session jamais clôturée) et, pour une session prévue, jusqu'à 12 h après son début prévu ;
 *  - régénérable (l'ancien passe « remplacé ») et révocable par l'animateur, chaque fois journalisé ;
 *  - expiré automatiquement par la tâche planifiée (statut « expired ») et, en attendant, refusé dès son heure passée.
 *
 * Unicité : un code n'est jamais réutilisé dans une communauté (clé unique tenant + code sur tout l'historique) ; un tirage
 * en collision est retiré. Le code est gardé en clair : l'animateur doit pouvoir le relire et l'annoncer pendant toute la
 * soirée ; il ne donne accès qu'au rattachement à une session de sa propre communauté (voir SessionJoinCode).
 */
final class RoleplaySessionCodeService
{
    public const GRACE_MINUTES = 30;
    public const LIVE_MAX_HOURS = 24;
    public const UPCOMING_AFTER_START_HOURS = 12;
    public const UPCOMING_MAX_DAYS = 30;
    private const DRAW_ATTEMPTS = 8;

    private RoleplaySessionAppRepository $app;
    private RoleplayGameSessionRepository $sessions;
    /** @var callable(): int */
    private $clock;
    /** @var (callable(int, int): int)|null */
    private $random;

    public function __construct(
        ?RoleplaySessionAppRepository $app = null,
        ?RoleplayGameSessionRepository $sessions = null,
        ?callable $clock = null,
        ?callable $random = null,
    ) {
        $this->app = $app ?? new RoleplaySessionAppRepository();
        $this->sessions = $sessions ?? new RoleplayGameSessionRepository();
        $this->clock = $clock ?? static fn (): int => time();
        $this->random = $random;
    }

    public function ready(): bool
    {
        return $this->app->schemaReady();
    }

    public function now(): int
    {
        return ($this->clock)();
    }

    private function stamp(?int $ts = null): string
    {
        return date('Y-m-d H:i:s', $ts ?? $this->now());
    }

    /**
     * Heure au-delà de laquelle le code ne rattache plus personne.
     *
     * @param array<string, mixed> $session
     * @param array<string, mixed> $code
     */
    public function expiresAt(array $session, array $code): int
    {
        $ts = static function (mixed $v): ?int {
            $s = trim((string) $v);
            $t = $s !== '' ? strtotime($s) : false;

            return $t === false ? null : $t;
        };
        $created = $ts($code['created_at'] ?? null) ?? $this->now();
        $status = (string) ($session['status'] ?? '');
        if ($status === 'closed' || $status === 'cancelled') {
            return ($ts($session['ended_at'] ?? null) ?? $ts($session['updated_at'] ?? null) ?? $created) + self::GRACE_MINUTES * 60;
        }
        $started = $ts($session['started_at'] ?? null);
        if ($started !== null) {
            return $started + self::LIVE_MAX_HOURS * 3600;
        }
        $plannedEnd = $ts($session['planned_ends_at'] ?? null);
        $plannedStart = $ts($session['planned_starts_at'] ?? null);
        $limit = $plannedEnd !== null ? $plannedEnd + self::GRACE_MINUTES * 60 : ($plannedStart !== null ? $plannedStart + self::UPCOMING_AFTER_START_HOURS * 3600 : null);

        return min($limit ?? PHP_INT_MAX, $created + self::UPCOMING_MAX_DAYS * 86400);
    }

    /**
     * État lisible du dernier code : none | active | expired | revoked (révoqué ou remplacé sans successeur).
     *
     * @param array<string, mixed> $session
     * @param array<string, mixed>|null $code
     */
    public function state(array $session, ?array $code): string
    {
        if ($code === null) {
            return 'none';
        }
        $status = (string) ($code['status'] ?? 'active');
        if ($status === 'revoked' || $status === 'replaced') {
            return 'revoked';
        }
        if ($status === 'expired' || $this->now() >= $this->expiresAt($session, $code)) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Code à afficher sur la fiche : le code actif, émis s'il manque pour une session prévue ou en cours.
     * Une session passée n'en reçoit jamais de nouveau. Sans les tables : ready=false, rien d'émis.
     *
     * @param array<string, mixed> $session
     * @return array{ready: bool, state: string, code: ?string, formatted: ?string, expires_at: ?int, created_at: ?string, can_issue: bool}
     */
    public function ensure(int $tenantId, array $session, ?int $actorId, bool $issueIfMissing = true): array
    {
        if (!$this->ready()) {
            return ['ready' => false, 'state' => 'none', 'code' => null, 'formatted' => null, 'expires_at' => null, 'created_at' => null, 'can_issue' => false];
        }
        $sessionId = (int) ($session['id'] ?? 0);
        $open = RoleplaySessionBoard::phase($session) !== RoleplaySessionBoard::PAST;
        $code = $this->app->latestCode($tenantId, $sessionId);
        $state = $this->state($session, $code);
        if ($state === 'expired' && ($code['status'] ?? '') === 'active') {
            $this->app->endActiveCodes($tenantId, $sessionId, 'expired', null, $this->stamp());
            $this->app->log($tenantId, $sessionId, 'code_expired', null, null, (string) ($code['code'] ?? ''), $this->stamp());
        }
        // Session prévue ou en cours sans code valable (première ouverture, session rouverte) : un code est émis.
        // Un code révoqué par l'animateur n'est jamais remplacé tout seul.
        if ($issueIfMissing && $open && ($state === 'none' || $state === 'expired')) {
            $code = $this->issue($tenantId, $sessionId, $actorId, false);
            $state = $code !== null ? 'active' : 'none';
        }

        return $this->view($session, $code, $state, $open);
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed>|null $code
     * @return array{ready: bool, state: string, code: ?string, formatted: ?string, expires_at: ?int, created_at: ?string, can_issue: bool}
     */
    private function view(array $session, ?array $code, string $state, bool $open): array
    {
        $raw = $code !== null ? (string) ($code['code'] ?? '') : null;

        return [
            'ready' => true,
            'state' => $state,
            'code' => $raw,
            'formatted' => $raw !== null ? SessionJoinCode::format($raw) : null,
            'expires_at' => $code !== null && $state === 'active' ? $this->expiresAt($session, $code) : null,
            'created_at' => $code !== null ? (string) ($code['created_at'] ?? '') : null,
            'can_issue' => $open,
        ];
    }

    /**
     * Émet un nouveau code (l'actif éventuel passe « remplacé »). Null si la base refuse huit tirages de suite.
     *
     * @return array<string, mixed>|null
     */
    public function issue(int $tenantId, int $sessionId, ?int $actorId, bool $regenerated): ?array
    {
        $now = $this->stamp();
        $replaced = $this->app->endActiveCodes($tenantId, $sessionId, 'replaced', $actorId, $now);
        for ($i = 0; $i < self::DRAW_ATTEMPTS; $i++) {
            $code = SessionJoinCode::generate($this->random);
            if ($this->app->insertCode($tenantId, $sessionId, $code, $actorId, $now)) {
                $this->app->log($tenantId, $sessionId, $regenerated || $replaced > 0 ? 'code_regenerated' : 'code_issued', null, $actorId, SessionJoinCode::format($code), $now);

                return $this->app->activeCode($tenantId, $sessionId);
            }
        }

        return null;
    }

    /**
     * Régénération demandée par l'animateur : refusée pour une session terminée ou annulée.
     *
     * @param array<string, mixed> $session
     * @return array{ok: bool, error?: string, code?: string}
     */
    public function regenerate(int $tenantId, array $session, ?int $actorId): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        if (RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::PAST) {
            return ['ok' => false, 'error' => 'session_closed'];
        }
        $code = $this->issue($tenantId, (int) $session['id'], $actorId, true);

        return $code !== null ? ['ok' => true, 'code' => (string) $code['code']] : ['ok' => false, 'error' => 'draw_failed'];
    }

    /**
     * @param array<string, mixed> $session
     * @return array{ok: bool, error?: string}
     */
    public function revoke(int $tenantId, array $session, ?int $actorId): array
    {
        if (!$this->ready()) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        $sessionId = (int) $session['id'];
        $active = $this->app->activeCode($tenantId, $sessionId);
        if ($active === null) {
            return ['ok' => false, 'error' => 'no_code'];
        }
        $now = $this->stamp();
        $this->app->endActiveCodes($tenantId, $sessionId, 'revoked', $actorId, $now);
        $this->app->log($tenantId, $sessionId, 'code_revoked', null, $actorId, SessionJoinCode::format((string) $active['code']), $now);

        return ['ok' => true];
    }

    /** Tâche planifiée : passe « expired » les codes actifs dont l'heure est passée. Nombre de codes expirés. */
    public function expireDue(): int
    {
        if (!$this->ready()) {
            return 0;
        }
        $n = 0;
        foreach ($this->app->activeCodesWithSession() as $row) {
            $session = $row['status'] === '' ? ['status' => 'cancelled', 'ended_at' => $row['created_at']] : $row;
            if ($this->now() < $this->expiresAt($session, ['created_at' => $row['created_at']])) {
                continue;
            }
            $now = $this->stamp();
            if ($this->app->endActiveCodes($row['tenant_id'], $row['session_id'], 'expired', null, $now) > 0) {
                $this->app->log($row['tenant_id'], $row['session_id'], 'code_expired', null, null, '', $now);
                $n++;
            }
        }

        return $n;
    }

    /**
     * Codes actifs à montrer dans la liste des sessions (sans en émettre).
     *
     * @param list<array<string, mixed>> $sessions
     * @return array<int, string> session_id => code formaté
     */
    public function listCodes(int $tenantId, array $sessions): array
    {
        if (!$this->ready() || $sessions === []) {
            return [];
        }
        $byId = [];
        foreach ($sessions as $s) {
            $byId[(int) ($s['id'] ?? 0)] = $s;
        }
        $out = [];
        foreach ($this->app->activeCodesFor($tenantId, array_keys($byId)) as $sid => $code) {
            $session = $byId[$sid] ?? null;
            if ($session !== null && RoleplaySessionBoard::phase($session) !== RoleplaySessionBoard::PAST) {
                $out[$sid] = SessionJoinCode::format($code);
            }
        }

        return $out;
    }

    public function repository(): RoleplaySessionAppRepository
    {
        return $this->app;
    }

    public function sessions(): RoleplayGameSessionRepository
    {
        return $this->sessions;
    }
}
