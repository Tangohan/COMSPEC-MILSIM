<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakRfHitsLot1AssetTest extends TestCase
{
    public function testFieldwatchLot1IsWiredEndToEnd(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/AtakDataRepository.php');
        $mig = (string) file_get_contents($root . '/bootstrap/atak_rf_hits_lot1_migration.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');
        $ops = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-ops.js');
        $beta = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');
        $atak = (string) file_get_contents($root . '/views/atak.php');
        $js = (string) file_get_contents($root . '/public/assets/js/atak-rf-hits.js');
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        $scan = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_scanRfNearby.sqf');
        $zen = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenRoleplayModules.sqf');
        $athenaCfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/config.cpp');
        $cap = (string) file_get_contents($root . '/docs/registry/REG-ATHENA-CAPABILITIES.md');

        self::assertStringContainsString("'rfHitsStore'", $routes);
        self::assertStringContainsString("'rfHitsIndex'", $routes);
        self::assertStringContainsString('/api/atak/rf-hits', $routes);
        self::assertStringContainsString('function rfHitsStore', $ctrl);
        self::assertStringContainsString('function rfHitsIndex', $ctrl);
        self::assertStringContainsString('function addRfHit', $repo);
        self::assertStringContainsString('function getRfHitMarkers', $repo);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS atak_rf_hits', $mig);
        self::assertStringContainsString('atak_rf_hits_lot1', $run);
        self::assertStringContainsString("'atak_rf_hits'", $run);
        self::assertStringContainsString('renderRfHits', $ops);
        self::assertStringContainsString('/api/atak/rf-hits?mode=markers', $ops);
        self::assertStringContainsString('id="ow-rf-list"', $beta);
        self::assertStringContainsString('id="ow-rf-layer"', $beta);
        self::assertStringContainsString('id="atak-rf-list"', $atak);
        self::assertStringContainsString('atak-rf-hits.js', $atak);
        self::assertStringContainsString('ATAKRFHITS', $js);
        self::assertStringContainsString('SendRfHit', $dll);
        self::assertStringContainsString('/api/atak/rf-hits', $dll);
        self::assertStringContainsString('SendRfHit', $scan);
        self::assertStringContainsString('Émetteur RF Fieldwatch', $zen);
        self::assertStringContainsString('AtakFieldwatch', $athenaCfg);
        self::assertStringContainsString('COMSPEC_ATAK_Fieldwatch', $athenaCfg);
        self::assertStringContainsString('CAP-RF-001', $cap);
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/modules/module_rf_emitter.hpp');
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_placeRfEmitter.sqf');
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/ui/fieldwatch_page.hpp');
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_fieldwatchOnOpened.sqf');
        self::assertFileExists($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_updateFieldwatch.sqf');
    }
}
