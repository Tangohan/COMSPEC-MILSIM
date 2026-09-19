<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakSceneIngestAssetTest extends TestCase
{
    public function testGameCollectsBuildingsAndForestsForAthena(): void
    {
        $sqf = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleScene.sqf');
        self::assertStringContainsString('nearestTerrainObjects', $sqf);
        self::assertStringContainsString('"HOUSE"', $sqf);
        self::assertStringContainsString('"TREE"', $sqf);
        self::assertStringContainsString('"WALL"', $sqf);
        self::assertStringContainsString('"FENCE"', $sqf);
        self::assertStringContainsString('buildingExit', $sqf);
        self::assertStringContainsString('""doors""', $sqf);
        self::assertStringContainsString('Scene.Ingest', $sqf);
        self::assertStringContainsString('params ["_s"]', $sqf);
        self::assertStringContainsString('visibleMap', $sqf);
        self::assertStringContainsString('curatorCamera', $sqf);
        self::assertStringContainsString('COMSPEC_SceneWorldDone', $sqf);
        self::assertStringContainsString('COMSPEC_SceneTiles_', $sqf);
        self::assertStringContainsString('COMSPEC_SceneSentIds', $sqf);
        self::assertStringContainsString('_sentIds getOrDefault', $sqf);
        self::assertStringContainsString('COMSPEC_TheaterSurveyCounts_', $sqf);
        self::assertStringContainsString('"WALL"', $sqf);
        self::assertStringContainsString('"FENCE"', $sqf);
        self::assertStringContainsString('buildingExit', $sqf);
        self::assertStringContainsString('"doors"', $sqf);
    }

    public function testSceneLoopDoesNotAutoUploadMapData(): void
    {
        $loops = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_startSyncLoops.sqf');
        self::assertStringNotContainsString('comspec_overwatch_connect_fnc_sampleScene', $loops);
        self::assertStringNotContainsString('uiSleep 45', $loops);
        self::assertStringNotContainsString('uiSleep 24', $loops);
        $theater = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTheater.sqf');
        self::assertStringContainsString('COMSPEC_SceneWorldDone_', $theater);
        self::assertStringContainsString('comspec_overwatch_connect_fnc_sampleGeoNetwork', $theater);
        self::assertStringContainsString('theaterSurveyVerify', $theater);
        $ace = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf');
        self::assertStringContainsString('Relevé de la carte', $ace);
        self::assertStringContainsString('theaterSurveyShow', $ace);
        self::assertStringNotContainsString('fnc_sampleTerrain', $ace);
        self::assertStringNotContainsString('fnc_sampleScene', $ace);
        self::assertStringNotContainsString('fnc_sampleGeoNetwork', $ace);
    }

    public function testExtensionQueuesSceneIngest(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('Scene.Ingest', $cs);
        self::assertStringContainsString('/api/atak/scene/ingest', $cs);
        self::assertStringContainsString('HandleSceneIngest', $cs);
        self::assertStringContainsString('HandleTheaterCoverage', $cs);
        self::assertStringContainsString('Theater.Coverage', $cs);
        self::assertStringContainsString('/api/atak/theater/coverage', $cs);
        self::assertStringContainsString('QueueHeavyIngest', $cs);
        $dialog = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/functions/fn_webJSDialog.sqf');
        self::assertStringContainsString('scene:json|', $dialog);
        self::assertStringContainsString('Scene.Ingest', $dialog);
        $tiles = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/web/map-tiles.js');
        $live = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/Overwatch 2026/ProdVersion/@COMSPEC_ATAK/addons/comspec_atak_core/web/live-map.js');
        self::assertStringContainsString('/map-data/', $tiles);
        self::assertStringContainsString('GridLayer', $tiles);
        self::assertStringNotContainsString('L.tileLayer', $tiles);
        self::assertStringNotContainsString('TILE_BASES', $live);
        self::assertStringNotContainsString('github.io', $live);
    }

    public function testTheaterSurveyVerifyComparesPostedCountsWithoutAutoResend(): void
    {
        $root = dirname(__DIR__, 2);
        $verify = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyVerify.sqf');
        self::assertStringContainsString('Theater.Coverage', $verify);
        self::assertStringContainsString('Vérification auprès du poste', $verify);
        self::assertStringContainsString('COMSPEC_TheaterResendMode', $verify);
        self::assertStringContainsString('_sceneGap', $verify);
        self::assertStringContainsString('_terrainGap', $verify);
        self::assertStringContainsString('_geoGap', $verify);
        self::assertStringContainsString('Renvoyer les données manquantes', $verify);
        self::assertStringNotContainsString('sampleTheater', $verify);
        self::assertStringNotContainsString('/api/', $verify);

        $resend = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_theaterSurveyResend.sqf');
        self::assertStringContainsString('sampleTheater', $resend);
        self::assertStringContainsString('sampleGeoNetwork', $resend);
        self::assertStringContainsString('[0, true]', $resend);

        $sample = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_sampleTheater.sqf');
        self::assertStringContainsString('_doScene', $sample);
        self::assertStringContainsString('_doTerrain', $sample);
        self::assertStringContainsString('COMSPEC_TheaterSurveyCounts_', $sample);

        $ctrl = (string) file_get_contents($root . '/app/Controllers/Api/AtakSceneApiController.php');
        self::assertStringContainsString("'places'", $ctrl);
        self::assertStringContainsString("'roads'", $ctrl);

        $cs = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('pl:{6};rd:{7}', $cs);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("/api/atak/theater/coverage", $routes);
        self::assertStringContainsString("'coverage'", $routes);

        $repo = (string) file_get_contents($root . '/app/Repositories/AtakSceneObjectRepository.php');
        self::assertStringContainsString('function countByKind', $repo);
        self::assertStringContainsString('WHERE map_id = ?', $repo);
        self::assertStringContainsString('COUNT(DISTINCT', $repo);
        self::assertStringContainsString("kind IN ('building', 'buildings')", $repo);
        $terrain = (string) file_get_contents($root . '/app/Repositories/AtakTerrainRepository.php');
        self::assertStringContainsString('function coverageSummary', $terrain);
    }

    public function testConnectRegistersSampleScene(): void
    {
        $cfg = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        self::assertStringContainsString('class sampleScene {};', $cfg);
        self::assertStringContainsString('class sampleTheater {};', $cfg);
        self::assertStringContainsString('class theaterSurveyVerify {};', $cfg);
        self::assertStringContainsString('class theaterSurveyResend {};', $cfg);
        self::assertStringContainsString('1.5.95', $cfg);
    }

    public function testTheaterSurveyModuleAndDialogExist(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect';
        $sqf = (string) file_get_contents($root . '/functions/fn_sampleTheater.sqf');
        self::assertStringContainsString('Scene.Ingest', $sqf);
        self::assertStringContainsString('Terrain.Chunk', $sqf);
        self::assertStringContainsString('params ["_s"]', $sqf);
        self::assertStringContainsString('COMSPEC_TheaterSampleToken', $sqf);
        self::assertStringContainsString('worldSize', $sqf);
        self::assertStringContainsString('sleep', $sqf);

        $dlg = (string) file_get_contents($root . '/display_theater_survey.hpp');
        self::assertStringContainsString('Relevé de la carte', $dlg);
        self::assertStringContainsString('DURÉE DU RELEVÉ', $dlg);
        self::assertStringContainsString('DONNÉES COLLECTÉES', $dlg);
        self::assertStringContainsString('SECTEUR EN COURS', $dlg);
        self::assertStringContainsString('DERNIER RELEVÉ', $dlg);
        self::assertStringContainsString('TRANSMISSION AU POSTE', $dlg);
        self::assertStringContainsString('Vérifier l’intégrité', $dlg);
        self::assertStringContainsString('Renvoyer les données manquantes', $dlg);
        self::assertStringContainsString('Villes 0', $dlg);
        self::assertStringContainsString('idd = 9994', $dlg);
        self::assertStringNotContainsString('sqf', strtolower($dlg));
        self::assertStringNotContainsString('json', strtolower($dlg));
        self::assertStringNotContainsString('endpoint', strtolower($dlg));

        $mod = (string) file_get_contents($root . '/modules/module_theater_survey.hpp');
        self::assertStringContainsString('Relever la carte du théâtre', $mod);
        self::assertStringContainsString('COMSPEC_Outils', $mod);

        $zen = (string) file_get_contents($root . '/functions/fn_registerZenTheaterSurvey.sqf');
        self::assertStringContainsString('COMSPEC Outils', $zen);
        self::assertStringContainsString('Relever la carte du théâtre', $zen);
        self::assertStringContainsString('ace_zeus_fnc_addModule', $zen);
        self::assertStringContainsString('COMSPEC_ZenTheaterSurveyRegistered', $zen);

        $show = (string) file_get_contents($root . '/functions/fn_theaterSurveyShow.sqf');
        self::assertStringContainsString('findDisplay 313', $show);
        self::assertStringContainsString('findDisplay 46', $show);
        self::assertStringNotContainsString('findDisplay 312', $show);

        $post = (string) file_get_contents($root . '/XEH_postInit.sqf');
        self::assertStringContainsString('COMSPEC_ZenRegisterDeadline', $post);
        self::assertStringContainsString('zen_custom_modules_fnc_register', $post);
        self::assertStringContainsString('CBA_fnc_waitUntilAndExecute', $post);

        $cfg = (string) file_get_contents($root . '/config.cpp');
        self::assertStringContainsString('COMSPEC_Module_TheaterSurvey', $cfg);
        self::assertStringContainsString('display_theater_survey.hpp', $cfg);
        self::assertStringContainsString('class COMSPEC_Outils', $cfg);
    }

    public function testBootHandlersRunOnceWhenCbaSettingsRetrigger(): void
    {
        $post = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/XEH_postInit.sqf');
        self::assertStringContainsString('COMSPEC_CbaSettingsBootDone', $post);
        self::assertStringContainsString('COMSPEC_CbaSettingsBootArmed', $post);
        $dump = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_logDump.sqf');
        self::assertStringContainsString('COMSPEC_LogDumpBootAt', $dump);
    }

    public function testSseAceGraftDoesNotCallIsNullOnClassName(): void
    {
        $sqf = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/sse_ace/functions/fn_initSseAce.sqf');
        self::assertStringNotContainsString("if (isNull _entity) exitWith {};", $sqf);
        self::assertStringContainsString('isEqualType ""', $sqf);
        self::assertStringContainsString('isEqualType objNull', $sqf);
        self::assertStringContainsString('addActionToClass', $sqf);
    }

    public function testVehicleOccupantsAreCollectedAndSentWithAirAssets(): void
    {
        $root = dirname(__DIR__, 2);
        $collect = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_collectVehicleOccupants.sqf');
        self::assertStringContainsString('crew _vehicle', $collect);
        self::assertStringContainsString('assignedVehicleRole', $collect);
        self::assertStringContainsString('se déplacer', $collect);

        $air = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_reportCrewedAirAssets.sqf');
        self::assertStringContainsString('collectVehicleOccupants', $air);
        self::assertStringContainsString('["occupants", _occ]', $air);
        self::assertStringContainsString('["crew", _occ]', $air);
        self::assertStringContainsString('7 max', $air);
        self::assertStringContainsString('2.5 max', $air);
        self::assertStringNotContainsString('>= 18', $air);

        $track = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updateVehicleTracking.sqf');
        self::assertStringContainsString('_vehGap = 2.5', $track);

        $gps = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initGpsBeacons.sqf');
        self::assertStringContainsString('}, 3, []] call CBA_fnc_addPerFrameHandler;', $gps);

        $cfg = (string) file_get_contents($root . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp');
        self::assertStringContainsString('class collectVehicleOccupants {};', $cfg);

        $api = (string) file_get_contents($root . '/app/Controllers/Api/AtakApiController.php');
        self::assertStringContainsString("'occupants' => \$crew", $api);
        self::assertStringContainsString("'crew' => \$crew", $api);
    }

    public function testExtensionDoesNotTreatHttpZeroAsSaturation(): void
    {
        $cs = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('1.18.12', $cs);
        self::assertStringContainsString('IsBestEffortEndpoint', $cs);
        self::assertStringContainsString('NoteBestEffortCooldown', $cs);
        self::assertStringContainsString('IsTacticalQueuedEndpoint', $cs);
        self::assertStringContainsString('Math.Clamp(_networkBackoffSec, 1, 3)', $cs);
        $idx = strpos($cs, 'private static void NoteTransientPostFailure');
        self::assertNotFalse($idx);
        $chunk = substr($cs, $idx, 900);
        self::assertStringNotContainsString('NoteRateLimited', $chunk);
        self::assertStringContainsString('NoteNetworkHiccup', $chunk);

        self::assertStringContainsString('IsVideoFeedsEndpoint', $cs);
        self::assertStringContainsString('NoteAccessDenied', $cs);
        self::assertStringContainsString('COMSPECExtension/', $cs);
        self::assertStringContainsString('AccessDenied', $cs);
        self::assertStringContainsString('queued:', $cs);
        self::assertStringContainsString('QueueHeavyIngest', $cs);
        self::assertStringContainsString('PersistQueueToDisk', $cs);

        $cb = (string) file_get_contents(dirname(__DIR__, 2) . '/mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf');
        self::assertStringContainsString('Poste momentanément injoignable', $cb);
        self::assertStringContainsString('if (_sec > 3) then { _sec = 3; }', $cb);
        self::assertStringContainsString('COMSPEC_VideoFeedsBackoffUntil', $cb);
        self::assertStringContainsString('case "AccessDenied":', $cb);
        self::assertStringContainsString('Accès refusé — pause', $cb);
    }
}
