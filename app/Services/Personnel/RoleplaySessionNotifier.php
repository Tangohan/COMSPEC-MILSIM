<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Repositories\AtakWebCommandRepository;
use App\Repositories\RoleplaySessionAppRepository;
use App\Services\AtakToc\TocPhoneService;
use App\Support\RoleplaySessionBoard;

/**
 * Notifications en jeu des sessions (téléphone ATAK). Aucun nouveau canal : la notification part dans la file des
 * commandes web vers les téléphones (atak_web_commands, kind « phone », cmd « notify »), la même que « Notifier » du poste
 * TOC web. Le mod la range dans la boîte du téléphone et le journal de l'app Alertes (fn_tocPhone) : jamais d'alerte à
 * l'écran du jeu. Arguments ajoutés au fil, ignorés par les mods actuels : src=ses, sid (session), ev (événement), pour que
 * l'app Session les reconnaisse (docs/technical/ICD-SESSION-JEU.md).
 *
 * Destinataires : tous les téléphones de la communauté (cible « * », une seule ligne par événement, durée de vie courte pour
 * ne jamais encombrer la file des ordres en temps réel), sauf la notification de test, envoyée au seul téléphone de l'animateur.
 * Chaque envoi est journalisé (roleplay_game_session_journal) ; les rappels portent une clé d'unicité (jamais deux fois).
 */
final class RoleplaySessionNotifier
{
    /** événement => [niveau i|a, durée de vie (s)] */
    public const EVENTS = [
        'opened' => ['i', 3600],
        'reminder_15' => ['i', 900],
        'reminder_5' => ['a', 600],
        'started' => ['a', 1800],
        'resumed' => ['i', 1200],
        'changed' => ['i', 1800],
        'checkin_call' => ['a', 900],
        'ended' => ['i', 1200],
        'cancelled' => ['i', 1800],
        'test' => ['i', 600],
    ];

    /** Longueur affichée par le téléphone (fn_tocPhone coupe à 160). */
    public const MESSAGE_MAX = 160;

    private RoleplaySessionAppRepository $app;
    /** @var callable(int, string, string, array<string, mixed>, array<string, scalar>, int, ?int): ?int */
    private $enqueue;
    /** @var callable(): int */
    private $clock;

    public function __construct(?RoleplaySessionAppRepository $app = null, ?callable $enqueue = null, ?callable $clock = null)
    {
        $this->app = $app ?? new RoleplaySessionAppRepository();
        $this->clock = $clock ?? static fn (): int => time();
        $this->enqueue = $enqueue ?? static function (int $tenantId, string $target, string $label, array $args, array $wire, int $ttl, ?int $userId): ?int {
            $row = (new AtakWebCommandRepository())->enqueue($tenantId, 1, 'phone', 'notify', $target, 'session:' . (int) ($wire['sid'] ?? 0), $label, $args, $wire, 'Session', $userId, $ttl);

            return is_array($row) ? (int) $row['id'] : null;
        };
    }

    /**
     * Texte de la notification (une ligne, 160 caractères au plus).
     *
     * @param array<string, mixed> $session
     */
    public static function message(string $event, array $session): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($session['mission_name'] ?? ''))) ?? '') ?: 'La session';
        $name = mb_substr($name, 0, 70);
        $start = trim((string) ($session['planned_starts_at'] ?? ''));
        $startTs = $start !== '' ? strtotime($start) : false;
        $when = $startTs !== false ? date('d/m', $startTs) . ' à ' . date('H:i', $startTs) : '';
        $text = match ($event) {
            'opened' => $when !== '' ? 'Session prévue : ' . $name . ', le ' . $when . '. Rejoignez-la dans l’app Session.' : 'Session ouverte : ' . $name . '. Rejoignez-la dans l’app Session.',
            'reminder_15' => $name . ' commence dans 15 min. Ouvrez l’app Session.',
            'reminder_5' => $name . ' commence dans 5 min.',
            'started' => $name . ' a commencé. Saisissez le code annoncé dans l’app Session.',
            'resumed' => $name . ' reprend. Le temps de jeu est de nouveau compté.',
            'changed' => $when !== '' ? $name . ' : horaire modifié, début le ' . $when . '.' : $name . ' : la session a été modifiée.',
            'checkin_call' => $name . ' : pointez votre présence dans l’app Session.',
            'ended' => $name . ' est terminée. Merci pour votre présence.',
            'cancelled' => $name . ' est annulée.',
            default => 'Notification de test de la session ' . $name . '.',
        };

        return mb_substr($text, 0, self::MESSAGE_MAX);
    }

    /**
     * Publie un événement de session. Sans les tables, ou notifications coupées pour la session (sauf test) : rien n'est envoyé.
     *
     * @param array<string, mixed> $session
     * @return array{ok: bool, sent: bool, reason?: string, command_id?: int}
     */
    public function publish(int $tenantId, array $session, string $event, ?int $actorId = null, ?string $dedupeKey = null, string $target = '*'): array
    {
        if (!isset(self::EVENTS[$event])) {
            return ['ok' => false, 'sent' => false, 'reason' => 'unknown_event'];
        }
        if (!$this->app->schemaReady()) {
            return ['ok' => false, 'sent' => false, 'reason' => 'unavailable'];
        }
        $sessionId = (int) ($session['id'] ?? 0);
        if ($sessionId < 1) {
            return ['ok' => false, 'sent' => false, 'reason' => 'no_session'];
        }
        $settings = $this->app->settings($tenantId, $sessionId);
        if ($event !== 'test' && !$settings['notify_enabled']) {
            return ['ok' => true, 'sent' => false, 'reason' => 'disabled'];
        }
        $now = date('Y-m-d H:i:s', ($this->clock)());
        [$level, $ttl] = self::EVENTS[$event];
        $text = self::message($event, $session);
        // La clé d'unicité est posée avant l'envoi : deux passages simultanés (tâche planifiée, app) n'envoient qu'une fois.
        if ($dedupeKey !== null && !$this->app->log($tenantId, $sessionId, 'notify_' . $event, $dedupeKey, $actorId, $text, $now)) {
            return ['ok' => true, 'sent' => false, 'reason' => 'already_sent'];
        }
        $wire = [
            'lv' => $level,
            'm' => TocPhoneService::wireTextMax($text, 330),
            'ak' => 0,
            'by' => 'Session',
            'src' => 'ses',
            'sid' => $sessionId,
            'ev' => $event,
        ];
        $label = $target === '*' ? 'Téléphones de la communauté' : 'Téléphone de l’animateur';
        $id = ($this->enqueue)($tenantId, $target, $label, ['event' => $event, 'message' => $text, 'session_id' => $sessionId], $wire, $ttl, $actorId);
        if ($dedupeKey === null) {
            $this->app->log($tenantId, $sessionId, 'notify_' . $event, null, $actorId, ($id === null ? '[non envoyée] ' : '') . $text, $now);
        } elseif ($id === null) {
            $this->app->log($tenantId, $sessionId, 'notify_failed', null, $actorId, $event, $now);
        }

        return $id !== null ? ['ok' => true, 'sent' => true, 'command_id' => $id] : ['ok' => false, 'sent' => false, 'reason' => 'send_failed'];
    }

    /**
     * Après un changement d'état demandé depuis la fiche (démarrer, rouvrir, clôturer, annuler).
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function onStateChange(int $tenantId, array $before, array $after, ?int $actorId): ?string
    {
        $was = RoleplaySessionBoard::phase($before);
        $is = RoleplaySessionBoard::phase($after);
        $status = (string) ($after['status'] ?? '');
        $event = null;
        if ($is === RoleplaySessionBoard::LIVE && $was === RoleplaySessionBoard::UPCOMING) {
            $event = 'started';
        } elseif ($is === RoleplaySessionBoard::LIVE && $was === RoleplaySessionBoard::PAST) {
            $event = 'resumed';
        } elseif ($is === RoleplaySessionBoard::PAST && $was !== RoleplaySessionBoard::PAST) {
            $event = $status === 'cancelled' ? 'cancelled' : 'ended';
        }
        if ($event === null) {
            return null;
        }
        $this->publish($tenantId, $after, $event, $actorId, $event . ':' . (string) ($after['started_at'] ?? '') . ':' . (string) ($after['ended_at'] ?? ''));

        return $event;
    }

    /** @param array<string, mixed> $session nouvelle session (prévue ou démarrée tout de suite) */
    public function onCreated(int $tenantId, array $session, ?int $actorId): ?string
    {
        $event = RoleplaySessionBoard::phase($session) === RoleplaySessionBoard::LIVE ? 'started' : 'opened';
        $res = $this->publish($tenantId, $session, $event, $actorId, $event . ':' . (string) ($session['started_at'] ?? '') . ':');

        return $res['sent'] ? $event : null;
    }

    /**
     * Horaire d'une session prévue modifié : une notification par nouvel horaire.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function onUpdated(int $tenantId, array $before, array $after, ?int $actorId): ?string
    {
        if (RoleplaySessionBoard::phase($after) !== RoleplaySessionBoard::UPCOMING) {
            return null;
        }
        $old = (string) ($before['planned_starts_at'] ?? '');
        $new = (string) ($after['planned_starts_at'] ?? '');
        if ($old === $new) {
            return null;
        }
        $res = $this->publish($tenantId, $after, 'changed', $actorId, 'changed:' . $new);

        return $res['sent'] ? 'changed' : null;
    }

    /**
     * Rappels 15 et 5 minutes avant le début prévu (tâche planifiée toutes les 5 minutes, et à la lecture de l'app).
     * Une tâche en retard n'envoie que le rappel le plus proche ; un horaire déplacé réarme les rappels.
     *
     * @return int rappels envoyés
     */
    public function tickReminders(?int $tenantId = null): int
    {
        if (!$this->app->schemaReady()) {
            return 0;
        }
        $now = ($this->clock)();
        $sent = 0;
        foreach ($this->app->sessionsStartingBetween(date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $now + 15 * 60), $tenantId) as $s) {
            $tid = (int) $s['tenant_id'];
            $settings = $this->app->settings($tid, (int) $s['id']);
            if (!$settings['notify_enabled']) {
                continue;
            }
            $left = (int) strtotime((string) $s['planned_starts_at']) - $now;
            $event = $left <= 5 * 60 ? ($settings['remind_5'] ? 'reminder_5' : null) : ($settings['remind_15'] ? 'reminder_15' : null);
            if ($event === null) {
                continue;
            }
            $res = $this->publish($tid, $s, $event, null, $event . ':' . (string) $s['planned_starts_at']);
            $sent += $res['sent'] ? 1 : 0;
        }

        return $sent;
    }
}
