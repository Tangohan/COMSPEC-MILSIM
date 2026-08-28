<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GradeDisplayService;
use PHPUnit\Framework\TestCase;

final class GradeDisplayServiceTest extends TestCase
{
    public function testHeaderShortCodeUsesPersonnelOverride(): void
    {
        $svc = new GradeDisplayService();
        $grade = [
            'label_short' => 'LCL',
            'label_long' => 'Lieutenant-colonel',
            'label_otan' => 'OF-4',
        ];
        $profile = [
            'rank_display' => 'Lieutenant Colonel',
            'rank_display_override' => 'O-5',
        ];

        self::assertSame('Lieutenant Colonel', $svc->headerTitle($grade, $profile));
        self::assertSame('O-5', $svc->headerShortCode($grade, $profile));
        self::assertSame('Lieutenant-colonel', $svc->headerTitle($grade, null));
        self::assertSame('OF-4', $svc->headerShortCode($grade, null));
    }
}
