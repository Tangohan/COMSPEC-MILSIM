<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PlatformProductionStatus;
use PHPUnit\Framework\TestCase;

final class PlatformProductionStatusTest extends TestCase
{
    public function testSnapshotExposesPlatformAndPackVersions(): void
    {
        $snap = PlatformProductionStatus::snapshot();

        self::assertArrayHasKey('platform_version', $snap);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $snap['platform_version']);
        self::assertNotSame('', $snap['deployed_at_label']);
        self::assertArrayHasKey('overwatch_version', $snap);
        self::assertArrayHasKey('athena_version', $snap);
        self::assertArrayHasKey('extension_version', $snap);
        self::assertArrayHasKey('pack_label', $snap);
    }
}
