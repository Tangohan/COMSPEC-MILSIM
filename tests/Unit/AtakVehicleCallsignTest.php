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
    }

    public function testMissingCallsignIsEmpty(): void
    {
        self::assertSame('', AtakVehicleTrackingRepository::resolveCallsign([]));
        self::assertSame('', AtakVehicleTrackingRepository::resolveCallsign(['vehicle_callsign' => ['x'], 'tenant_id' => 1]));
    }

    public function testUpsertEndpointRejectsMissingCallsignWith400(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("['error' => 'vehicle_callsign requis'], 400", $controller);
    }
}
