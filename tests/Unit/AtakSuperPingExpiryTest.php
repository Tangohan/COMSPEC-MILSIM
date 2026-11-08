<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakSuperPing;
use PHPUnit\Framework\TestCase;

final class AtakSuperPingExpiryTest extends TestCase
{
    public function testSeuleEtiquetteSuperPingCompte(): void
    {
        self::assertTrue(AtakSuperPing::isSuper('[Super ping]'));
        self::assertTrue(AtakSuperPing::isSuper('  [SUPER PING] Ici'));
        self::assertFalse(AtakSuperPing::isSuper('[Info] super position'));
        self::assertFalse(AtakSuperPing::isSuper('Super'));
        self::assertFalse(AtakSuperPing::isSuper(null));
    }

    public function testSuperPingRetireAuTermeSeulement(): void
    {
        $split = AtakSuperPing::partition([
            ['id' => 1, 'message' => '[Super ping]', 'age_s' => 16],
            ['id' => 2, 'message' => '[Super ping]', 'age_s' => 5],
            ['id' => 3, 'message' => '[Contact] nord', 'age_s' => 9000],
        ]);

        self::assertSame([1], $split['expired']);
        self::assertSame([2, 3], array_column($split['rows'], 'id'));
        self::assertArrayNotHasKey('age_s', $split['rows'][0]);
    }
}
