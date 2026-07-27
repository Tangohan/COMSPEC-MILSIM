<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use DateInterval;
use DateTimeImmutable;
use Throwable;

/**
 * Calcul des anciennetés à partir des périodes enregistrées.
 *
 * Deux conventions valent d’être posées, parce qu’elles gouvernent tous les résultats :
 *
 * - **Décompte du temps écoulé.** Une période du 1er au 31 janvier vaut 30 jours : on
 *   compte l’écart entre les deux dates, pas le nombre de journées touchées. Pour que
 *   deux périodes jointives totalisent exactement ce que totalise la même durée saisie
 *   d’un seul bloc, les périodes qui se touchent sont fusionnées avant le décompte.
 * - **Rien ne compte au-delà de la date de référence.** Un engagement daté du futur ou
 *   une fin de période prévisionnelle ne créent pas d’ancienneté par anticipation.
 *
 * Les dates viennent de colonnes DATE qui peuvent contenir NULL, une chaîne vide ou
 * `0000-00-00` : elles sont ignorées plutôt que de faire tomber la page.
 */
final class SeniorityEngine
{
    /**
     * @param array{calc_mode?: string} $definition
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    public function compute(array $definition, array $periods, ?DateTimeImmutable $referenceDate = null): array
    {
        $referenceDate = ($referenceDate ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $mode = (string) ($definition['calc_mode'] ?? 'from_start');

        return match ($mode) {
            'sum_periods' => $this->computeSumPeriods($periods, $referenceDate),
            'active_only' => $this->computeActiveOnly($periods, $referenceDate),
            'custom_rule' => $this->computeCustomRule($periods, $referenceDate),
            // `from_start` est le mode par défaut : un mode inconnu (typo en base, valeur
            // d’une version ultérieure) y retombe. Renvoyer zéro afficherait « — » pour
            // toute la communauté sans que rien ne signale la cause.
            default => $this->computeFromStart($periods, $referenceDate),
        };
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeFromStart(array $periods, DateTimeImmutable $referenceDate): array
    {
        $earliest = null;
        foreach ($periods as $period) {
            $start = $this->parseDate($period['start_date'] ?? null);
            if ($start === null || $start > $referenceDate) {
                continue;
            }
            if ($earliest === null || $start < $earliest) {
                $earliest = $start;
            }
        }

        if ($earliest === null) {
            return $this->result(0, $referenceDate);
        }

        return $this->result($this->spanDays($earliest, $referenceDate), $referenceDate);
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeSumPeriods(array $periods, DateTimeImmutable $referenceDate): array
    {
        $normalized = [];
        foreach ($periods as $period) {
            $start = $this->parseDate($period['start_date'] ?? null);
            if ($start === null || $start > $referenceDate) {
                continue;
            }

            $end = $this->parseDate($period['end_date'] ?? null) ?? $referenceDate;
            // Une fin prévisionnelle ne doit pas créer d’ancienneté d’avance.
            if ($end > $referenceDate) {
                $end = $referenceDate;
            }
            if ($end < $start) {
                continue;
            }

            $normalized[] = ['start' => $start, 'end' => $end];
        }

        if ($normalized === []) {
            return $this->result(0, $referenceDate);
        }

        usort($normalized, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $merged = [];
        foreach ($normalized as $range) {
            $lastIndex = count($merged) - 1;
            if ($lastIndex < 0) {
                $merged[] = $range;
                continue;
            }

            // Le « +1 jour » fusionne aussi les périodes jointives (fin le 31, reprise le
            // 1er). Sans lui, la charnière n’était comptée dans aucune des deux : janvier
            // puis février saisis séparément donnaient un jour de moins que le même
            // intervalle saisi d’un bloc.
            $last = $merged[$lastIndex];
            if ($range['start'] <= $last['end']->add(new DateInterval('P1D'))) {
                if ($range['end'] > $last['end']) {
                    $merged[$lastIndex]['end'] = $range['end'];
                }
                continue;
            }

            $merged[] = $range;
        }

        $days = 0;
        foreach ($merged as $range) {
            $days += $this->spanDays($range['start'], $range['end']);
        }

        return $this->result($days, $referenceDate);
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeActiveOnly(array $periods, DateTimeImmutable $referenceDate): array
    {
        $filtered = array_values(array_filter(
            $periods,
            static fn (array $period): bool => (($period['status'] ?? 'active') ?: 'active') === 'active'
        ));

        return $this->computeSumPeriods($filtered, $referenceDate);
    }

    /**
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     *
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function computeCustomRule(array $periods, DateTimeImmutable $referenceDate): array
    {
        // Les règles avancées sont injectées via seniority_rules ; fallback temporaire.
        return $this->computeSumPeriods($periods, $referenceDate);
    }

    /**
     * Temps écoulé entre deux dates, en jours.
     */
    private function spanDays(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        if ($end < $start) {
            return 0;
        }

        return (int) $start->diff($end)->days;
    }

    /**
     * Les dates viennent de colonnes DATE : NULL, chaîne vide et `0000-00-00` sont des
     * valeurs qu’on rencontre réellement. `0000-00-00` interprété littéralement donnait
     * « 2028 ans » d’ancienneté, et une chaîne non parsable faisait tomber la page.
     */
    private function parseDate(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw)) {
            return null;
        }
        $value = trim($raw);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }

        return $date->setTime(0, 0);
    }

    /**
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function result(int $days, DateTimeImmutable $referenceDate): array
    {
        return [
            'formatted' => $this->formatDays($days),
            'days' => $days,
            'reference_date' => $referenceDate->format('Y-m-d'),
        ];
    }

    /**
     * Met le total en années / mois / jours.
     *
     * Le mois vaut ici un douzième d’année (30,44 jours) et non 30 jours ronds. Avec des
     * mois de 30 jours, le reste d’une année pouvait en contenir douze : 364 jours
     * s’affichaient « 12 mois, 4 jours », soit plus de mois qu’il n’en existe, et le
     * libellé n’était même pas croissant.
     *
     * Le découpage reste volontairement indépendant de la date de référence. Un total
     * cumulé n’est rattaché à aucune période du calendrier : l’ancrer sur le jour de
     * consultation ferait varier le libellé d’un même total au fil des mois.
     */
    private function formatDays(int $days): string
    {
        if ($days < 1) {
            return '—';
        }

        $years = intdiv($days, 365);
        $remaining = $days - ($years * 365);
        $months = min(11, (int) floor($remaining / (365 / 12)));
        $finalDays = max(0, (int) round($remaining - ($months * (365 / 12))));

        $parts = [];
        if ($years > 0) {
            $parts[] = $years === 1 ? '1 an' : $years . ' ans';
        }
        if ($months > 0) {
            $parts[] = $months . ' mois';
        }
        if ($finalDays > 0) {
            $parts[] = $finalDays === 1 ? '1 jour' : $finalDays . ' jours';
        }

        return $parts !== [] ? implode(', ', $parts) : '—';
    }
}
