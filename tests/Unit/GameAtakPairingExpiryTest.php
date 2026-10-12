<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Game\GameAtakPairingService;
use PHPUnit\Framework\TestCase;

final class GameAtakPairingExpiryTest extends TestCase
{
    public function testUtcExpiryIsNotShiftedByParisTimezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
        try {
            $now = time();
            $inTenMinutes = ['expires_at' => gmdate('Y-m-d H:i:s', $now + 600)];
            $tenMinutesAgo = ['expires_at' => gmdate('Y-m-d H:i:s', $now - 600)];

            self::assertFalse(GameAtakPairingService::isExpiredRow($inTenMinutes, $now));
            self::assertTrue(GameAtakPairingService::isExpiredRow($tenMinutesAgo, $now));
            self::assertFalse(GameAtakPairingService::isExpiredRow([], $now));
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
