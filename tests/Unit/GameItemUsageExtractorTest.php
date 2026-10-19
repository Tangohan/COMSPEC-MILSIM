<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GameEquipment\GameItemKinds;
use App\Services\GameEquipment\GameItemUsageExtractor;
use App\Services\OperatorGame\OperatorGameObservationNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Équipement remonté par le mod : catalogue envoyé par les mods récents, déduction depuis `equipment`
 * et le loadout brut pour les anciens, liste des mods.
 */
final class GameItemUsageExtractorTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    private static function byClass(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $out[$it['class']] = $it;
        }

        return $out;
    }

    public function testItemCatalogIsPreferredSourceWithKindAndMod(): void
    {
        $payload = (new OperatorGameObservationNormalizer())->normalize([
            'steam_id' => '76561198000000001',
            'equipment' => [
                'vest_class' => 'rhsusf_spcs_ocp',
                'primary' => ['class' => 'rhs_weap_m4a1', 'display' => 'M4A1', 'optic' => 'rhsusf_acc_eotech_552'],
            ],
            'item_catalog' => [
                ['class' => 'rhs_weap_m4a1', 'name' => 'M4A1 Carbine', 'kind' => 'primary', 'mod' => 'RHS: USAF'],
                ['class' => 'rhsusf_acc_eotech_552', 'name' => 'EOTech 552', 'kind' => 'optic', 'mod' => 'RHS: USAF'],
                ['class' => 'rhsusf_spcs_ocp', 'name' => 'SPCS (OCP)', 'kind' => 'vest', 'mod' => 'RHS: USAF'],
                ['class' => 'ACRE_PRC152', 'name' => 'AN/PRC-152', 'kind' => 'radio', 'mod' => 'ACRE2'],
                ['class' => 'FirstAidKit', 'name' => 'First Aid Kit', 'kind' => 'medical', 'mod' => 'Arma 3'],
                ['class' => 'bad class"; DROP', 'name' => 'x', 'kind' => 'item', 'mod' => ''],
                ['class' => 'H_HelmetB', 'name' => 'ECH', 'kind' => 'helmet', 'mod' => ''],
            ],
        ]);

        $data = (new GameItemUsageExtractor())->extract($payload);
        $items = self::byClass($data['items']);

        self::assertArrayHasKey('rhs_weap_m4a1', $items);
        self::assertSame('primary', $items['rhs_weap_m4a1']['kind']);
        self::assertSame('M4A1 Carbine', $items['rhs_weap_m4a1']['name']);
        self::assertSame('RHS: USAF', $items['rhs_weap_m4a1']['mod']);
        self::assertSame('optic', $items['rhsusf_acc_eotech_552']['kind']);
        self::assertSame('vest', $items['rhsusf_spcs_ocp']['kind']);
        self::assertSame('radio', $items['ACRE_PRC152']['kind']);
        self::assertSame('Arma 3', $items['FirstAidKit']['mod']);
        self::assertSame('headgear', $items['H_HelmetB']['kind'], 'alias helmet → headgear');
        self::assertArrayNotHasKey('bad class"; DROP', $items);
        self::assertNull($data['mods'], 'mods absent : la liste du joueur ne doit pas être effacée');
    }

    public function testOlderModWithoutCatalogDerivesFromEquipmentAndLoadout(): void
    {
        $payload = (new OperatorGameObservationNormalizer())->normalize([
            'steam_id' => '76561198000000002',
            'equipment' => [
                'uniform_class' => 'U_B_CombatUniform_mcam',
                'uniform_display' => 'Combat Fatigues (MTP)',
                'vest_class' => 'V_PlateCarrier1_rgr',
                'backpack_class' => 'B_AssaultPack_mcamo',
                'helmet_class' => 'H_HelmetB',
                'goggles_class' => 'G_Tactical_Clear',
                'nvgs_class' => 'NVGoggles',
                'primary' => [
                    'class' => 'arifle_MX_F', 'display' => 'MX 6.5 mm',
                    'optic' => 'optic_Hamr', 'pointer' => 'acc_pointer_IR', 'muzzle' => '', 'bipod' => '',
                    'magazine' => '30Rnd_65x39_caseless_mag',
                ],
                'handgun' => ['class' => 'hgun_P07_F', 'display' => 'P07 9 mm'],
                'radios' => ['ItemRadio'],
                'magazines' => [['class' => 'HandGrenade', 'count' => 2, 'display' => 'RGO Grenade']],
                'medical_items' => ['FirstAidKit'],
                'loadout' => [
                    ['arifle_MX_F', '', 'acc_pointer_IR', 'optic_Hamr', ['30Rnd_65x39_caseless_mag', 30], [], ''],
                    [],
                    ['hgun_P07_F', '', '', '', ['16Rnd_9x21_Mag', 17], [], ''],
                    ['U_B_CombatUniform_mcam', [['FirstAidKit', 1], ['16Rnd_9x21_Mag', 2, 17]]],
                    ['V_PlateCarrier1_rgr', [['30Rnd_65x39_caseless_mag', 6, 30], ['HandGrenade', 2, 1]]],
                    ['B_AssaultPack_mcamo', [[['launch_NLAW_F', '', '', '', [], [], ''], 1]]],
                    'H_HelmetB',
                    'G_Tactical_Clear',
                    ['Binocular', '', '', '', [], [], ''],
                    ['ItemMap', '', 'ItemRadio', 'ItemCompass', 'ItemWatch', 'NVGoggles'],
                ],
            ],
        ]);

        $data = (new GameItemUsageExtractor())->extract($payload);
        $items = self::byClass($data['items']);

        self::assertSame('uniform', $items['U_B_CombatUniform_mcam']['kind']);
        self::assertSame('Combat Fatigues (MTP)', $items['U_B_CombatUniform_mcam']['name']);
        self::assertSame('vest', $items['V_PlateCarrier1_rgr']['kind']);
        self::assertSame('backpack', $items['B_AssaultPack_mcamo']['kind']);
        self::assertSame('headgear', $items['H_HelmetB']['kind']);
        self::assertSame('goggles', $items['G_Tactical_Clear']['kind']);
        self::assertSame('nvg', $items['NVGoggles']['kind']);
        self::assertSame('primary', $items['arifle_MX_F']['kind']);
        self::assertSame('MX 6.5 mm', $items['arifle_MX_F']['name']);
        self::assertSame('optic', $items['optic_Hamr']['kind']);
        self::assertSame('pointer', $items['acc_pointer_IR']['kind']);
        self::assertSame('handgun', $items['hgun_P07_F']['kind']);
        self::assertSame('radio', $items['ItemRadio']['kind']);
        self::assertSame('medical', $items['FirstAidKit']['kind'], 'médical l’emporte sur « objet » du loadout');
        self::assertSame('magazine', $items['HandGrenade']['kind']);
        self::assertSame('magazine', $items['30Rnd_65x39_caseless_mag']['kind']);
        self::assertSame(30, $items['30Rnd_65x39_caseless_mag']['max_ammo']);
        self::assertSame(17, $items['16Rnd_9x21_Mag']['max_ammo']);
        self::assertSame('binocular', $items['Binocular']['kind']);
        self::assertSame('item', $items['ItemMap']['kind']);
        self::assertArrayHasKey('launch_NLAW_F', $items, 'arme rangée dans le sac');
        foreach ($items as $it) {
            self::assertSame('', $it['mod'], 'mod inconnu sans item_catalog');
            self::assertArrayHasKey($it['kind'], GameItemKinds::KINDS);
        }
        self::assertArrayNotHasKey('', $items);
    }

    public function testModsListIsNormalised(): void
    {
        $data = (new GameItemUsageExtractor())->extract([
            'mods' => [
                ['name' => 'CBA_A3', 'dir' => '@CBA_A3', 'workshop' => '450814997', 'dlc' => false],
                ['name' => 'ACE3', 'dir' => '@ace', 'workshop' => '463939057', 'dlc' => false],
                ['name' => 'Contact', 'dir' => 'enoch', 'workshop' => '', 'dlc' => true],
                ['name' => 'Doublon', 'dir' => '@ace', 'workshop' => '1'],
                ['name' => '', 'dir' => ''],
                'RHSUSAF',
            ],
        ]);

        self::assertIsArray($data['mods']);
        self::assertCount(4, $data['mods']);
        self::assertSame(['dir' => '@CBA_A3', 'name' => 'CBA_A3', 'workshop_id' => '450814997', 'is_dlc' => false], $data['mods'][0]);
        self::assertSame('463939057', $data['mods'][1]['workshop_id']);
        self::assertTrue($data['mods'][2]['is_dlc']);
        self::assertSame('RHSUSAF', $data['mods'][3]['dir']);
        self::assertSame([], $data['items']);
    }

    public function testEmptyModsListMeansNoMods(): void
    {
        $data = (new GameItemUsageExtractor())->extract(['mods' => []]);

        self::assertSame([], $data['mods']);
    }

    public function testKindNormalisation(): void
    {
        self::assertSame('secondary', GameItemKinds::normalize('launcher'));
        self::assertSame('nvg', GameItemKinds::normalize('NVGS'));
        self::assertSame('item', GameItemKinds::normalize('unknown-kind'));
        self::assertSame('item', GameItemKinds::normalize(null));
    }
}
