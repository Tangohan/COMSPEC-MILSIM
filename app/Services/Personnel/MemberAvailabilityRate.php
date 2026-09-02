<?php

declare(strict_types=1);

namespace App\Services\Personnel;

/**
 * Taux de disponibilité d’un membre : moyenne de la participation annoncée
 * (présence prévue) et de la présence validée, sur une fenêtre glissante.
 */
final class MemberAvailabilityRate
{
    public const WINDOW_DAYS = 90;

    /**
     * @return array{
     *     sample: bool,
     *     events: int,
     *     yes: int,
     *     checked_in: int,
     *     participation_pct: ?int,
     *     presence_pct: ?int,
     *     availability_pct: ?int,
     *     hue: int,
     *     label: ?string,
     *     hint: string
     * }
     */
    public static function fromCounts(int $events, int $yes, int $checkedIn, int $windowDays = self::WINDOW_DAYS): array
    {
        $events = max(0, $events);
        $yes = max(0, $yes);
        $checkedIn = max(0, $checkedIn);
        if ($events > 0) {
            $yes = min($yes, $events);
        }
        $checkedIn = min($checkedIn, $yes);
        $days = max(1, $windowDays);

        if ($events < 1) {
            return [
                'sample' => false,
                'events' => 0,
                'yes' => 0,
                'checked_in' => 0,
                'participation_pct' => null,
                'presence_pct' => null,
                'availability_pct' => null,
                'hue' => 0,
                'label' => null,
                'hint' => 'Aucune activité sur les ' . $days . ' derniers jours',
            ];
        }

        $participation = $yes / $events;
        $presence = $yes > 0 ? ($checkedIn / $yes) : 0.0;
        $pct = (int) round((($participation + $presence) / 2) * 100);
        $pct = max(0, min(100, $pct));
        $partPct = (int) round($participation * 100);
        $presPct = $yes > 0 ? (int) round($presence * 100) : null;

        return [
            'sample' => true,
            'events' => $events,
            'yes' => $yes,
            'checked_in' => $checkedIn,
            'participation_pct' => $partPct,
            'presence_pct' => $presPct,
            'availability_pct' => $pct,
            'hue' => self::barHue($pct),
            'label' => $pct . ' %',
            'hint' => self::hint($days, $events, $yes, $checkedIn),
        ];
    }

    public static function barHue(int $pct): int
    {
        return (int) round(max(0, min(100, $pct)) * 1.2);
    }

    private static function hint(int $days, int $events, int $yes, int $checkedIn): string
    {
        return sprintf(
            'Sur %d jours : %s sur %s, %s.',
            $days,
            self::frCount($yes, 'participation annoncée', 'participations annoncées'),
            self::frCount($events, 'activité', 'activités'),
            self::frCount($checkedIn, 'présence validée', 'présences validées')
        );
    }

    private static function frCount(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n > 1 ? $many : $one);
    }
}
