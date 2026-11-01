<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Effectifs\ElevationEligibility;
use PHPUnit\Framework\TestCase;

/** Critères d’éligibilité à une élévation réglés par communauté et leur évaluation. */
final class ElevationEligibilityTest extends TestCase
{
    public function testSettingsAreBoundedAndOnlyKeepTheCommunityQualifications(): void
    {
        $s = ElevationEligibility::sanitize([
            'min_months_in_grade' => '999',
            'min_presences_90' => -4,
            'min_presence_rate_90' => '150',
            'required_qualification_ids' => ['3', 7, 7, 'x', 42],
        ], [3, 7]);

        self::assertSame(240, $s['min_months_in_grade']);
        self::assertSame(0, $s['min_presences_90']);
        self::assertSame(100, $s['min_presence_rate_90']);
        self::assertSame([3, 7], $s['required_qualification_ids'], 'une qualification d’une autre communauté est refusée');
        self::assertTrue(ElevationEligibility::isConfigured($s));
        self::assertFalse(ElevationEligibility::isConfigured(ElevationEligibility::settings([])));
        self::assertSame(6, ElevationEligibility::settings(['elevation_eligibility' => ['min_months_in_grade' => 6]])['min_months_in_grade']);
    }

    public function testEligibleWhenEveryCriterionIsMet(): void
    {
        $settings = ElevationEligibility::sanitize(['min_months_in_grade' => 3, 'min_presences_90' => 5, 'min_presence_rate_90' => 80, 'required_qualification_ids' => [9]]);
        $out = ElevationEligibility::evaluate($settings, ['months_in_grade' => 4, 'presences_90' => 9, 'rate_90' => 90, 'valid_qualification_ids' => [9]], [9 => 'Combat Life Saver']);

        self::assertSame(ElevationEligibility::ELIGIBLE, $out['status']);
        self::assertSame(4, $out['met']);
        self::assertSame(4, $out['total']);
        self::assertSame('Qualification : Combat Life Saver', $out['criteria'][3]['label']);
    }

    public function testOneFailedCriterionMakesTheMemberNotEligible(): void
    {
        $settings = ElevationEligibility::sanitize(['min_presences_90' => 5, 'required_qualification_ids' => [9]]);
        $out = ElevationEligibility::evaluate($settings, ['months_in_grade' => null, 'presences_90' => 2, 'rate_90' => 40, 'valid_qualification_ids' => [9]]);

        self::assertSame(ElevationEligibility::NOT_ELIGIBLE, $out['status']);
        self::assertSame(1, $out['met']);
        self::assertFalse($out['criteria'][0]['met']);
    }

    public function testUnknownGradeDateIsReportedInsteadOfGuessed(): void
    {
        $settings = ElevationEligibility::sanitize(['min_months_in_grade' => 6]);
        $out = ElevationEligibility::evaluate($settings, ['months_in_grade' => null, 'presences_90' => 0, 'rate_90' => null, 'valid_qualification_ids' => []]);

        self::assertSame(ElevationEligibility::UNKNOWN, $out['status']);
        self::assertNull($out['criteria'][0]['met']);
        self::assertStringContainsString('inconnue', $out['criteria'][0]['detail']);
    }

    public function testMonthsAreCompletedCalendarMonths(): void
    {
        $now = new \DateTimeImmutable('2026-11-01');
        self::assertSame(2, ElevationEligibility::monthsBetween('2026-08-12', $now));
        self::assertSame(3, ElevationEligibility::monthsBetween('2026-08-01 10:00:00', $now));
        self::assertNull(ElevationEligibility::monthsBetween('', $now));
    }
}
