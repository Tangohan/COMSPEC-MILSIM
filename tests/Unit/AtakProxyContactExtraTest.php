<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDataRepository;
use PHPUnit\Framework\TestCase;

final class AtakProxyContactExtraTest extends TestCase
{
    public function testDecodeExtraAcceptsJsonString(): void
    {
        $extra = AtakDataRepository::decodeExtra('{"ally_ai":true,"source":"ally"}');
        self::assertTrue($extra['ally_ai']);
        self::assertSame('ally', $extra['source']);
    }

    public function testDecodeExtraFixesFrenchDecimalComma(): void
    {
        $extra = AtakDataRepository::decodeExtra('{"speed":1,5,"ally_ai":true}');
        self::assertSame(1.5, $extra['speed']);
        self::assertTrue($extra['ally_ai']);
    }

    public function testAllyAndGpsAndPhoneAreProxyContacts(): void
    {
        self::assertTrue(AtakDataRepository::isProxyContactExtra(['ally_ai' => true]));
        self::assertTrue(AtakDataRepository::isProxyContactExtra(['phone_geoloc' => true]));
        self::assertTrue(AtakDataRepository::isProxyContactExtra(['gps_beacon' => true]));
        self::assertTrue(AtakDataRepository::isProxyContactExtra(['source' => 'ally']));
        self::assertTrue(AtakDataRepository::isProxyContactExtra(['enemy_ai' => true]));
        self::assertFalse(AtakDataRepository::isProxyContactExtra(['health' => 'ok', 'role' => 'Breacher']));
    }

    public function testBrokenJsonStringStillLooksLikeProxy(): void
    {
        $raw = '{""ally_ai"":true,""source"":""ally""}';
        self::assertTrue(AtakDataRepository::extraLooksLikeProxy($raw, []));
    }

    public function testProxyCallsignsAreNeverMergedWithPlayer(): void
    {
        self::assertTrue(AtakDataRepository::callSignLooksLikeProxy('ALLY-1-2345 · Alpha 1-2 — James Brown'));
        self::assertTrue(AtakDataRepository::callSignLooksLikeProxy('GPS-0-12'));
        self::assertTrue(AtakDataRepository::callSignLooksLikeProxy('Tél. Garde'));
        self::assertFalse(AtakDataRepository::callSignLooksLikeProxy('Alpha 1-2 — James Brown'));
        self::assertFalse(AtakDataRepository::callSignLooksLikeProxy('N-10'));
    }

    public function testAutoAllyIdIsDetected(): void
    {
        self::assertTrue(AtakDataRepository::looksLikeAutoAllyId('ALLY-0-1780311'));
        self::assertFalse(AtakDataRepository::looksLikeAutoAllyId('RAVEN'));
        self::assertFalse(AtakDataRepository::looksLikeAutoAllyId('ALLY-6'));
    }

    public function testDisplayCallSignHidesAutoAllyId(): void
    {
        self::assertSame(
            'Alpha 1-2 — James Brown',
            AtakDataRepository::displayCallSign('ALLY-0-1780311 · Alpha 1-2 — James Brown', [
                'ally_ai' => true,
                'ally_id' => 'ALLY-0-1780311',
            ])
        );
        self::assertSame(
            'RAVEN',
            AtakDataRepository::displayCallSign('RAVEN', [
                'ally_ai' => true,
                'ally_id' => 'ALLY-0-12',
                'display_name' => 'RAVEN',
            ])
        );
        self::assertSame(
            'Alpha 1-2',
            AtakDataRepository::displayCallSign('ALLY-0-1780311', [
                'ally_ai' => true,
                'ally_id' => 'ALLY-0-1780311',
                'group_name' => 'Alpha 1-2',
            ])
        );
        self::assertSame('N-10', AtakDataRepository::displayCallSign('N-10', []));
    }
}
