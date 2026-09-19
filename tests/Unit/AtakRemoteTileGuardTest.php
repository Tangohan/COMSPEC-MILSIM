<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakRemoteTileGuard;
use PHPUnit\Framework\TestCase;

final class AtakRemoteTileGuardTest extends TestCase
{
    public function testAcceptsAtlasAltisTile(): void
    {
        $url = 'https://atlas.plan-ops.fr/data/1/maps/3/295/3/4/1.webp';
        self::assertSame($url, AtakRemoteTileGuard::normalize($url));
    }

    public function testRejectsOpenProxyAndLocalTargets(): void
    {
        self::assertNull(AtakRemoteTileGuard::normalize('https://evil.example/data/1/maps/3/295/3/4/1.webp'));
        self::assertNull(AtakRemoteTileGuard::normalize('http://atlas.plan-ops.fr/data/1/maps/3/295/3/4/1.webp'));
        self::assertNull(AtakRemoteTileGuard::normalize('https://atlas.plan-ops.fr/data/1/maps/3/295/3/4/1.webp?x=1'));
        self::assertNull(AtakRemoteTileGuard::normalize('https://atlas.plan-ops.fr/../etc/passwd'));
        self::assertNull(AtakRemoteTileGuard::normalize('https://127.0.0.1/data/1/maps/3/295/3/4/1.webp'));
    }
}
