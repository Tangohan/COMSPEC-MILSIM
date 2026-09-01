<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Calendar\IcalendarService;
use PHPUnit\Framework\TestCase;

final class IcalendarServiceTest extends TestCase
{
    public function testBuildEventIncludesUidUtcFoldingCrlfAndCancelled(): void
    {
        $svc = new IcalendarService();
        $ics = $svc->buildEventCalendar([
            'uid' => 'rdv-42@athena.test',
            'summary' => 'Accueil, briefing; suite',
            'description' => str_repeat('A', 90),
            'location' => 'Salle 1',
            'starts_at' => '2026-09-02 10:00:00',
            'ends_at' => '2026-09-02 11:00:00',
            'dtstamp' => '2026-09-01 12:00:00 UTC',
            'status' => 'CANCELLED',
        ], 'Intégration');

        self::assertStringContainsString("\r\n", $ics);
        self::assertStringContainsString('UID:rdv-42@athena.test', $ics);
        self::assertStringContainsString('DTSTAMP:', $ics);
        self::assertStringContainsString('Z', $ics);
        self::assertStringContainsString('STATUS:CANCELLED', $ics);
        self::assertStringContainsString('\\,', $ics);
        self::assertStringContainsString('\\;', $ics);
        self::assertMatchesRegularExpression("/\r\n [A-Za-z]/", $ics);
        self::assertStringEndsWith("\r\n", $ics);
    }
}
