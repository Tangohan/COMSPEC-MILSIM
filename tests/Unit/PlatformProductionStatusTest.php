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
        self::assertArrayHasKey('codename', $snap);
        self::assertArrayHasKey('codename_label', $snap);
        self::assertSame('1.7.0', $snap['platform_version']);
        self::assertSame('Denver', $snap['codename']);
        self::assertSame('Denver', $snap['codename_label']);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $snap['atak_version']);
        self::assertMatchesRegularExpression('/^2\.0\.\d+$/', $snap['link_version']);
    }
}
