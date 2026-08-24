<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Replay\ReplayTimelineBuilder;
use PHPUnit\Framework\TestCase;

final class ReplayTimelineBuilderTest extends TestCase
{
    public function testInfersPhoneAllyAndGpsFromFlagsAndCallsign(): void
    {
        self::assertSame('phone', ReplayTimelineBuilder::inferKind('Alpha', null, ['phone_geoloc' => true]));
        self::assertSame('ally_ai', ReplayTimelineBuilder::inferKind('Bravo', null, ['ally_ai' => true]));
        self::assertSame('gps', ReplayTimelineBuilder::inferKind('Charlie', null, ['gps_beacon' => true]));
        self::assertSame('ally_ai', ReplayTimelineBuilder::inferKind('ALLY-12', null, []));
        self::assertSame('phone', ReplayTimelineBuilder::inferKind('TEL-88', null, []));
        self::assertSame('gps', ReplayTimelineBuilder::inferKind('GPS-1', null, []));
        self::assertSame('player', ReplayTimelineBuilder::inferKind('RAVEN-1', null, []));
    }

    public function testFramesKeepAllEntitiesOnTheSameTick(): void
    {
        $rows = [
            $this->row('RAVEN-1', '2026-08-24 12:00:00', 100, 200, null, ['health' => 'ok']),
            $this->row('ALLY-9', '2026-08-24 12:00:01', 110, 210, 'ally_ai', ['ally_ai' => true]),
            $this->row('TEL-3', '2026-08-24 12:00:03', 120, 220, null, ['phone_geoloc' => true]),
        ];
        $frames = ReplayTimelineBuilder::framesFromRows($rows, 2, 90);
        self::assertNotEmpty($frames);
        $last = $frames[count($frames) - 1];
        $ids = array_map(static fn (array $u): string => (string) $u['unitId'], $last['units']);
        sort($ids);
        self::assertSame(['ALLY-9', 'RAVEN-1', 'TEL-3'], $ids);

        $byId = [];
        foreach ($last['units'] as $u) {
            $byId[$u['unitId']] = $u;
        }
        self::assertSame('ally_ai', $byId['ALLY-9']['kind']);
        self::assertTrue(!empty($byId['ALLY-9']['extra']['ally_ai']));
        self::assertSame('phone', $byId['TEL-3']['kind']);
        self::assertTrue(!empty($byId['TEL-3']['extra']['phone_geoloc']));
        self::assertSame('player', $byId['RAVEN-1']['kind']);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function row(string $id, string $at, float $x, float $y, ?string $type, array $extra): array
    {
        return [
            'unit_id' => $id,
            'callsign' => $id,
            'unit_type' => $type,
            'pos_x' => $x,
            'pos_y' => $y,
            'pos_z' => 0,
            'heading' => 90,
            'state_json' => json_encode($extra),
            'logged_at' => $at,
        ];
    }
}
