<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PackCodenameCatalog;
use PHPUnit\Framework\TestCase;

final class PackCodenameCatalogTest extends TestCase
{
    public function testOlderVersionsStayPhoenix(): void
    {
        self::assertSame('Phoenix', PackCodenameCatalog::nameForVersion('1.6.15'));
        self::assertSame('Phoenix', PackCodenameCatalog::nameForVersion('1.4.0'));
    }

    public function testVersionOneSevenIsDenverCityName(): void
    {
        $meta = PackCodenameCatalog::forVersion('1.7.0');
        self::assertSame('1.7', $meta['line']);
        self::assertSame('Denver', $meta['name']);
        self::assertSame('Denver', $meta['label']);
        self::assertSame('Colorado', $meta['state']);
        self::assertSame('Denver', PackCodenameCatalog::nameForVersion('1.7.3'));
    }

    public function testUnassignedVersionHasNoName(): void
    {
        self::assertSame('', PackCodenameCatalog::nameForVersion('1.9.0'));
    }

    public function testNextNameGoesToNextMinor(): void
    {
        $next = PackCodenameCatalog::nextReserved();
        self::assertNotNull($next);
        self::assertSame('1.8', $next['line']);
        self::assertSame('Austin', $next['name']);
    }

    public function testNamesAreNeverReused(): void
    {
        $names = array_column(PackCodenameCatalog::assigned(), 'name');
        self::assertSame($names, array_unique($names));
        self::assertNotContains(PackCodenameCatalog::nextReserved()['name'] ?? '', $names);
    }
}
