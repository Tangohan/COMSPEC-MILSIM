<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Support\ParisDateTime;
use PHPUnit\Framework\TestCase;

final class ParisDateTimeTest extends TestCase
{
    public function testSummerUtcTimestampUsesCest(): void
    {
        self::assertSame('04/09/2026 à 02:28:03', ParisDateTime::format('2026-09-04T00:28:03+00:00', 'd/m/Y à H:i:s'));
    }

    public function testWinterUtcTimestampUsesCet(): void
    {
        self::assertSame('04/01/2026 à 01:28:03', ParisDateTime::format('2026-01-04T00:28:03Z', 'd/m/Y à H:i:s'));
    }

    public function testBareDatabaseTimestampIsUtc(): void
    {
        self::assertSame('04/09/2026 02:28', ParisDateTime::format('2026-09-04 00:28:03', 'd/m/Y H:i'));
    }
}
