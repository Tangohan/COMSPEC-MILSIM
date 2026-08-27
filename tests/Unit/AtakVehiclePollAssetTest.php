<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakVehiclePollAssetTest extends TestCase
{
    public function testVehiclePollDoesNotCountAsWholePostOutage(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-socket.js');
        $controller = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakVehicleTrackingRepository.php');
        $schema = (string) file_get_contents($root . '/app/Support/AtakModulesSchema.php');

        self::assertStringContainsString('function countsTowardUnavailable(url, method)', $js);
        self::assertStringContainsString('return isCoreRosterUrl(url);', $js);
        self::assertStringNotContainsString('/api/atak/vehicles', $js);
        self::assertStringContainsString('catch (\\Throwable) {', $controller);
        self::assertStringContainsString("\$vehicles = [];", $controller);
        self::assertStringContainsString('FROM atak_vehicle_tracking', $repo);
        self::assertStringNotContainsString('FROM v_atak_active_vehicles', $repo);
        self::assertStringContainsString('atak_vehicle_tracking', $schema);
        self::assertStringContainsString('v_atak_active_vehicles', $schema);
        self::assertStringContainsString('CREATE OR REPLACE VIEW v_atak_active_vehicles', $schema);
        self::assertStringContainsString("!isset(\$have['atak_poi']) || !isset(\$have['atak_vehicle_tracking'])", $schema);
    }
}
