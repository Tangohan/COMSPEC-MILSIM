<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakCombatEventPresenter;
use App\Services\Tactical\AtakOperationalStatusService;
use PHPUnit\Framework\TestCase;

final class AtakCombatEventPresenterTest extends TestCase
{
    public function testFireBurstAndSingleShotLabels(): void
    {
        $one = AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'fire', 'n' => 1, 'x' => 100, 'y' => 200]],
        ]);
        self::assertCount(1, $one);
        self::assertSame('UNIT_FIRING', $one[0]['type']);
        self::assertSame('N-10 ouvre le feu', $one[0]['message']);
        self::assertSame('warn', $one[0]['severity']);

        $burst = AtakCombatEventPresenter::fromExtra('Alpha 1-2', [
            'combat_events' => [['t' => 'fire', 'n' => 8]],
        ]);
        self::assertSame('Alpha 1-2 ouvre le feu (rafale)', $burst[0]['message']);
    }

    public function testExchangeTakesPriorityOverIsolatedFire(): void
    {
        $rows = AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'fire', 'n' => 5, 'exch' => true]],
        ]);
        self::assertSame('UNIT_FIRE_EXCHANGE', $rows[0]['type']);
        self::assertSame('N-10 en échange de feu', $rows[0]['message']);
        self::assertSame('alert', $rows[0]['severity']);
    }

    public function testHitAndMissileOutcomes(): void
    {
        $hit = AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'hit', 'n' => 1]],
        ]);
        self::assertSame('Impact sur N-10', $hit[0]['message']);

        $attempt = AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'missile', 'out' => 'attempt']],
        ]);
        self::assertSame('Tentative de missile vers N-10', $attempt[0]['message']);

        $lock = AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'missile', 'out' => 'lock']],
        ]);
        self::assertSame('Verrouillage missile sur N-10', $lock[0]['message']);

        $miss = AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'missile', 'out' => 'miss']],
        ]);
        self::assertSame('Missile manqué près de N-10', $miss[0]['message']);

        $shot = AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'missile', 'out' => 'shot']],
        ]);
        self::assertSame('N-10 tire un missile', $shot[0]['message']);
    }

    public function testSkipsHostileProxyAndPhone(): void
    {
        self::assertSame([], AtakCombatEventPresenter::fromExtra('OPFOR-1', [
            'affiliation' => 'hostile',
            'ally_ai' => true,
            'combat_events' => [['t' => 'fire', 'n' => 3]],
        ]));
        self::assertSame([], AtakCombatEventPresenter::fromExtra('Tél. Garde', [
            'phone_geoloc' => true,
            'combat_events' => [['t' => 'fire', 'n' => 3]],
        ]));
        self::assertSame([], AtakCombatEventPresenter::fromExtra('N-10', [
            'combat_events' => [['t' => 'fire', 'n' => 2, 'aff' => 'hostile']],
        ]));
        $shown = AtakCombatEventPresenter::fromExtra('ENY-0-12', [
            'enemy_ai' => true,
            'affiliation' => 'hostile',
            'side' => 'EAST',
            'show_enemy_ai' => true,
            'combat_events' => [['t' => 'fire', 'n' => 3]],
        ]);
        self::assertCount(1, $shown);
        self::assertSame('ENY-0-12 ouvre le feu', $shown[0]['message']);
    }

    public function testKeepsFriendlyPlayerEvenIfSideMapsHostile(): void
    {
        $rows = AtakCombatEventPresenter::fromExtra('N-10', [
            'affiliation' => 'hostile',
            'steam_uid' => '76561198000000000',
            'combat_events' => [['t' => 'fire', 'n' => 2, 'x' => 10, 'y' => 20]],
        ]);
        self::assertCount(1, $rows);
        self::assertSame('N-10 ouvre le feu', $rows[0]['message']);
        self::assertSame(10.0, $rows[0]['payload']['x']);
        self::assertSame(20.0, $rows[0]['payload']['y']);
    }

    public function testCombatContactFlagsOperationalStatus(): void
    {
        $row = AtakOperationalStatusService::decorate([
            'call_sign' => 'N-10',
            'extra' => [
                'combat_contact' => true,
                'combat_events' => [['t' => 'fire', 'n' => 4]],
            ],
        ], ['motion' => ['status' => 'stationary']]);

        self::assertTrue($row['operational']['combat']['contact']);
    }
}
