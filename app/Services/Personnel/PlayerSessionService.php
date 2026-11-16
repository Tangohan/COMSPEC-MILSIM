<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Sessions de jeu d'un joueur sur le serveur de sa communauté (service pur : aucune base, aucune horloge cachée).
 *
 * Une session est un intervalle continu de présence reconstitué à partir d'événements (signes de vie du mod toutes les
 * 10 minutes, connexions, déconnexions). Deux événements espacés de moins de « coupure tolérée » appartiennent à la même
 * session : une reconnexion rapide ne coupe donc pas la session. Une session ne compte pour la disponibilité que si sa
 * durée atteint le seuil de la communauté (60 minutes par défaut) ; les plus courtes restent en base pour l'historique.
 *
 * Les instants sont des horodatages Unix. Une session n'est jamais coupée à minuit : elle est rattachée au jour local
 * (fuseau de la communauté) où elle a commencé.
 */
final class PlayerSessionService
{
    public const DEFAULT_MIN_MINUTES = 60;

    /** Le mod signale sa présence toutes les 10 minutes : 25 minutes tolèrent deux signes de vie perdus. */
    public const DEFAULT_GAP_MINUTES = 25;

    /** Au-delà, la session est jugée aberrante (client resté ouvert, horloge faussée) : durée plafonnée et signalée. */
    public const MAX_SESSION_SECONDS = 86400;

    public const EVENT_HEARTBEAT = 'heartbeat';
    public const EVENT_CONNECT = 'connect';
    public const EVENT_DISCONNECT = 'disconnect';

    public const RELIABILITY_NO_UID = 'no_uid';
    public const RELIABILITY_NO_DATA = 'no_data';
    public const RELIABILITY_PARTIAL = 'partial';
    public const RELIABILITY_GOOD = 'good';

    /** Fenêtres d'agrégat, en jours. */
    public const WINDOWS = [7, 30, 90];

    /**
     * Reconstitue les sessions d'un joueur.
     *
     * @param list<array{at: int|string, type?: string, server_id?: ?int, source?: ?string}> $events
     * @param int|null $now instant de lecture : une session dont le dernier signe de vie est récent est « en cours »
     * @return list<array{started_at: int, last_seen_at: int, ended_at: ?int, duration_s: int, aberrant: bool, ongoing: bool, source: string, server_id: ?int, events: int}>
     */
    public static function buildSessions(array $events, int $gapSeconds, ?int $now = null): array
    {
        $gapSeconds = max(60, $gapSeconds);
        $clean = [];
        foreach ($events as $e) {
            $at = self::toTimestamp($e['at'] ?? null);
            if ($at === null) {
                continue;
            }
            $type = (string) ($e['type'] ?? self::EVENT_HEARTBEAT);
            if (!in_array($type, [self::EVENT_HEARTBEAT, self::EVENT_CONNECT, self::EVENT_DISCONNECT], true)) {
                $type = self::EVENT_HEARTBEAT;
            }
            $clean[] = [
                'at' => $at,
                'type' => $type,
                'server_id' => isset($e['server_id']) && (int) $e['server_id'] > 0 ? (int) $e['server_id'] : null,
                'source' => isset($e['source']) && trim((string) $e['source']) !== '' ? trim((string) $e['source']) : 'heartbeat',
            ];
        }
        usort($clean, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        $sessions = [];
        $cur = null;
        $close = static function (array $s, bool $ongoing) use (&$sessions): void {
            $sessions[] = self::finish($s, $ongoing);
        };
        foreach ($clean as $e) {
            if ($cur !== null && $e['at'] - $cur['last'] > $gapSeconds) {
                // Une déconnexion qui clôt une présence annoncée par une connexion (journal sans signes de vie) garde la
                // session ouverte : l'intervalle connexion → déconnexion est continu.
                $endsLoggedSession = $e['type'] === self::EVENT_DISCONNECT && $cur['logged'] && $cur['explicit_end'] === null;
                if (!$endsLoggedSession) {
                    $close($cur, false);
                    $cur = null;
                }
            }
            if ($cur === null) {
                if ($e['type'] === self::EVENT_DISCONNECT) {
                    continue; // une déconnexion sans présence connue n'ouvre pas de session
                }
                $cur = ['start' => $e['at'], 'last' => $e['at'], 'explicit_end' => null, 'events' => 1, 'server_id' => $e['server_id'], 'source' => $e['source'], 'logged' => $e['type'] === self::EVENT_CONNECT];
                continue;
            }
            $cur['last'] = max($cur['last'], $e['at']);
            $cur['events']++;
            $cur['server_id'] ??= $e['server_id'];
            $cur['logged'] = $cur['logged'] || $e['type'] === self::EVENT_CONNECT;
            if ($e['type'] === self::EVENT_DISCONNECT) {
                $cur['explicit_end'] = $cur['last'];
            } else {
                // Reconnexion ou signe de vie après une déconnexion rapprochée : la session continue.
                $cur['explicit_end'] = null;
            }
        }
        if ($cur !== null) {
            $ongoing = $cur['explicit_end'] === null && $now !== null && $now - $cur['last'] <= $gapSeconds;
            $close($cur, $ongoing);
        }

        return $sessions;
    }

    /**
     * @param array{start: int, last: int, explicit_end: ?int, events: int, server_id: ?int, source: string, logged: bool} $s
     * @return array{started_at: int, last_seen_at: int, ended_at: ?int, duration_s: int, aberrant: bool, ongoing: bool, source: string, server_id: ?int, events: int}
     */
    private static function finish(array $s, bool $ongoing): array
    {
        $raw = max(0, $s['last'] - $s['start']);
        $aberrant = $raw > self::MAX_SESSION_SECONDS;

        return [
            'started_at' => $s['start'],
            'last_seen_at' => $s['last'],
            'ended_at' => $ongoing ? null : ($s['explicit_end'] ?? $s['last']),
            'duration_s' => min($raw, self::MAX_SESSION_SECONDS),
            'aberrant' => $aberrant,
            'ongoing' => $ongoing,
            'source' => $s['source'],
            'server_id' => $s['server_id'],
            'events' => $s['events'],
        ];
    }

    /** Une session compte quand sa durée atteint le seuil (en direct comme une fois terminée). */
    public static function qualifies(array $session, int $minSeconds): bool
    {
        return (int) ($session['duration_s'] ?? 0) >= max(1, $minSeconds);
    }

    /**
     * Agrégats d'un joueur : sessions qui comptent sur 7/30/90 jours, durée cumulée, jours distincts, série,
     * dernière session qualifiante, fiabilité de la donnée.
     *
     * @param list<array<string, mixed>> $sessions sessions du joueur (started_at, last_seen_at, duration_s, aberrant, source, ongoing)
     * @param bool $linked le membre a un Steam relié à son compte
     * @return array<string, mixed>
     */
    public static function summarize(array $sessions, int $minSeconds, int $now, string $timezone = 'Europe/Paris', bool $linked = true, int $activeDays = 30): array
    {
        $minSeconds = max(60, $minSeconds);
        $tz = self::zone($timezone);
        $windows = [];
        foreach (self::WINDOWS as $d) {
            $windows[$d] = ['sessions' => 0, 'seconds' => 0, 'days' => 0, 'short' => 0, 'short_seconds' => 0];
        }
        $daysSeen = [];
        $perWindowDays = [];
        $last = null;
        $aberrant = 0;
        $reconstructed = 0;
        $qualifyingTotal = 0;
        $ongoingQualifying = false;
        $maxLast = null;
        foreach ($sessions as $s) {
            $start = (int) ($s['started_at'] ?? 0);
            $seen = (int) ($s['last_seen_at'] ?? $start);
            $dur = min(self::MAX_SESSION_SECONDS, max(0, (int) ($s['duration_s'] ?? ($seen - $start))));
            $ok = $dur >= $minSeconds;
            $maxLast = $maxLast === null ? $seen : max($maxLast, $seen);
            if (!empty($s['aberrant'])) {
                $aberrant++;
            }
            if (($s['source'] ?? '') === 'backfill') {
                $reconstructed++;
            }
            $day = (new DateTimeImmutable('@' . $start))->setTimezone($tz)->format('Y-m-d');
            foreach (self::WINDOWS as $d) {
                if ($seen < $now - $d * 86400) {
                    continue;
                }
                if ($ok) {
                    $windows[$d]['sessions']++;
                    $windows[$d]['seconds'] += $dur;
                    $perWindowDays[$d][$day] = true;
                } else {
                    $windows[$d]['short']++;
                    $windows[$d]['short_seconds'] += $dur;
                }
            }
            if ($ok) {
                $qualifyingTotal++;
                $daysSeen[$day] = true;
                if ($last === null || $start > $last['started_at']) {
                    $last = [
                        'started_at' => $start,
                        'ended_at' => !empty($s['ongoing']) ? null : ($s['ended_at'] ?? $seen),
                        'duration_s' => $dur,
                        'ongoing' => !empty($s['ongoing']),
                        'aberrant' => !empty($s['aberrant']),
                    ];
                }
                if (!empty($s['ongoing'])) {
                    $ongoingQualifying = true;
                }
            }
        }
        foreach (self::WINDOWS as $d) {
            $windows[$d]['days'] = count($perWindowDays[$d] ?? []);
        }

        $reliability = self::RELIABILITY_GOOD;
        if (!$linked) {
            $reliability = self::RELIABILITY_NO_UID;
        } elseif ($sessions === []) {
            $reliability = self::RELIABILITY_NO_DATA;
        } elseif ($aberrant > 0 || $reconstructed > 0) {
            $reliability = self::RELIABILITY_PARTIAL;
        }

        $activeSince = $now - max(1, $activeDays) * 86400;

        return [
            'min_seconds' => $minSeconds,
            'min_minutes' => intdiv($minSeconds, 60),
            'linked' => $linked,
            'has_data' => $sessions !== [],
            'windows' => $windows,
            'last_qualifying' => $last,
            'qualifying_total' => $qualifyingTotal,
            'streak_days' => self::streak(array_keys($daysSeen), $now, $tz),
            'aberrant' => $aberrant,
            'reconstructed' => $reconstructed,
            'ongoing_qualifying' => $ongoingQualifying,
            'last_seen_at' => $maxLast,
            'active' => $last !== null && (($last['ended_at'] ?? $now) >= $activeSince),
            'active_days' => max(1, $activeDays),
            'reliability' => $reliability,
        ];
    }

    /**
     * Série : jours locaux consécutifs avec au moins une session qui compte, jusqu'à aujourd'hui ou hier.
     *
     * @param list<string> $days dates « Y-m-d »
     */
    public static function streak(array $days, int $now, DateTimeZone $tz): int
    {
        if ($days === []) {
            return 0;
        }
        $set = array_flip($days);
        $cursor = (new DateTimeImmutable('@' . $now))->setTimezone($tz)->setTime(12, 0);
        if (!isset($set[$cursor->format('Y-m-d')])) {
            $cursor = $cursor->modify('-1 day');
        }
        $n = 0;
        while (isset($set[$cursor->format('Y-m-d')])) {
            $n++;
            $cursor = $cursor->modify('-1 day');
        }

        return $n;
    }

    /**
     * Cellule compacte des listes (colonne « Sessions de plus d'1 h » du tableur Effectifs) : sessions qui comptent
     * sur 30 jours, sessions plus courtes laissées de côté, actif ou non.
     *
     * @param array<string, mixed> $summary retour de summarize
     * @return array{sessions: int, seconds: int, short: int, active: bool, linked: bool, has_data: bool, min_minutes: int, last_at: ?int}
     */
    public static function rosterCell(array $summary): array
    {
        $w = is_array($summary['windows'][30] ?? null) ? $summary['windows'][30] : [];

        return [
            'sessions' => (int) ($w['sessions'] ?? 0),
            'seconds' => (int) ($w['seconds'] ?? 0),
            'short' => (int) ($w['short'] ?? 0),
            'active' => !empty($summary['active']),
            'linked' => !empty($summary['linked']),
            'has_data' => !empty($summary['has_data']),
            'min_minutes' => (int) ($summary['min_minutes'] ?? self::DEFAULT_MIN_MINUTES),
            'last_at' => isset($summary['last_qualifying']['started_at']) ? (int) $summary['last_qualifying']['started_at'] : null,
        ];
    }

    /** Résumé vide pour un membre sans donnée (clés identiques à summarize). */
    public static function emptySummary(int $minSeconds, bool $linked): array
    {
        return self::summarize([], $minSeconds, time(), 'Europe/Paris', $linked);
    }

    /** « plus d’1 h », « plus de 30 min », « plus d’1 h 30 », « plus de 2 h » : le seuil tel que configuré. */
    public static function thresholdLabel(int $minutes): string
    {
        $minutes = max(1, $minutes);
        if ($minutes < 60) {
            return 'plus de ' . $minutes . ' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        $hours = ($h === 1 ? '1 h' : $h . ' h') . ($m > 0 ? ' ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');

        return ($h === 1 ? 'plus d’' : 'plus de ') . $hours;
    }

    /** « 3 h 05 », « 45 min ». */
    public static function durationLabel(int $seconds): string
    {
        $m = intdiv(max(0, $seconds), 60);
        if ($m < 60) {
            return $m . ' min';
        }

        return intdiv($m, 60) . ' h ' . str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT);
    }

    private static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone !== '' ? $timezone : 'Europe/Paris');
        } catch (\Throwable) {
            return new DateTimeZone('Europe/Paris');
        }
    }

    private static function toTimestamp(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v > 0 ? $v : null;
        }
        if (is_string($v) && trim($v) !== '') {
            if (ctype_digit($v)) {
                return (int) $v > 0 ? (int) $v : null;
            }
            $t = strtotime($v);

            return $t === false ? null : $t;
        }

        return null;
    }
}
