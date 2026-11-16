<?php

declare(strict_types=1);

namespace App\Services\Effectifs\Availability;

use App\Services\Personnel\PlayerSessionService as Sessions;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Activité déduite des HEURES DE JEU remontées par le mod (table user_arma_playtime, via ArmaPlaytimeRepository).
 *
 * Cette source est un cumul : elle ne dit ni quand ni combien de temps d'affilée le membre a joué, seulement le total
 * et la date de la dernière remontée. Elle ne sert donc qu'en l'absence de sessions détaillées (player_sessions), qui
 * restent prioritaires. Service pur : aucune base, l'horloge est passée par l'appelant.
 *
 * Règle (le cumul doit atteindre au moins la durée qui compte de la communauté) :
 *  - Disponible : dernière remontée il y a 7 jours ou moins, ou cumul en hausse d'au moins la durée qui compte depuis la
 *    dernière vérification enregistrée (instantané du cumul) avec une remontée il y a 30 jours ou moins ;
 *  - Peu disponible : dernière remontée il y a 90 jours ou moins ;
 *  - Inactif : plus ancien.
 */
final class PlaytimeAvailability
{
    public const AVAILABLE_DAYS = 7;
    public const GAIN_DAYS = 30;
    public const LOW_DAYS = 90;

    /**
     * @param array<string, mixed> $playtime total_seconds, last_report_at
     * @param array<string, mixed>|null $previous instantané de la dernière vérification (total_seconds, checked_at)
     * @return array{key: ?string, total: int, last_at: ?int, ago: ?int, gained: ?int, previous_at: ?int, rule: string}
     */
    public static function activity(array $playtime, int $minSeconds, int $now, string $timezone, ?array $previous = null): array
    {
        $tz = self::zone($timezone);
        $total = max(0, (int) ($playtime['total_seconds'] ?? 0));
        $lastAt = self::ts($playtime['last_report_at'] ?? null);
        $ago = $lastAt !== null ? self::daysAgo($lastAt, $now, $tz) : null;
        $prevTotal = is_array($previous) && isset($previous['total_seconds']) ? max(0, (int) $previous['total_seconds']) : null;
        $prevAt = is_array($previous) ? self::ts($previous['checked_at'] ?? null) : null;
        $gained = $prevTotal !== null ? $total - $prevTotal : null;
        $out = ['key' => null, 'total' => $total, 'last_at' => $lastAt, 'ago' => $ago, 'gained' => $gained, 'previous_at' => $prevAt, 'rule' => ''];

        $min = max(60, $minSeconds);
        if ($total < $min || $lastAt === null || $ago === null) {
            $out['rule'] = $total <= 0
                ? 'aucune heure de jeu remontée'
                : ($lastAt === null ? 'la date de la dernière remontée est inconnue' : 'le cumul n’atteint pas la durée qui compte');

            return $out;
        }
        if ($ago <= self::AVAILABLE_DAYS) {
            $out['key'] = AvailabilityCheck::AVAILABLE;
            $out['rule'] = str_replace('%1', (string) $ago, 'Règle, sans session détaillée : cumul d’heures mis à jour il y a %1 jour(s) (7 jours ou moins).');
        } elseif ($gained !== null && $gained >= $min && $ago <= self::GAIN_DAYS) {
            $out['key'] = AvailabilityCheck::AVAILABLE;
            $out['rule'] = str_replace(['%1', '%2'], [Sessions::durationLabel($gained), (string) $ago], 'Règle, sans session détaillée : cumul en hausse de %1 depuis la dernière vérification (au moins la durée qui compte), mis à jour il y a %2 jour(s).');
        } elseif ($ago <= self::LOW_DAYS) {
            $out['key'] = AvailabilityCheck::LOW;
            $out['rule'] = str_replace('%1', (string) $ago, 'Règle, sans session détaillée : cumul d’heures mis à jour il y a %1 jour(s) (90 jours ou moins).');
        } else {
            $out['key'] = AvailabilityCheck::INACTIVE;
            $out['rule'] = str_replace('%1', (string) $ago, 'Règle, sans session détaillée : cumul d’heures sans mise à jour depuis %1 jour(s).');
        }

        return $out;
    }

    /**
     * Cellule des listes (tableau des effectifs) et de l'onglet Disponibilité pour un membre sans session détaillée.
     *
     * @param array<string, mixed> $summary ArmaPlaytimeRepository::summariesForUsers (une entrée)
     * @return array{key: ?string, total_seconds: int, hours_label: string, last_at: ?int, last_label: string, ago: ?int, state_label: string}|null null : aucun cumul
     */
    public static function cell(array $summary, int $minSeconds, int $now, string $timezone): ?array
    {
        $total = (int) ($summary['total_seconds'] ?? 0);
        if ($total <= 0) {
            return null;
        }
        $a = self::activity($summary, $minSeconds, $now, $timezone);

        return [
            'key' => $a['key'],
            'total_seconds' => $total,
            'hours_label' => $total >= 3600 ? intdiv($total, 3600) . ' h' : Sessions::durationLabel($total),
            'last_at' => $a['last_at'],
            'last_label' => $a['last_at'] !== null ? (new DateTimeImmutable('@' . $a['last_at']))->setTimezone(self::zone($timezone))->format('d/m') : '',
            'ago' => $a['ago'],
            'state_label' => $a['key'] !== null ? AvailabilityCheck::conclusionLabel($a['key']) : '',
        ];
    }

    private static function daysAgo(int $ts, int $now, DateTimeZone $tz): int
    {
        $a = new DateTimeImmutable((new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d'), $tz);
        $b = new DateTimeImmutable((new DateTimeImmutable('@' . $now))->setTimezone($tz)->format('Y-m-d'), $tz);

        return max(0, (int) $a->diff($b)->format('%r%a'));
    }

    private static function ts(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v > 0 ? $v : null;
        }
        $v = trim((string) $v);
        if ($v === '' || str_starts_with($v, '0000-00-00')) {
            return null;
        }
        $t = strtotime($v);

        return $t === false ? null : $t;
    }

    private static function zone(string $tz): DateTimeZone
    {
        try {
            return new DateTimeZone($tz !== '' ? $tz : 'Europe/Paris');
        } catch (\Throwable) {
            return new DateTimeZone('Europe/Paris');
        }
    }
}
