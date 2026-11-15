<?php

declare(strict_types=1);

namespace App\Services\Jnet;

/**
 * Agenda de l'espace commun (calcul pur, testé sans base) : met en forme les événements à venir de la communauté
 * (table community_events, la même que la page « Événements ») et les groupe par jour. La réponse du membre
 * (présent, peut-être, absent) est celle qu'il a déjà donnée sur le site ; l'écriture passe par la route existante.
 */
final class JnetAgenda
{
    /** Types d'événement du site : clé => libellé. */
    public const TYPES = [
        'operation' => 'Opération',
        'formation' => 'Formation',
        'evenement' => 'Événement',
        'autre' => 'Autre',
    ];

    /** Réponses possibles : clé de la base => libellé. */
    public const RSVP = [
        'yes' => 'Présent',
        'maybe' => 'Incertain',
        'no' => 'Absent',
    ];

    public const LIMIT = 40;

    /** Jours de la semaine (0 = dimanche), courts puis longs, comme date('w'). */
    public const WEEKDAYS_SHORT = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    public const WEEKDAYS_LONG = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

    /** Libellé du type, « Événement » pour un type inconnu. */
    public static function typeKey(string $raw): string
    {
        $raw = strtolower(trim($raw));

        return isset(self::TYPES[$raw]) ? $raw : 'evenement';
    }

    /**
     * Un événement prêt à afficher.
     *
     * @param array<string, mixed> $row ligne de CommunityEventRepository::upcomingForTenantWithUserRsvp
     * @param array{yes: int, maybe: int, no: int}|null $counts réponses déjà données (lecture groupée), ou null
     * @return array<string, mixed>
     */
    public static function normalize(array $row, int $now, ?array $counts = null): array
    {
        $start = strtotime((string) ($row['starts_at'] ?? '')) ?: 0;
        $end = strtotime((string) ($row['ends_at'] ?? '')) ?: $start;
        $typeKey = self::typeKey((string) ($row['event_type'] ?? ''));
        $rsvp = (string) ($row['rsvp_status'] ?? '');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => trim((string) ($row['title'] ?? '')),
            'where' => trim((string) ($row['location'] ?? '')),
            'typeKey' => $typeKey,
            'type' => self::TYPES[$typeKey],
            'startTs' => $start,
            'endTs' => max($start, $end),
            'dateKey' => $start > 0 ? date('Y-m-d', $start) : '',
            'dayLabel' => self::dayLabel($start, $now),
            'dateFr' => $start > 0 ? date('d/m/Y', $start) : '',
            'weekday' => $start > 0 ? (int) date('w', $start) : 0,
            'dayNum' => $start > 0 ? (int) date('j', $start) : 0,
            'time' => $start > 0 ? date('H:i', $start) : '',
            'rsvp' => isset(self::RSVP[$rsvp]) ? $rsvp : '',
            'counts' => $counts ?? ['yes' => 0, 'maybe' => 0, 'no' => 0],
            'ongoing' => $start > 0 && $start <= $now && max($start, $end) >= $now,
        ];
    }

    /**
     * Les événements du type demandé (« » = tous), groupés par jour, du plus proche au plus lointain.
     *
     * @param list<array<string, mixed>> $events événements normalisés
     * @return list<array{key: string, label: string, weekday: int, date: string, today: bool, items: list<array<string, mixed>>}>
     */
    public static function group(array $events, string $type, int $now): array
    {
        $groups = [];
        $index = [];
        foreach ($events as $e) {
            if ($type !== '' && $e['typeKey'] !== $type) {
                continue;
            }
            $key = (string) $e['dateKey'];
            if (!isset($index[$key])) {
                $index[$key] = count($groups);
                $groups[] = ['key' => $key, 'label' => (string) $e['dayLabel'], 'weekday' => (int) $e['weekday'], 'date' => (string) $e['dateFr'], 'today' => $key === date('Y-m-d', $now), 'items' => []];
            }
            $groups[$index[$key]]['items'][] = $e;
        }

        return $groups;
    }

    /** « Aujourd’hui », « Demain » ou rien (le jour de la semaine et la date suffisent). */
    public static function dayLabel(int $startTs, int $now): string
    {
        if ($startTs <= 0) {
            return '';
        }

        return match (self::daysUntil($startTs, $now)) {
            0 => 'Aujourd’hui',
            1 => 'Demain',
            default => '',
        };
    }

    /** Nombre de jours calendaires entre aujourd'hui et le début (0 = aujourd'hui, négatif = déjà commencé un autre jour). */
    public static function daysUntil(int $startTs, int $now): int
    {
        $a = strtotime(date('Y-m-d', $now) . ' 12:00:00') ?: $now;
        $b = strtotime(date('Y-m-d', $startTs) . ' 12:00:00') ?: $startTs;

        return (int) round(($b - $a) / 86400);
    }

    /** Intitulé du jeton de proximité : « J-5 », « Aujourd’hui » ou « En cours ». */
    public static function countdown(int $days, bool $ongoing): string
    {
        if ($ongoing) {
            return 'En cours';
        }

        return $days <= 0 ? 'Aujourd’hui' : 'J-' . $days;
    }
}
