<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\AtakCasualtyRepository;
use App\Support\AtakMissionKey;
use PHPUnit\Framework\TestCase;

/**
 * Données ATAK natif remontées au site (inventaire du 2026-10-12, pistes 1 à 5) : clé de mission, pertes amies,
 * trajets véhicules / drones, contacts vus par drone, relèvements GE et détections RF.
 */
final class AtakMissionDataQuickWinsAssetTest extends TestCase
{
    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    public function testMissionKeyIsNormalizedAndLabelled(): void
    {
        self::assertSame('Op_Rubicon@Altis#202610051230', AtakMissionKey::normalize(" Op_Rubicon@Altis#202610051230\n"));
        self::assertSame('', AtakMissionKey::normalize('mission_1_map_1'));
        self::assertSame('', AtakMissionKey::normalize(['x']));
        self::assertSame(190, mb_strlen(AtakMissionKey::normalize(str_repeat('a', 300) . '@b')));
        self::assertSame('Op_Rubicon (Altis)', AtakMissionKey::label('Op_Rubicon@Altis#202610051230'));
    }

    public function testCasualtyDedupeIgnoresSmallPositionNoise(): void
    {
        $a = AtakCasualtyRepository::dedupeKey(1, 1, 'm@w#1', 'Alpha 1-1', 1000.0, 2000.0);
        self::assertSame($a, AtakCasualtyRepository::dedupeKey(1, 1, 'm@w#1', 'alpha 1-1', 1004.0, 1996.0));
        self::assertNotSame($a, AtakCasualtyRepository::dedupeKey(1, 1, 'm@w#2', 'Alpha 1-1', 1000.0, 2000.0));
    }

    public function testMissionKeyTravelsWithPositionsScreenTimeAndReports(): void
    {
        $pos = $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_updatePosition.sqf');
        $rep = $this->read('mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_submitTacticalReport.sqf');
        $ctrl = $this->read('app/Controllers/Api/AtakApiController.php');
        $replay = $this->read('app/Repositories/ReplayRepository.php');
        $screen = $this->read('app/Repositories/AtakScreenTimeRepository.php');
        $mig = $this->read('bootstrap/atak_mission_key_migration.php');
        $run = $this->read('run-migrations.php');

        self::assertStringContainsString('COMSPEC_ATAK_MissionKey', $pos);
        self::assertStringContainsString('""mission_key""', $pos);
        self::assertStringContainsString('_reportData set ["mission_key", _missionKey]', $rep);
        self::assertStringContainsString("\$extra['mission_key']", $ctrl);
        self::assertStringContainsString('->setMissionKey($reportId', $ctrl);
        self::assertStringContainsString('$repo->addItems($tenantId, (int) $user[\'id\'], $p[\'items\'], null, $missionKey, \App\Repositories\AtakScreenTimeRepository::SOURCE_SERVER)', $ctrl);
        self::assertStringContainsString('mission_key, logged_at', $replay);
        self::assertStringContainsString('function listSessions', $replay);
        self::assertStringContainsString('day, mission_key, kind, item_key', $screen);
        self::assertStringContainsString('uniq_atak_screen_time_mission', $mig);
        self::assertStringContainsString('CREATE TABLE atak_casualties', $mig);
        self::assertStringContainsString("'atak_mission_key_migration.php'", $run);
    }

    public function testReplayHasMissionSelectorCasualtiesAndVehicleTracks(): void
    {
        $view = $this->read('views/atak.php');
        $js = $this->read('public/assets/js/atak-replay.js');
        $routes = $this->read('routes/web.php');
        $svc = $this->read('app/Services/Replay/ReplayService.php');
        $repo = $this->read('app/Repositories/ReplayRepository.php');
        $ingest = $this->read('app/Services/Tactical/AtakTelemetryBatchIngest.php');
        $client = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/XEH_postInitClient.sqf');

        self::assertStringContainsString('id="atak-replay-mission-filter"', $view);
        self::assertStringContainsString("/api/replay/sessions/{missionId}', [ReplayController::class, 'sessions']", $routes);
        self::assertStringContainsString("'mission_key=' + encodeURIComponent(missionKeyFilter)", $js);
        self::assertStringContainsString('Perte amie', $js);
        self::assertStringContainsString('data.vehicleTracks', $js);
        self::assertStringContainsString('Temps de vol par pilote', $js);
        self::assertStringContainsString("'casualties' =>", $svc);
        self::assertStringContainsString('atak_vehicle_position_history h JOIN atak_vehicle_tracking v', $repo);
        self::assertStringContainsString("if (\$kind === 'kia')", $ingest);
        self::assertStringContainsString('["kind", "kia"]', $client);
        self::assertStringContainsString('"EmitTelemetry"', $client);
    }

    public function testDroneTracksAndEwBearingsReachTheSiteThrottled(): void
    {
        $drone = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/drone/fn_droneAction.sqf');
        $detect = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/drone/fn_droneDetectScan.sqf');
        $ew = $this->read('mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_ewAction.sqf');
        $contacts = $this->read('public/assets/js/atak-drone-contacts.js');
        $sigint = $this->read('public/assets/js/atak-sigint.js');
        $view = $this->read('views/atak.php');
        $tracks = $this->read('app/Repositories/TacticalTrackRepository.php');

        // Pistes drone : toutes les 5 s au plus, 6 par passage, nouvelles ou déplacées de 50 m, BDA une fois.
        self::assertStringContainsString('COMSPEC_ATAK_DroneTrkTx", diag_tickTime + 5', $drone);
        self::assertStringContainsString('if (_n >= 6) exitWith {};', $drone);
        self::assertStringContainsString('distance2D _pos) < 50', $drone);
        self::assertStringContainsString('"DRN-"', $drone);
        self::assertStringContainsString('track_uid LIKE ?', $tracks);
        self::assertStringContainsString('prefix=DRN-', $contacts);
        self::assertStringContainsString('atak-drone-contacts.js?v=', $view);
        self::assertStringContainsString('id="atak-show-drone-contacts"', $view);

        // Détecteur RF : 20 s par drone, 4 par balayage, position du récepteur seulement.
        self::assertStringContainsString('"SendRfHit"', $detect);
        self::assertStringContainsString('"SendSigint"', $detect);
        self::assertStringContainsString('_txN < 4', $detect);
        self::assertStringContainsString('>= 20', $detect);
        self::assertStringNotContainsString('getPosATL _u', $detect);

        // Goniométrie GE : 8 relèvements au plus par balayage (lui-même limité à un toutes les 45 s).
        self::assertStringContainsString('"SendSigint"', $ew);
        self::assertStringContainsString('_forEachIndex < 8', $ew);
        self::assertStringContainsString('function drawBearings(rows)', $sigint);
        self::assertStringContainsString('Math.sin(rad) * BEARING_LEN_M', $sigint);
    }
}
