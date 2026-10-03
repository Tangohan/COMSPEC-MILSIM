<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GamePhoneIdentityRepository;
use PHPUnit\Framework\TestCase;

final class GamePhoneIdentityFormatTest extends TestCase
{
    public function testFrenchNumberIsMobile(): void
    {
        for ($i = 0; $i < 50; $i++) {
            self::assertMatchesRegularExpression('/^0[67]( \d{2}){4}$/', GamePhoneIdentityRepository::newNumber('FR'));
        }
    }

    public function testUsNumberFollowsNanp(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $n = GamePhoneIdentityRepository::newNumber('US');
            self::assertMatchesRegularExpression('/^\([2-9]\d{2}\) [2-9]\d{2}-\d{4}$/', $n);
            self::assertStringStartsNotWith('(555)', $n);
        }
    }

    public function testImeiHasValidLuhnKey(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $digits = str_replace('-', '', GamePhoneIdentityRepository::newImei());
            self::assertSame(15, strlen($digits));
            $sum = 0;
            foreach (str_split($digits) as $k => $c) {
                $v = (int) $c;
                if ($k % 2 === 1) {
                    $v *= 2;
                    if ($v > 9) {
                        $v -= 9;
                    }
                }
                $sum += $v;
            }
            self::assertSame(0, $sum % 10);
        }
    }

    public function testMacIsUnicast(): void
    {
        $mac = GamePhoneIdentityRepository::newMac();
        self::assertMatchesRegularExpression('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac);
        self::assertSame(0, hexdec(substr($mac, 0, 2)) % 2);
    }
}
