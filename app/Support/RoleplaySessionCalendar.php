<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Calendrier unique de l'agenda : les sessions de jeu s'y affichent comme un type d'événement.
 * Les deux tables restent séparées : une session liée à un événement marque simplement sa pastille,
 * une session sans événement obtient la sienne. Aucune donnée n'est déplacée.
 */
final class RoleplaySessionCalendar
{
    public const TYPE = 'session';

    /**
     * @param list<array<string, mixed>> $events lignes du registre (déjà filtrées)
     * @param list<array<string, mixed>> $sessions sessions du mois, annulées exclues
     * @param array{type?: string, statut?: string, q?: string} $filters
     * @return list<array<string, mixed>> triées par début
     */
    public static function merge(array $events, array $sessions, array $filters = []): array
    {
        $type = trim((string) ($filters['type'] ?? ''));
        $byEvent = [];
        foreach ($sessions as $s) {
            $eventId = (int) ($s['community_event_id'] ?? 0);
            if ($eventId > 0) {
                $byEvent[$eventId] = (int) ($s['id'] ?? 0);
            }
        }
        $out = [];
        if ($type !== self::TYPE) {
            foreach ($events as $ev) {
                $ev['linked_session_id'] = $byEvent[(int) ($ev['id'] ?? 0)] ?? 0;
                $out[] = $ev;
            }
        }
        if ($type === '' || $type === self::TYPE) {
            foreach ($sessions as $s) {
                $row = self::row($s);
                if ((int) ($s['community_event_id'] ?? 0) <= 0 && $row !== null && self::matches($row, $filters)) {
                    $out[] = $row;
                }
            }
        }
        usort($out, static fn (array $a, array $b): int => strcmp((string) ($a['starts_at'] ?? ''), (string) ($b['starts_at'] ?? '')));

        return $out;
    }

    /**
     * @param array<string, mixed> $s
     * @return array<string, mixed>|null
     */
    private static function row(array $s): ?array
    {
        $start = trim((string) ($s['started_at'] ?? ''));
        if ($start === '') {
            $start = trim((string) ($s['planned_starts_at'] ?? ''));
        }
        if ($start === '') {
            $start = trim((string) ($s['created_at'] ?? ''));
        }
        if ($start === '') {
            return null;
        }

        return [
            'calendar_kind' => self::TYPE,
            'id' => (int) ($s['id'] ?? 0),
            'title' => trim((string) ($s['mission_name'] ?? '')) ?: 'Session',
            'starts_at' => $start,
            'event_type' => self::TYPE,
            'session_phase' => RoleplaySessionBoard::phase($s),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array{type?: string, statut?: string, q?: string} $filters
     */
    private static function matches(array $row, array $filters): bool
    {
        $statut = trim((string) ($filters['statut'] ?? ''));
        $phase = (string) ($row['session_phase'] ?? '');
        $wanted = match ($statut) {
            'planifie' => RoleplaySessionBoard::UPCOMING,
            'en_cours' => RoleplaySessionBoard::LIVE,
            'clos' => RoleplaySessionBoard::PAST,
            '' => $phase,
            default => '',
        };
        if ($phase !== $wanted) {
            return false;
        }
        $q = trim((string) ($filters['q'] ?? ''));

        return $q === '' || mb_stripos((string) ($row['title'] ?? ''), $q) !== false;
    }
}
