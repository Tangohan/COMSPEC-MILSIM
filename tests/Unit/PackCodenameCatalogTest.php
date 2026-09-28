<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PackCodenameCatalog;
use PHPUnit\Framework\TestCase;

final class PackCodenameCatalogTest extends TestCase
{
    public function testMajorOneIsPhoenix(): void
    {
        $meta = PackCodenameCatalog::forVersion('1.6.14');
        self::assertSame(1, $meta['major']);
        self::assertSame('Phoenix', $meta['name']);
        self::assertSame('Opération Phoenix', $meta['label']);
    }

    public function testNextMajorIsDenver(): void
    {
        $next = PackCodenameCatalog::nextReserved(1);
        self::assertNotNull($next);
        self::assertSame(2, $next['major']);
        self::assertSame('Denver', $next['name']);
    }
}
