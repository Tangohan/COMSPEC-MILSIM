<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakVehicleTrackingRepository;
use App\Services\Tactical\AtakDataFlowsService;
use PHPUnit\Framework\TestCase;

final class AtakDataFlowsTest extends TestCase
{
    public function testPresetsAreRecognised(): void
    {
        self::assertSame('full', AtakDataFlowsService::presetOf(AtakDataFlowsService::PRESETS['full']));
        self::assertSame('reports', AtakDataFlowsService::presetOf(AtakDataFlowsService::PRESETS['reports']));
        self::assertSame('custom', AtakDataFlowsService::presetOf(['positions' => false] + AtakDataFlowsService::PRESETS['full']));
        // Flux absent : envoyé (rien n'est coupé par défaut).
        self::assertSame('full', AtakDataFlowsService::presetOf([]));
    }

    public function testBatchTypesMapToFlows(): void
    {
        self::assertSame('vehicles', AtakDataFlowsService::flowForBatchType('veh'));
        self::assertSame('vehicles', AtakDataFlowsService::flowForBatchType('flight'));
        self::assertSame('combat', AtakDataFlowsService::flowForBatchType('combat'));
        self::assertSame('context', AtakDataFlowsService::flowForBatchType('sigint'));
        // Positions (présence) et comptes rendus : jamais coupés côté lot.
        self::assertNull(AtakDataFlowsService::flowForBatchType('pos'));
        self::assertNull(AtakDataFlowsService::flowForBatchType('salute'));
        self::assertNull(AtakDataFlowsService::flowForBatchType('bda'));
    }

    /** vehicle_exception (HTTP 500) du lot : valeurs du mod hors du type des colonnes en SQL strict. */
    public function testVehicleColumnsAreCoercedForStrictSql(): void
    {
        $d = AtakVehicleTrackingRepository::sanitizeColumns([
            'vehicle_callsign' => 'HUNTER-1',
            'vehicle_name' => str_repeat('x', 300),
            'crew_commander_user_id' => '76561198000000000',
            'crew_count' => -2,
            'crew_max' => 'n/a',
            'pos_x' => '1850,5',
            'pos_y' => 'nan',
            'heading' => -90,
            'speed' => 'INF',
            'fuel_percent' => 140,
            'is_visible' => false,
            'unit_assigned' => ['Alpha'],
        ]);
        self::assertSame(200, mb_strlen((string) $d['vehicle_name']));
        self::assertNull($d['crew_commander_user_id']);
        self::assertArrayNotHasKey('crew_count', $d);
        self::assertNull($d['crew_max']);
        self::assertSame(1850.5, $d['pos_x']);
        self::assertArrayNotHasKey('pos_y', $d);
        self::assertSame(270.0, $d['heading']);
        self::assertNull($d['speed']);
        self::assertSame(100.0, $d['fuel_percent']);
        self::assertSame(0, $d['is_visible']);
        self::assertArrayNotHasKey('unit_assigned', $d);
    }
}
