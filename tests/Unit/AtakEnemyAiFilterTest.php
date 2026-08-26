<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakDataRepository;
use PHPUnit\Framework\TestCase;

final class AtakEnemyAiFilterTest extends TestCase
{
    public function testHostileAiIsHiddenByDefault(): void
    {
        $extra = [
            'enemy_ai' => true,
            'is_ai' => true,
            'source' => 'enemy',
            'affiliation' => 'hostile',
            'side' => 'EAST',
        ];
        self::assertTrue(AtakDataRepository::isEnemyAiContact($extra, 'ENY-0-12'));
        self::assertTrue(AtakDataRepository::shouldHideEnemyAiContact($extra, 'ENY-0-12'));
    }

    public function testAllyTrackedEastIsTreatedAsEnemyAi(): void
    {
        $extra = [
            'ally_ai' => true,
            'is_ai' => true,
            'source' => 'ally',
            'affiliation' => 'hostile',
            'side' => 'EAST',
        ];
        self::assertTrue(AtakDataRepository::isEnemyAiContact($extra, 'ALLY-0-99'));
        self::assertTrue(AtakDataRepository::shouldHideEnemyAiContact($extra, 'ALLY-0-99'));
    }

    public function testShownWhenChefDeMissionAsks(): void
    {
        $extra = [
            'enemy_ai' => true,
            'affiliation' => 'hostile',
            'side' => 'EAST',
            'show_enemy_ai' => true,
        ];
        self::assertFalse(AtakDataRepository::shouldHideEnemyAiContact($extra, 'ENY-0-12'));
    }

    public function testFriendlyAllyAndOpforPlayerStayVisible(): void
    {
        self::assertFalse(AtakDataRepository::isEnemyAiContact([
            'ally_ai' => true,
            'affiliation' => 'friend',
            'side' => 'WEST',
        ], 'RAVEN'));
        self::assertFalse(AtakDataRepository::isEnemyAiContact([
            'affiliation' => 'hostile',
            'side' => 'EAST',
            'health' => 'ok',
        ], 'N-10'));
        self::assertFalse(AtakDataRepository::shouldHideEnemyAiContact([
            'affiliation' => 'hostile',
            'side' => 'EAST',
        ], 'Alpha 1-2'));
    }

    public function testPhoneAndGpsAreNotEnemyAi(): void
    {
        self::assertFalse(AtakDataRepository::isAiContactExtra([
            'phone_geoloc' => true,
            'affiliation' => 'hostile',
        ], 'Tél. Garde'));
        self::assertFalse(AtakDataRepository::isEnemyAiContact([
            'gps_beacon' => true,
            'affiliation' => 'hostile',
            'side' => 'EAST',
        ], 'GPS-0-1'));
    }

    public function testEnemyAiIsAProxyContact(): void
    {
        self::assertTrue(AtakDataRepository::isProxyContactExtra(['enemy_ai' => true]));
        self::assertTrue(AtakDataRepository::isProxyContactExtra(['source' => 'enemy']));
        self::assertTrue(AtakDataRepository::callSignLooksLikeProxy('ENY-0-12'));
        self::assertTrue(AtakDataRepository::extraLooksLikeProxy('{"enemy_ai":true}', []));
    }
}
