<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ArsenalLoadoutItems;
use PHPUnit\Framework\TestCase;

final class ArsenalLoadoutItemsTest extends TestCase
{
    public function testGroupedListsWeaponsUniformAndCargo(): void
    {
        $payload = '[["arifle_MX_GL_F","","acc_pointer_IR","optic_Aco",["30Rnd_65x39_caseless_mag",30],["3Rnd_HE_Grenade_shell",3],""],["launch_NLAW_F","","","",["NLAW_F",1],[],""],["hgun_P07_F","","","",["16Rnd_9x21_Mag",16],[],""],["U_B_CombatUniform_mcam",[["FirstAidKit",1],["30Rnd_65x39_caseless_mag",2,30]]],["V_PlateCarrier1_rgr",[["30Rnd_65x39_caseless_mag",7,30]]],["B_AssaultPack_rgr",[]],"H_HelmetB","G_Tactical_Clear",["Binocular","","","",[],[],""],["ItemMap","ItemGPS","ItemRadio","ItemCompass","ItemWatch","NVGoggles"]]';

        $sections = ArsenalLoadoutItems::grouped($payload);
        $byTitle = [];
        foreach ($sections as $section) {
            $byTitle[$section['title']] = $section['items'];
        }

        self::assertArrayHasKey('Arme', $byTitle);
        self::assertArrayHasKey('Lanceur', $byTitle);
        self::assertArrayHasKey('Pistolet', $byTitle);
        self::assertArrayHasKey('Tenue', $byTitle);
        self::assertArrayHasKey('Gilet', $byTitle);
        self::assertArrayHasKey('Sac', $byTitle);
        self::assertArrayHasKey('Casque', $byTitle);
        self::assertArrayHasKey('Équipement porté', $byTitle);

        $armeNames = array_column($byTitle['Arme'], 'name');
        self::assertTrue(self::containsLoose($armeNames, 'MX'));
        self::assertTrue(self::containsLoose($armeNames, 'Aco'));

        $giletQty = 0;
        foreach ($byTitle['Gilet'] as $row) {
            if (str_contains(strtolower($row['name']), '30rnd')) {
                $giletQty = (int) $row['qty'];
            }
        }
        self::assertSame(7, $giletQty);
    }

    public function testAceVersionWrapIsUnwrapped(): void
    {
        $inner = '[["arifle_MX_F","","","",["30Rnd_65x39_caseless_mag",30],[],""],[],[],["U_B_CombatUniform_mcam",[]],[],[],"","",[],["","","","","",""]]';
        $wrapped = '[' . $inner . ',2]';
        $sections = ArsenalLoadoutItems::grouped($wrapped);
        $titles = array_column($sections, 'title');
        self::assertContains('Arme', $titles);
        self::assertContains('Tenue', $titles);
    }

    public function testEmptyPayloadYieldsNoSections(): void
    {
        self::assertSame([], ArsenalLoadoutItems::grouped(''));
        self::assertSame([], ArsenalLoadoutItems::grouped('not-an-array'));
    }

    public function testDisplayNameDropsConfigNoise(): void
    {
        self::assertSame('MX GL', ArsenalLoadoutItems::displayName('arifle_MX_GL_F'));
        self::assertSame('CombatUniform mcam', ArsenalLoadoutItems::displayName('U_B_CombatUniform_mcam'));
        self::assertSame('Map', ArsenalLoadoutItems::displayName('ItemMap'));
    }

    /**
     * @param list<string> $names
     */
    private static function containsLoose(array $names, string $needle): bool
    {
        $needle = strtolower($needle);
        foreach ($names as $name) {
            if (str_contains(strtolower($name), $needle)) {
                return true;
            }
        }

        return false;
    }
}
