<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakVehicleTrackingRepository;
use PHPUnit\Framework\TestCase;

final class AtakVehicleCallsignTest extends TestCase
{
    public function testCallsignFallsBackAndTrims(): void
    {
        self::assertSame('HUNTER-1', AtakVehicleTrackingRepository::resolveCallsign(['vehicle_callsign' => ' HUNTER-1 ']));
        self::assertSame('Alpha', AtakVehicleTrackingRepository::resolveCallsign(['callsign' => 'Alpha']));
        self::assertSame('Hunter HMG', AtakVehicleTrackingRepository::resolveCallsign(['vehicle_callsign' => '', 'vehicle_name' => 'Hunter HMG']));
        self::assertSame('UAV-2-17', AtakVehicleTrackingRepository::resolveCallsign(['vehicle_id' => 'UAV-2-17']));
        self::assertSame('B_MRAP_01_F', AtakVehicleTrackingRepository::resolveCallsign(['vehicle_type' => 'B_MRAP_01_F']));
    }

    public function testArmaJsonWithControlCharsAndNanIsRepaired(): void
    {
        $raw = "{\"vehicle_callsign\":\"Hunter\nHMG\",\"speed\":-nan(ind),\"heading\":1.#INF,\"pos_x\":12,5}";
        self::assertNull(json_decode($raw, true));
        $decoded = json_decode(\App\Controllers\Api\AtakApiController::sanitizeArmaJson($raw), true);
        self::assertIsArray($decoded);
        self::assertSame('Hunter HMG', $decoded['vehicle_callsign']);
        self::assertSame(0, $decoded['speed']);
        self::assertSame(0, $decoded['heading']);
        self::assertSame(12.5, $decoded['pos_x']);
    }

    public function testMissingCallsignIsEmpty(): void
    {
        self::assertSame('', AtakVehicleTrackingRepository::resolveCallsign([]));
        self::assertSame('', AtakVehicleTrackingRepository::resolveCallsign(['vehicle_callsign' => ['x'], 'tenant_id' => 1]));
    }

    public function testUpsertEndpointRejectsMissingCallsignWith400(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("'error' => 'vehicle_callsign requis',", $controller);
        self::assertStringContainsString("'error' => 'invalid_json',", $controller);
    }
}
