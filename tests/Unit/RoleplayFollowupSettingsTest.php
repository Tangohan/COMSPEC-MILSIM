<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\RoleplayFollowupSettings;
use PHPUnit\Framework\TestCase;

final class RoleplayFollowupSettingsTest extends TestCase
{
    public function testSanitizeClampsIntegersAndKeepsHistoricalCadenceDefaults(): void
    {
        $out = RoleplayFollowupSettings::sanitize([
            'bilans' => ['first_year_days' => 5, 'grace_days' => 999],
            'probation' => ['duration_days' => 2, 'bilan_label' => str_repeat('x', 200)],
        ]);

        self::assertSame(30, $out['bilans']['first_year_days']);
        self::assertSame(60, $out['bilans']['grace_days']);
        self::assertSame(14, $out['probation']['duration_days']);
        self::assertSame(80, strlen((string) $out['probation']['bilan_label']));
        self::assertFalse($out['cadence_reviewed']);
    }

    public function testMergePreservesUnknownKeysAndDoesNotInventCadenceReview(): void
    {
        $stored = [
            'enabled' => true,
            'legacy_custom_flag' => 'keep-me',
            'bilans' => ['first_year_days' => 200],
        ];
        $merged = RoleplayFollowupSettings::merge($stored, ['enabled' => false]);

        self::assertFalse($merged['enabled']);
        self::assertSame('keep-me', $merged['legacy_custom_flag']);
        self::assertSame(200, $merged['bilans']['first_year_days']);
        self::assertFalse($merged['cadence_reviewed']);
    }

    public function testPatchFromImmersionPostMarksCadenceReviewed(): void
    {
        $patch = RoleplayFollowupSettings::patchFromImmersionPost([
            'rp_followup_enabled' => '1',
            'rp_bilans_enabled' => '1',
            'rp_bilans_first_year_days' => '180',
        ]);

        self::assertTrue($patch['cadence_reviewed']);
        self::assertTrue($patch['enabled']);
        self::assertSame(180, $patch['bilans']['first_year_days']);
    }
}
