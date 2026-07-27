<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\SeniorityEngine;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Le décompte est celui du temps écoulé : du 1er au 31 janvier, 30 jours. Les quatre
 * premiers tests fixent ce contrat de longue date ; les suivants sont des verrous ajoutés
 * après constat sur les données réelles — ancienneté qui courait avant la date d’arrivée,
 * date invalide affichant des siècles ou faisant tomber la page, libellé annonçant douze
 * mois.
 */
final class SeniorityEngineTest extends TestCase
{
    private const REFERENCE = '2026-04-15';

    public function testComputeFromStartUsesOldestPeriod(): void
    {
        $engine = new SeniorityEngine();

        $result = $engine->compute(
            ['calc_mode' => 'from_start'],
            [
                ['start_date' => '2025-01-01'],
                ['start_date' => '2023-05-10'],
            ],
            new DateTimeImmutable('2026-04-15')
        );

        self::assertSame(1071, $result['days']);
        self::assertSame('2 ans, 11 mois, 6 jours', $result['formatted']);
        self::assertSame('2026-04-15', $result['reference_date']);
    }

    public function testComputeSumPeriodsMergesOverlaps(): void
    {
        $engine = new SeniorityEngine();

        $result = $engine->compute(
            ['calc_mode' => 'sum_periods'],
            [
                ['start_date' => '2024-01-01', 'end_date' => '2024-02-01'],
                ['start_date' => '2024-01-15', 'end_date' => '2024-03-01'],
            ],
            new DateTimeImmutable('2026-04-15')
        );

        self::assertSame(60, $result['days']);
        self::assertSame('1 mois, 30 jours', $result['formatted']);
    }

    public function testComputeActiveOnlyFiltersInactiveRows(): void
    {
        $engine = new SeniorityEngine();

        $result = $engine->compute(
            ['calc_mode' => 'active_only'],
            [
                ['start_date' => '2024-01-01', 'end_date' => '2024-01-31', 'status' => 'active'],
                ['start_date' => '2024-02-01', 'end_date' => '2024-03-01', 'status' => 'inactive'],
            ],
            new DateTimeImmutable('2026-04-15')
        );

        self::assertSame(30, $result['days']);
        self::assertSame('30 jours', $result['formatted']);
    }

    public function testFormatDaysOmitsZeroParts(): void
    {
        $engine = new SeniorityEngine();

        $result = $engine->compute(
            ['calc_mode' => 'sum_periods'],
            [
                ['start_date' => '2026-04-01', 'end_date' => '2026-04-08'],
            ],
            new DateTimeImmutable('2026-04-15')
        );

        self::assertSame(7, $result['days']);
        self::assertSame('7 jours', $result['formatted']);
    }

    /**
     * Une date d’arrivée dans le futur créait de l’ancienneté à rebours : `diff()->days`
     * est une valeur absolue, le `max(0, …)` qui l’entourait ne rattrapait rien.
     */
    public function testFutureStartDateProducesNoSeniority(): void
    {
        $result = $this->compute(['calc_mode' => 'from_start'], [
            ['start_date' => '2027-01-01'],
        ]);

        self::assertSame(0, $result['days']);
        self::assertSame('—', $result['formatted']);
    }

    public function testFutureEndDateDoesNotCountInAdvance(): void
    {
        $planned = $this->compute(['calc_mode' => 'sum_periods'], [
            ['start_date' => '2025-01-01', 'end_date' => '2030-01-01'],
        ]);
        $open = $this->compute(['calc_mode' => 'sum_periods'], [
            ['start_date' => '2025-01-01', 'end_date' => null],
        ]);

        self::assertSame($open['days'], $planned['days']);
    }

    /**
     * `0000-00-00` existe réellement dans les colonnes DATE et donnait « 2028 ans » ; une
     * chaîne non parsable faisait remonter une exception jusqu’à la page.
     */
    public function testUnusableDatesAreIgnoredWithoutThrowing(): void
    {
        foreach (['', '0000-00-00', 'pas une date', '   '] as $raw) {
            $result = $this->compute(['calc_mode' => 'from_start'], [['start_date' => $raw]]);
            self::assertSame(0, $result['days'], var_export($raw, true));
            self::assertSame('—', $result['formatted'], var_export($raw, true));
        }
    }

    public function testUnusableDateDoesNotHideValidPeriods(): void
    {
        $result = $this->compute(['calc_mode' => 'sum_periods'], [
            ['start_date' => '0000-00-00', 'end_date' => null],
            ['start_date' => '2026-04-01', 'end_date' => '2026-04-11'],
        ]);

        self::assertSame(10, $result['days']);
    }

    /**
     * Deux périodes jointives doivent totaliser exactement ce que totalise la même durée
     * saisie d’un seul bloc. La charnière n’était comptée dans aucune des deux.
     */
    public function testAdjacentPeriodsMatchTheEquivalentSinglePeriod(): void
    {
        $split = $this->compute(['calc_mode' => 'sum_periods'], [
            ['start_date' => '2025-01-01', 'end_date' => '2025-01-31'],
            ['start_date' => '2025-02-01', 'end_date' => '2025-02-28'],
        ]);
        $single = $this->compute(['calc_mode' => 'sum_periods'], [
            ['start_date' => '2025-01-01', 'end_date' => '2025-02-28'],
        ]);

        self::assertSame(58, $single['days']);
        self::assertSame($single['days'], $split['days']);
    }

    public function testMissingStatusIsTreatedAsActive(): void
    {
        $withNull = $this->compute(['calc_mode' => 'active_only'], [
            ['start_date' => '2025-01-01', 'end_date' => '2025-06-30', 'status' => null],
        ]);
        $withEmpty = $this->compute(['calc_mode' => 'active_only'], [
            ['start_date' => '2025-01-01', 'end_date' => '2025-06-30', 'status' => ''],
        ]);

        self::assertSame(180, $withNull['days']);
        self::assertSame($withNull['days'], $withEmpty['days']);
    }

    /**
     * Un mode inconnu renvoyait zéro : toute la communauté affichait « — » sans que rien
     * n’en signale la cause. Il retombe désormais sur le mode par défaut.
     */
    public function testUnknownCalcModeFallsBackToDefault(): void
    {
        $unknown = $this->compute(['calc_mode' => 'mode_inexistant'], [['start_date' => '2020-01-01']]);
        $default = $this->compute([], [['start_date' => '2020-01-01']]);

        self::assertGreaterThan(0, $unknown['days']);
        self::assertSame($default['days'], $unknown['days']);
    }

    /**
     * Le découpage par mois de 30 jours et années de 365 affichait « 12 mois, 4 jours » à
     * 364 jours — plus de mois qu’il n’en existe, et un libellé non monotone.
     */
    public function testLabelNeverExceedsElevenMonths(): void
    {
        foreach ($this->labelsOverFourYears() as $days => $label) {
            self::assertStringNotContainsString('12 mois', $label, "à {$days} jours");
            self::assertStringNotContainsString('13 mois', $label, "à {$days} jours");
        }
    }

    public function testDayCountIsStrictlyIncreasing(): void
    {
        $previous = -1;
        foreach ($this->dayCountsOverFourYears() as $days => $computed) {
            self::assertGreaterThan($previous, $computed, "à {$days} jours");
            $previous = $computed;
        }
    }

    public function testEmptyPeriodListIsRenderedConsistently(): void
    {
        foreach (['from_start', 'sum_periods', 'active_only', 'custom_rule'] as $mode) {
            $result = $this->compute(['calc_mode' => $mode], []);
            self::assertSame(0, $result['days'], $mode);
            // Toujours le même tiret cadratin : `from_start` renvoyait un trait d’union.
            self::assertSame('—', $result['formatted'], $mode);
        }
    }

    /**
     * @return array<int, string>
     */
    private function labelsOverFourYears(): array
    {
        $out = [];
        foreach ($this->spanResults() as $days => $result) {
            $out[$days] = $result['formatted'];
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private function dayCountsOverFourYears(): array
    {
        $out = [];
        foreach ($this->spanResults() as $days => $result) {
            $out[$days] = $result['days'];
        }

        return $out;
    }

    /**
     * @return array<int, array{formatted: string, days: int, reference_date: string}>
     */
    private function spanResults(): array
    {
        $engine = new SeniorityEngine();
        $reference = new DateTimeImmutable(self::REFERENCE);
        $out = [];

        for ($days = 1; $days <= 1460; $days++) {
            $start = $reference->modify('-' . $days . ' days');
            $out[$days] = $engine->compute(
                ['calc_mode' => 'from_start'],
                [['start_date' => $start->format('Y-m-d')]],
                $reference
            );
        }

        return $out;
    }

    /**
     * @param array{calc_mode?: string} $definition
     * @param list<array{start_date: string, end_date?: ?string, status?: ?string}> $periods
     * @return array{formatted: string, days: int, reference_date: string}
     */
    private function compute(array $definition, array $periods): array
    {
        return (new SeniorityEngine())->compute($definition, $periods, new DateTimeImmutable(self::REFERENCE));
    }
}
