<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakEnemyAiAssetTest extends TestCase
{
    public function testGameHidesEnemyAiUnlessChefAsks(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';

        $ally = (string) file_get_contents($root . '/functions/fn_reportAllyPosition.sqf');
        self::assertStringContainsString('side group _unit) isEqualTo east', $ally);
        self::assertStringContainsString('shouldSkipEnemyAiTransmit', $ally);

        $set = (string) file_get_contents($root . '/functions/fn_setAtakShowEnemyAi.sqf');
        self::assertStringContainsString('COMSPEC_AtakShowEnemyAi', $set);
        self::assertStringContainsString('Les IA ennemies apparaissent sur la carte du poste', $set);
        self::assertStringContainsString('Les contacts ennemis sont masqués', $set);

        $report = (string) file_get_contents($root . '/functions/fn_reportEnemyPosition.sqf');
        self::assertStringContainsString('COMSPEC_AtakShowEnemyAi', $report);
        self::assertStringContainsString('"enemy_ai"":true', $report);
        self::assertStringContainsString('affiliation"":""hostile"', $report);
        self::assertStringContainsString('ENY-', $report);

        $scan = (string) file_get_contents($root . '/functions/fn_reportEnemyAiPositions.sqf');
        self::assertStringContainsString('allGroups', $scan);
        self::assertStringContainsString('east', $scan);
        self::assertStringContainsString('isPlayer _leader', $scan);
        self::assertStringContainsString('_sent >= 24', $scan);
        self::assertStringContainsString('COMSPEC_AtakShowEnemyAi', $scan);

        $air = (string) file_get_contents($root . '/functions/fn_reportCrewedAirAssets.sqf');
        self::assertStringContainsString('shouldSkipEnemyAiTransmit', $air);
        self::assertStringContainsString('SendFlightManifest', $air);

        $skip = (string) file_get_contents($root . '/functions/fn_shouldSkipEnemyAiTransmit.sqf');
        self::assertStringContainsString('COMSPEC_AtakShowEnemyAi', $skip);
        self::assertStringContainsString('isPlayer _obj', $skip);
        self::assertStringContainsString('east', $skip);

        $phone = (string) file_get_contents($root . '/functions/fn_reportPhonePosition.sqf');
        self::assertStringContainsString('shouldSkipEnemyAiTransmit', $phone);

        $gps = (string) file_get_contents($root . '/functions/fn_reportGpsBeacon.sqf');
        self::assertStringContainsString('shouldSkipEnemyAiTransmit', $gps);

        $veh = (string) file_get_contents($root . '/functions/fn_updateVehicleTracking.sqf');
        self::assertStringContainsString('shouldSkipEnemyAiTransmit', $veh);

        $beaconInit = (string) file_get_contents($root . '/functions/fn_initGpsBeacons.sqf');
        self::assertStringContainsString('COMSPEC_AtakShowEnemyAi', $beaconInit);
        self::assertStringContainsString('reportEnemyAiPositions', $beaconInit);

        $pos = (string) file_get_contents($root . '/functions/fn_updatePosition.sqf');
        self::assertStringContainsString('show_enemy_ai', $pos);
    }

    public function testZeusAndEdenCanShowEnemyContacts(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';

        $zen = (string) file_get_contents($root . '/functions/fn_registerZenTrackActions.sqf');
        self::assertStringContainsString('Afficher les IA ennemies sur la carte', $zen);
        self::assertStringContainsString('Masquer les contacts ennemis', $zen);
        self::assertStringContainsString('COMSPEC_ZeusToggleEnemyAi', $zen);

        $mod = (string) file_get_contents($root . '/modules/module_atak_show_enemy_ai.hpp');
        self::assertStringContainsString('Contacts ennemis sur l’ATAK', $mod);
        self::assertStringContainsString('Afficher les IA ennemies sur la carte', $mod);
        self::assertStringContainsString('Masqués (défaut)', $mod);
        self::assertStringNotContainsString('sqf', strtolower($mod));
        self::assertStringNotContainsString('json', strtolower($mod));
        self::assertStringNotContainsString('endpoint', strtolower($mod));

        $edenFn = (string) file_get_contents($root . '/functions/fn_moduleAtakShowEnemyAi.sqf');
        self::assertStringContainsString('ShowAtStart', $edenFn);
        self::assertStringContainsString('setAtakShowEnemyAi', $edenFn);

        $cfg = (string) file_get_contents($root . '/config.cpp');
        self::assertStringContainsString('class setAtakShowEnemyAi {};', $cfg);
        self::assertStringContainsString('class shouldSkipEnemyAiTransmit {};', $cfg);
        self::assertStringContainsString('class reportEnemyPosition {};', $cfg);
        self::assertStringContainsString('class reportEnemyAiPositions {};', $cfg);
        self::assertStringContainsString('class moduleAtakShowEnemyAi {};', $cfg);
        self::assertStringContainsString('COMSPEC_Module_AtakShowEnemyAi', $cfg);
        self::assertStringContainsString('module_atak_show_enemy_ai.hpp', $cfg);
    }

    public function testWebMapHidesEnemyAiByDefault(): void
    {
        $jsRoot = dirname(__DIR__, 2) . '/public/assets/js';
        $units = (string) file_get_contents($jsRoot . '/atak-units.js');
        self::assertStringContainsString('function isEnemyAi', $units);
        self::assertStringContainsString('function shouldHideEnemyAi', $units);
        self::assertStringContainsString('show_enemy_ai', $units);
        self::assertStringContainsString('shouldHideEnemyAi(u, units)', $units);

        $map = (string) file_get_contents($jsRoot . '/atak-map.js');
        self::assertStringContainsString('shouldHideEnemyAi', $map);

        $cop = (string) file_get_contents($jsRoot . '/atak-cop.js');
        self::assertStringContainsString('shouldHideEnemyAi', $cop);

        $act = (string) file_get_contents($jsRoot . '/atak-activity.js');
        self::assertStringContainsString('shouldHideEnemyAiActivity', $act);
        self::assertStringContainsString('ENY-', $act);

        $api = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString('shouldHideEnemyAiContact', $api);
        self::assertGreaterThan(1, substr_count($api, 'shouldHideEnemyAiContact'));
    }

    public function testCatalogUpdateMentionsEnemyContacts(): void
    {
        $php = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/DevDispatchCatalog.php');
        self::assertStringContainsString('$pr(217', $php);
        self::assertStringContainsString('Contacts ennemis masqués par défaut', $php);
        self::assertStringContainsString('les contacts ennemis ne sont pas suivis', $php);
        self::assertStringContainsString('la liaison n’est pas saturée', $php);
    }
}
