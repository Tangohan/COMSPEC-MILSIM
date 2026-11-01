<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Présence d’un membre aux sessions de sa communauté (événements du calendrier, pointage).
 *
 * Une « session » est un événement passé et non annulé de la communauté, postérieur à l’engagement
 * du membre. Classement d’une session pour le membre :
 * - présent : pointage enregistré (le membre ou un responsable a validé sa présence) ;
 * - absent excusé : le membre a répondu « absent » (motif autre que « absence non justifiée ») ;
 * - absent sans prévenir : inscrit présent sans pointage, ou absence marquée « non justifiée » ;
 * - sans réponse : aucune réponse ou « peut-être » sans pointage.
 *
 * Taux de présence sur 90 jours = sessions pointées / sessions des 90 derniers jours.
 */
final class MemberAttendanceSummary
{
    public const GRID_SIZE = 26;
    public const WINDOW_DAYS = 90;

    public const PRESENT = 'present';
    public const EXCUSED = 'excused';
    public const ABSENT = 'absent';
    public const NO_REPLY = 'no_reply';

    public const LABELS = [
        self::PRESENT => 'Présent',
        self::EXCUSED => 'Absent excusé',
        self::ABSENT => 'Absent sans prévenir',
        self::NO_REPLY => 'Sans réponse',
    ];

    /** @param array<string, mixed>|null $rsvp */
    public static function classify(?array $rsvp): string
    {
        if ($rsvp === null) {
            return self::NO_REPLY;
        }
        if (trim((string) ($rsvp['checked_in_at'] ?? '')) !== '') {
            return self::PRESENT;
        }
        $status = (string) ($rsvp['status'] ?? '');
        if ($status === 'no') {
            return (string) ($rsvp['absence_reason'] ?? '') === 'absence_non_justifiee' ? self::ABSENT : self::EXCUSED;
        }
        if ($status === 'yes') {
            return self::ABSENT;
        }

        return self::NO_REPLY;
    }

    /**
     * @param list<array<string, mixed>> $events événements passés non annulés de la communauté (id, starts_at, title)
     * @param array<int, array<string, mixed>> $rsvpByEvent réponses du membre indexées par événement
     * @param ?string $joinedOn date d’engagement (les sessions antérieures sont ignorées)
     * @return array{
     *   grid: list<array{event_id:int,date:string,title:string,state:string}>,
     *   grid_counts: array<string,int>,
     *   rate_90: ?int,
     *   rate_prev_90: ?int,
     *   delta_points: ?int,
     *   sessions_90: int,
     *   present_90: int,
     *   present_total: int
     * }
     */
    public static function summarize(array $events, array $rsvpByEvent, ?string $joinedOn, \DateTimeImmutable $now): array
    {
        $joinTs = self::ts((string) $joinedOn);
        $nowTs = $now->getTimestamp();
        $from90 = $nowTs - self::WINDOW_DAYS * 86400;
        $from180 = $nowTs - 2 * self::WINDOW_DAYS * 86400;

        $sessions = [];
        foreach ($events as $event) {
            $start = self::ts((string) ($event['starts_at'] ?? ''));
            $id = (int) ($event['id'] ?? 0);
            if ($id < 1 || $start < 1 || $start >= $nowTs || ($joinTs > 0 && $start < $joinTs)) {
                continue;
            }
            $sessions[] = [
                'event_id' => $id,
                'ts' => $start,
                'date' => date('Y-m-d', $start),
                'title' => trim((string) ($event['title'] ?? '')),
                'state' => self::classify($rsvpByEvent[$id] ?? null),
            ];
        }
        usort($sessions, static fn (array $a, array $b): int => $b['ts'] <=> $a['ts'] ?: $b['event_id'] <=> $a['event_id']);

        $presentTotal = 0;
        $n90 = 0;
        $p90 = 0;
        $nPrev = 0;
        $pPrev = 0;
        foreach ($sessions as $s) {
            $present = $s['state'] === self::PRESENT;
            $presentTotal += $present ? 1 : 0;
            if ($s['ts'] >= $from90) {
                $n90++;
                $p90 += $present ? 1 : 0;
            } elseif ($s['ts'] >= $from180) {
                $nPrev++;
                $pPrev += $present ? 1 : 0;
            }
        }

        $grid = array_reverse(array_slice($sessions, 0, self::GRID_SIZE));
        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($grid as &$cell) {
            $counts[$cell['state']]++;
            unset($cell['ts']);
        }
        unset($cell);

        $rate = $n90 > 0 ? (int) round($p90 * 100 / $n90) : null;
        $ratePrev = $nPrev > 0 ? (int) round($pPrev * 100 / $nPrev) : null;

        return [
            'grid' => $grid,
            'grid_counts' => $counts,
            'rate_90' => $rate,
            'rate_prev_90' => $ratePrev,
            'delta_points' => $rate !== null && $ratePrev !== null ? $rate - $ratePrev : null,
            'sessions_90' => $n90,
            'present_90' => $p90,
            'present_total' => $presentTotal,
        ];
    }

    private static function ts(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return 0;
        }
        $ts = strtotime($raw);

        return $ts !== false ? $ts : 0;
    }
}
