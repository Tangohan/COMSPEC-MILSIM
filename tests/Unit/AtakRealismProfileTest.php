<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakExperienceService;
use PHPUnit\Framework\TestCase;

/**
 * Profils de réalisme de l'ATAK natif (Milsim par défaut), positions BFT par points, GPS naturel, fonds satellite.
 */
final class AtakRealismProfileTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return (string) file_get_contents($path);
    }

    public function testSiteRulesExistForEveryNewCondition(): void
    {
        $ids = array_column((new AtakExperienceService())->networkCatalog(), 'id');
        foreach (['net_profile', 'net_pli', 'net_pli_rate', 'net_gps', 'net_gps_k', 'net_gps_fix', 'net_battery'] as $id) {
            self::assertContains($id, $ids);
        }
    }

    public function testMilsimIsTheDefaultAndEverySettingStaysAdjustable(): void
    {
        $cond = $this->read(self::MAIN . '/functions/network/fn_netCond.sqf');
        self::assertStringContainsString('["profile", "comspec_atak_native_realism_profile", "net_profile", "num", 3,', $cond);
        // Un réglage CBA changé l'emporte sur le profil ; le menu Échap puis le site l'emportent ensuite.
        self::assertStringContainsString('if (!isNil "_pv" && {_v isEqualTo _def}) then { _v = _pv; _s = "profile"; };', $cond);
        $pre = $this->read(self::MAIN . '/XEH_preInit.sqf');
        self::assertStringContainsString('[[3, 2, 1, 0], ["Milsim", "Standard", "Arcade", "Personnalisé (réglages ci-dessous)"], 0]', $pre);
        // Le menu Échap n'envoie que les écarts au profil choisi.
        self::assertStringContainsString('["expect", _prof] call comspec_atak_native_fnc_netConfig', $this->read(self::MAIN . '/functions/network/fn_netConfig.sqf'));
    }

    public function testBftReadsPositionReportsAndGpsGoesThroughEwEffects(): void
    {
        self::assertStringContainsString('_obj getVariable ["COMSPEC_ATAK_Pli", []]', $this->read(self::MAIN . '/functions/map/fn_localDataRefresh.sqf'));
        self::assertStringContainsString('["tick"] call comspec_atak_native_fnc_pli;', $this->read(self::MAIN . '/XEH_postInitClient.sqf'));
        self::assertStringContainsString('call comspec_atak_native_fnc_gpsFix', $this->read(self::MAIN . '/functions/pages/fn_ewEffects.sqf'));
        $cfg = $this->read(self::MAIN . '/config.cpp');
        self::assertStringContainsString('class gpsFix {}; class pli {};', $cfg);
        self::assertStringContainsString('class mapCtrl {};', $cfg);
    }

    public function testSatelliteMapsShareTheMapHandlers(): void
    {
        $disp = $this->read(self::MAIN . '/ui/displays.hpp');
        self::assertStringContainsString('class MapSat: COMSPEC_RscMapAtakSat { idc=COMSPEC_ATAK_IDC_MAP_SAT;', $disp);
        self::assertStringContainsString('class MapHyb: COMSPEC_RscMapAtakHyb { idc=COMSPEC_ATAK_IDC_MAP_HYB;', $disp);
        self::assertSame(3, substr_count($disp, 'onDraw="private _perfT = diag_tickTime; _this call comspec_atak_native_fnc_mapOnDraw;'));
        self::assertStringContainsString('maxSatelliteAlpha=1;', $this->read(self::MAIN . '/ui/controls.hpp'));
        // Le téléphone ne prend plus la carte par son numéro : fn_mapCtrl donne celle du fond choisi.
        foreach (glob(self::MAIN . '/functions/*/*.sqf') ?: [] as $f) {
            if (basename($f) === 'fn_mapCtrl.sqf') {
                continue;
            }
            self::assertStringNotContainsString('displayCtrl 88530', $this->read($f), basename($f));
        }
    }
}
