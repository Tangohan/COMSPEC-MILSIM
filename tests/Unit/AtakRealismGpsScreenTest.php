<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tactical\AtakExperienceService;
use PHPUnit\Framework\TestCase;

/**
 * ATAK natif : réglages de réalisme du GPS (pertes de fix, fiabilité), anneaux qui suivent la position GPS affichée,
 * sang en couleur qui sèche, variantes de dégâts et parasites du brouillage sous les bris d'écran, montre Garmin en
 * objet divers, antennes du jeu converties en relais.
 */
final class AtakRealismGpsScreenTest extends TestCase
{
    private const MAIN = __DIR__ . '/../../mod/COMSPEC_ATAK_Native/Sources/addons/main';

    private function read(string $path): string
    {
        return (string) file_get_contents(self::MAIN . '/' . $path);
    }

    public function testGpsReliabilitySettingsExistInGameAndOnTheSite(): void
    {
        $ids = array_column((new AtakExperienceService())->networkCatalog(), 'id');
        self::assertContains('net_gps_loss', $ids);
        self::assertContains('net_gps_rel', $ids);
        $cond = $this->read('functions/network/fn_netCond.sqf');
        self::assertStringContainsString('["gpsLoss", "comspec_atak_native_gps_loss", "net_gps_loss", "num", 100,', $cond);
        self::assertStringContainsString('["gpsRel", "comspec_atak_native_gps_rel", "net_gps_rel", "num", 1,', $cond);
        $pre = $this->read('XEH_preInit.sqf');
        self::assertStringContainsString('"comspec_atak_native_gps_loss", "LIST"', $pre);
        self::assertStringContainsString('"comspec_atak_native_gps_rel", "LIST"', $pre);
        $fix = $this->read('functions/core/fn_gpsFix.sqf');
        self::assertStringContainsString('["gpsLoss"] call comspec_atak_native_fnc_netCond', $fix);
        self::assertStringContainsString('COMSPEC_ATAK_GpsOutUntil', $fix);
        self::assertStringContainsString('COMSPEC_ATAK_GpsJump', $fix);
    }

    public function testMyRingsAndDistancesFollowTheDisplayedGpsPosition(): void
    {
        self::assertStringContainsString('class gpsPos {};', $this->read('config.cpp'));
        foreach (['functions/map/fn_mapOnDraw.sqf', 'functions/map/fn_mapLabels.sqf', 'functions/map/fn_mapCenter.sqf'] as $f) {
            self::assertStringContainsString('call comspec_atak_native_fnc_gpsPos', $this->read($f), $f);
        }
        self::assertStringContainsString('_acc = round _e;', $this->read('functions/map/fn_localDataRefresh.sqf'));
    }

    public function testScreenEffectsStayAboveApps(): void
    {
        self::assertStringContainsString('[] call comspec_atak_native_fnc_overlayRaise;', $this->read('functions/ui/fn_pageCtrl.sqf'));
        $ov = $this->read('functions/ui/fn_deviceOverlay.sqf');
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_OvBuild", diag_frameNo];', $ov);
        self::assertStringContainsString('[_hp] call comspec_atak_native_fnc_screenFx;', $ov);
        self::assertStringContainsString('class overlayRaise {}; class screenFx {};', $this->read('config.cpp'));
    }

    public function testColourBloodAndDamageTexturesShip(): void
    {
        $surf = $this->read('functions/ui/fn_screenSurface.sqf');
        self::assertStringContainsString('bloodc_print_%1', $surf);
        self::assertStringNotContainsString('"blood_%1_%2"', $surf);
        foreach (['splat' => 2, 'hand' => 2, 'drip' => 2, 'spatter' => 2, 'pool' => 2, 'smear' => 3, 'print' => 4] as $kind => $n) {
            for ($i = 0; $i < $n; $i++) {
                self::assertFileExists(self::MAIN . "/data/bloodc_{$kind}_{$i}.paa");
            }
        }
        foreach (['fx_bleed_0', 'fx_bleed_1', 'fx_mura_0', 'fx_band_1', 'fx_backlight_0_port', 'fx_backlight_1_land', 'fx_emi_0', 'fx_emi_3'] as $t) {
            $path = self::MAIN . "/data/{$t}.paa";
            self::assertFileExists($path);
            // DXT5 (0xFF05), comme les textures de HEMTT.
            self::assertSame("\x05\xff", substr((string) file_get_contents($path), 0, 2), $t);
        }
        // Le sang sèche : heure de la tache gardée (index 7).
        self::assertStringContainsString('_alpha max 0.05 min 1, time];', $this->read('functions/core/fn_screenDirt.sqf'));
    }

    public function testGarminWatchAlsoExistsAsMiscItem(): void
    {
        $cfg = $this->read('config.cpp');
        self::assertMatchesRegularExpression('/class COMSPEC_ATAK_GarminWatchItem: ItemCore \{[^}]*type=4096;/s', $cfg);
        self::assertStringContainsString('"COMSPEC_ATAK_GarminWatchItem" in (items player)', $this->read('functions/garmin/fn_garminTick.sqf'));
        self::assertStringContainsString('"COMSPEC_ATAK_GarminWatchItem" in (items player)', $this->read('functions/pages/fn_resynchRun.sqf'));
        self::assertStringContainsString('STR_COMSPEC_ATAK_CFG_FF273F9B2F', $this->read('stringtable.xml'));
    }

    public function testGameAntennasCanBecomeRelays(): void
    {
        self::assertStringContainsString('class relayConvert {};', $this->read('config.cpp'));
        self::assertStringContainsString('["sweep"] call comspec_atak_native_fnc_relayConvert;', $this->read('XEH_postInitServer.sqf'));
        self::assertStringContainsString('"comspec_atak_native_relay_convert", "LIST"', $this->read('XEH_preInit.sqf'));
        $conv = $this->read('functions/network/fn_relayConvert.sqf');
        self::assertStringContainsString('["TRANSMITTER"]', $conv);
        self::assertStringContainsString('"ttower"', $conv);
    }
}
