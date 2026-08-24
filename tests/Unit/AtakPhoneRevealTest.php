<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakOperationalStatusService;
use PHPUnit\Framework\TestCase;

final class AtakPhoneRevealTest extends TestCase
{
    public function testPhoneWithoutRevealHidesTelemetryAndAnalysis(): void
    {
        $row = AtakOperationalStatusService::decorate([
            'call_sign' => 'Tél. Dimitris Roumpesi',
            'heading' => 7,
            'pos_z' => 111,
            'status' => 'linked',
            'role' => 'Téléphone',
            'extra' => [
                'phone_geoloc' => true,
                'source' => 'phone',
                'affiliation' => 'friend',
                'in_vehicle' => true,
                'health' => 'stable',
            ],
        ], [
            'heading_object' => 7,
            'speed' => 1.2,
            'motion' => [
                'status' => 'moving',
                'confidence' => 0.28,
                'trend' => 'stable',
                'category' => 'INFANTRY',
            ],
            'air' => ['altitude' => 111],
        ]);

        self::assertNull($row['heading']);
        self::assertNull($row['source_arma']['heading_deg']);
        self::assertNull($row['source_arma']['altitude_m']);
        self::assertNull($row['source_arma']['speed_ms']);
        self::assertNull($row['source_arma']['in_vehicle']);
        self::assertNull($row['source_arma']['health']);
        self::assertNull($row['analysis_athena']['motion_status']);
        self::assertNull($row['analysis_athena']['confidence']);
        self::assertNull($row['operational']['unit']['affiliation']);
        self::assertFalse($row['operational']['combat']['contact']);
        self::assertSame('Téléphone', $row['operational']['unit']['role']);
    }

    public function testPhoneRevealShowsOnlySelectedFields(): void
    {
        $row = AtakOperationalStatusService::decorate([
            'call_sign' => 'Tél. Dimitris Roumpesi',
            'heading' => 7,
            'pos_z' => 111,
            'status' => 'linked',
            'extra' => [
                'phone_geoloc' => true,
                'source' => 'phone',
                'affiliation' => 'hostile',
                'in_vehicle' => true,
                'reveal' => [
                    'heading' => true,
                    'altitude' => true,
                    'affiliation' => true,
                    'vehicle' => true,
                ],
            ],
        ], [
            'heading_object' => 7,
            'air' => ['altitude' => 111],
            'motion' => ['status' => 'moving', 'confidence' => 0.5],
        ]);

        self::assertSame(7.0, $row['source_arma']['heading_deg']);
        self::assertSame(111.0, $row['source_arma']['altitude_m']);
        self::assertTrue($row['source_arma']['in_vehicle']);
        self::assertSame('hostile', $row['operational']['unit']['affiliation']);
        self::assertNull($row['analysis_athena']['motion_status']);
    }

    public function testRegularUnitKeepsAnalysis(): void
    {
        $row = AtakOperationalStatusService::decorate([
            'call_sign' => 'Alpha 1-1',
            'heading' => 90,
            'extra' => ['health' => 'ok'],
        ], [
            'heading_object' => 90,
            'motion' => ['status' => 'stationary', 'confidence' => 0.8, 'trend' => 'stable'],
        ]);

        self::assertSame('stationary', $row['analysis_athena']['motion_status']);
        self::assertSame(90.0, $row['source_arma']['heading_deg']);
    }
}
