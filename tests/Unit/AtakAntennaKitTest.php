<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Antennes transportables (fn_antennaKit) : objets, relais par le code existant, MRH facultatif, aucune boucle. */
final class AtakAntennaKitTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    public function testObjetsDeclaresAvecModelesDuJeu(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        foreach (['COMSPEC_ATAK_SatAntennaKit', 'COMSPEC_ATAK_MastAntennaKit'] as $kit) {
            self::assertStringContainsString("class {$kit}: ItemCore", $cfg);
            self::assertStringContainsString("class Item_{$kit}: Item_Base_F", $cfg);
            self::assertStringContainsString("\"Item_{$kit}\"", $cfg);
        }
        self::assertStringContainsString('class antennaKit {};', $cfg);
        self::assertStringContainsString('model="\A3\Props_F_Exp\Military\Camps\SatelliteAntenna_01_F.p3d"', $cfg);
        self::assertStringContainsString('model="\A3\Props_F_Enoch\Military\Equipment\OmniDirectionalAntenna_01_F.p3d"', $cfg);
        foreach (['sat', 'mast'] as $id) {
            self::assertFileExists(dirname(__DIR__, 2) . '/' . self::MAIN . "data/item_antkit_{$id}.paa");
        }
    }

    public function testRelaisParLeCodeExistantEtRetraitDuReseau(): void
    {
        $kit = self::read(self::MAIN . 'functions/network/fn_antennaKit.sqf');
        self::assertStringContainsString('["register", [_o,', $kit);
        self::assertSame(3, substr_count($kit, '["remove", ['), 'Repli, destruction et suppression retirent le relais.');
        self::assertStringContainsString('"Killed"', $kit);
        self::assertStringContainsString('"Deleted"', $kit);
        self::assertStringNotContainsString('while {', $kit);
        self::assertStringNotContainsString('addPerFrameHandler', $kit);
        $conv = self::read(self::MAIN . 'functions/network/fn_relayConvert.sqf');
        self::assertStringContainsString('case "register":', $conv);
        self::assertStringContainsString('case "remove":', $conv);
    }

    public function testReglagesDeclares(): void
    {
        $pre = self::read(self::MAIN . 'XEH_preInit.sqf');
        foreach (['range', 'gain', 'height', 'mbps', 'time'] as $key) {
            self::assertStringContainsString("comspec_atak_native_antkit_%1_{$key}", $pre);
        }
        self::assertStringContainsString('"comspec_atak_native_antkit_max"', $pre);
        self::assertStringContainsString('"comspec_atak_native_antkit_mast_root"', $pre);
        self::assertStringContainsString('"comspec_atak_native_relay_mrh"', $pre);
    }

    public function testMrhReste100PourCentFacultatif(): void
    {
        $cfg = self::read(self::MAIN . 'config.cpp');
        self::assertDoesNotMatchRegularExpression('/requiredAddons\[\]=\{[^}]*MRH/i', $cfg);
        self::assertStringNotContainsString('\MRH', $cfg);
        $conv = self::read(self::MAIN . 'functions/network/fn_relayConvert.sqf');
        self::assertStringContainsString('select { isClass (configFile >> "CfgVehicles" >> _x) }', $conv);
    }
}
