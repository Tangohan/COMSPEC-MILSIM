<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakAirAssetMergeService;
use PHPUnit\Framework\TestCase;

final class AtakAirAssetMergeServiceTest extends TestCase
{
    public function testOccupancyPayloadDetection(): void
    {
        self::assertTrue(AtakAirAssetMergeService::isOccupancyPayload(['source' => 'occupancy']));
        self::assertTrue(AtakAirAssetMergeService::isOccupancyPayload(['inferred' => true]));
        self::assertFalse(AtakAirAssetMergeService::isOccupancyPayload(['callsign' => 'HAWK-1']));
    }

    public function testManifestAndOccupancySameAirframeMerge(): void
    {
        $merged = AtakAirAssetMergeService::merge([
            [
                'callsign' => 'HAWK-1',
                'model' => 'Chinook HC5',
                'aircraft_type' => 'helicopter',
                'freq' => '31.0',
                'laser' => '1688',
                'pos_x' => 1000.0,
                'pos_y' => 2000.0,
                'source' => 'manifest',
            ],
            [
                'callsign' => 'Alpha 1-4',
                'model' => 'Chinook HC5',
                'aircraft_type' => 'helicopter',
                'pos_x' => 1008.0,
                'pos_y' => 2004.0,
                'vehicle_id' => '2:14',
                'source' => 'occupancy',
            ],
        ], []);

        self::assertCount(1, $merged);
        self::assertSame('HAWK-1', $merged[0]['callsign']);
        self::assertSame('31.0', $merged[0]['freq']);
        self::assertSame(1008.0, $merged[0]['pos_x']);
        self::assertSame('2:14', $merged[0]['vehicle_id']);
    }

    public function testDistinctVehicleIdsStaySeparate(): void
    {
        $merged = AtakAirAssetMergeService::merge([
            [
                'callsign' => 'Alpha 1-4',
                'model' => 'Chinook HC5',
                'aircraft_type' => 'helicopter',
                'pos_x' => 1000.0,
                'pos_y' => 2000.0,
                'vehicle_id' => '2:14',
                'source' => 'occupancy',
            ],
            [
                'callsign' => 'Alpha 1-5',
                'model' => 'Chinook HC5',
                'aircraft_type' => 'helicopter',
                'pos_x' => 1020.0,
                'pos_y' => 2010.0,
                'vehicle_id' => '2:15',
                'source' => 'occupancy',
            ],
        ], []);

        self::assertCount(2, $merged);
    }

    public function testUnitsInSameAirframeBecomeOneCandidate(): void
    {
        $units = [];
        foreach (['P1', 'P2', 'P3', 'P4'] as $cs) {
            $units[] = [
                'call_sign' => $cs,
                'status' => 'linked',
                'pos_x' => 4400.2,
                'pos_y' => 5510.1,
                'extra' => json_encode([
                    'platform' => 'HELICOPTER',
                    'in_vehicle' => true,
                    'vehicle' => 'UK3CB_BAF_Chinook_HC5',
                    'vehicle_name' => 'Chinook HC5',
                    'group_name' => 'Alpha 1-4',
                    'side' => 'WEST',
                ], JSON_THROW_ON_ERROR),
            ];
        }

        $merged = AtakAirAssetMergeService::merge([], $units);
        self::assertCount(1, $merged);
        self::assertSame('Alpha 1-4', $merged[0]['callsign']);
        self::assertSame('Chinook HC5', $merged[0]['model']);
        self::assertSame(1, $merged[0]['aircraft_count']);
    }

    public function testUnitDoesNotDuplicateOccupancyReport(): void
    {
        $merged = AtakAirAssetMergeService::merge([
            [
                'callsign' => 'Alpha 1-4',
                'model' => 'Chinook HC5',
                'aircraft_type' => 'helicopter',
                'pos_x' => 4400.0,
                'pos_y' => 5510.0,
                'vehicle_id' => '2:14',
                'source' => 'occupancy',
            ],
        ], [
            [
                'call_sign' => 'N-10',
                'status' => 'linked',
                'pos_x' => 4402.0,
                'pos_y' => 5511.0,
                'extra' => json_encode([
                    'platform' => 'HELICOPTER',
                    'in_vehicle' => true,
                    'vehicle' => 'UK3CB_BAF_Chinook_HC5',
                    'group_name' => 'Alpha 1-4',
                ], JSON_THROW_ON_ERROR),
            ],
        ]);

        self::assertCount(1, $merged);
        self::assertSame('Alpha 1-4', $merged[0]['callsign']);
    }

    public function testGroundUnitsAreIgnored(): void
    {
        $merged = AtakAirAssetMergeService::merge([], [
            [
                'call_sign' => 'N-01',
                'status' => 'linked',
                'pos_x' => 100.0,
                'pos_y' => 200.0,
                'extra' => json_encode(['platform' => 'INFANTRY'], JSON_THROW_ON_ERROR),
            ],
        ]);
        self::assertSame([], $merged);
    }

    public function testOccupancyCrewSurvivesManifestMerge(): void
    {
        $crew = [
            ['name' => 'N-01', 'seat' => 'driver'],
            ['name' => 'N-02', 'seat' => 'gunner'],
            ['name' => 'N-03', 'seat' => 'cargo'],
        ];
        $merged = AtakAirAssetMergeService::merge([
            [
                'callsign' => 'HAWK-1',
                'model' => 'CH-146 Griffin',
                'aircraft_type' => 'helicopter',
                'freq' => '31.0',
                'pos_x' => 1000.0,
                'pos_y' => 2000.0,
                'source' => 'manifest',
            ],
            [
                'callsign' => 'Alpha 1-2',
                'model' => 'CH-146 Griffin',
                'aircraft_type' => 'helicopter',
                'pos_x' => 1008.0,
                'pos_y' => 2004.0,
                'vehicle_id' => '2:14',
                'source' => 'occupancy',
                'occupants' => $crew,
                'crew' => $crew,
                'crew_count' => 3,
                'pilot' => 'N-01',
            ],
        ], []);

        self::assertCount(1, $merged);
        self::assertSame('HAWK-1', $merged[0]['callsign']);
        self::assertCount(3, $merged[0]['occupants']);
        self::assertSame('N-01', $merged[0]['occupants'][0]['name']);
        self::assertSame(3, $merged[0]['crew_count']);
        self::assertSame('N-01', $merged[0]['pilot']);
    }
}
