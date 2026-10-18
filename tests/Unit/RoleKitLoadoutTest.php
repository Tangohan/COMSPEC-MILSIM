<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GameEquipment\RoleKitLoadout;
use App\Support\ArsenalLoadoutItems;
use PHPUnit\Framework\TestCase;

/**
 * Kit par rôle → chaîne getUnitLoadout appliquée en jeu par setUnitLoadout (tenue d’arsenal).
 */
final class RoleKitLoadoutTest extends TestCase
{
    public function testEmptyKitBuildsTenEmptySlots(): void
    {
        $kit = RoleKitLoadout::normalize([]);

        self::assertTrue(RoleKitLoadout::isEmpty($kit));
        self::assertSame('[[],[],[],[],[],[],"","",[],["","","","","",""]]', RoleKitLoadout::build($kit));
    }

    public function testFullKitMatchesArmaLoadoutFormat(): void
    {
        $kit = RoleKitLoadout::normalize(
            [
                'primary' => [
                    'class' => 'arifle_MX_F', 'optic' => 'optic_Hamr', 'muzzle' => 'muzzle_snds_H',
                    'pointer' => 'acc_pointer_IR', 'bipod' => 'bipod_01_F_snd', 'magazine' => '30Rnd_65x39_caseless_mag',
                ],
                'secondary' => ['class' => 'launch_NLAW_F', 'magazine' => 'NLAW_F'],
                'handgun' => ['class' => 'hgun_P07_F', 'magazine' => '16Rnd_9x21_Mag'],
                'uniform' => 'U_B_CombatUniform_mcam',
                'vest' => 'V_PlateCarrier1_rgr',
                'backpack' => 'B_AssaultPack_mcamo',
                'headgear' => 'H_HelmetB',
                'goggles' => 'G_Tactical_Clear',
                'nvg' => 'NVGoggles',
                'binocular' => 'Binocular',
                'radio' => 'ItemRadio',
                'map' => 'ItemMap',
                'compass' => 'ItemCompass',
                'watch' => 'ItemWatch',
            ],
            [
                'uniform' => [['class' => 'FirstAidKit', 'count' => '3']],
                'vest' => [
                    ['class' => '30Rnd_65x39_caseless_mag', 'count' => 4],
                    ['class' => 'HandGrenade', 'count' => 2],
                    ['class' => '30Rnd_65x39_caseless_mag', 'count' => 2],
                    ['class' => '', 'count' => 5],
                ],
                'backpack' => [['class' => 'Medikit', 'count' => 0]],
            ],
        );
        $catalog = [
            'handgrenade' => ['kind' => 'magazine', 'max_ammo' => 1, 'mod' => 'Arma 3'],
            '16rnd_9x21_mag' => ['kind' => 'magazine', 'max_ammo' => 17, 'mod' => ''],
            'firstaidkit' => ['kind' => 'medical', 'max_ammo' => null, 'mod' => 'Arma 3'],
        ];

        $str = RoleKitLoadout::build($kit, $catalog);

        self::assertSame(
            '[["arifle_MX_F","muzzle_snds_H","acc_pointer_IR","optic_Hamr",["30Rnd_65x39_caseless_mag",30],[],"bipod_01_F_snd"],'
            . '["launch_NLAW_F","","","",["NLAW_F",1],[],""],'
            . '["hgun_P07_F","","","",["16Rnd_9x21_Mag",17],[],""],'
            . '["U_B_CombatUniform_mcam",[["FirstAidKit",3]]],'
            . '["V_PlateCarrier1_rgr",[["30Rnd_65x39_caseless_mag",6,30],["HandGrenade",2,1]]],'
            . '["B_AssaultPack_mcamo",[["Medikit",1]]],'
            . '"H_HelmetB","G_Tactical_Clear",'
            . '["Binocular","","","",[],[],""],'
            . '["ItemMap","","ItemRadio","ItemCompass","ItemWatch","NVGoggles"]]',
            $str
        );

        // Relu par le parseur des tenues d’arsenal (page Équipement, overlay en jeu).
        $parsed = ArsenalLoadoutItems::parse($str);
        self::assertCount(10, $parsed);
        self::assertNotSame([], ArsenalLoadoutItems::grouped($str));
    }

    public function testAttachmentsAndCargoNeedTheirParent(): void
    {
        $kit = RoleKitLoadout::normalize(
            ['primary' => ['class' => '', 'optic' => 'optic_Hamr'], 'vest' => ''],
            ['vest' => [['class' => 'FirstAidKit', 'count' => 2]]],
        );

        self::assertSame('', $kit['weapons']['primary']['optic']);
        self::assertSame([], $kit['cargo']['vest']);
        self::assertTrue(RoleKitLoadout::isEmpty($kit));
    }

    public function testInvalidClassNamesAreDropped(): void
    {
        $kit = RoleKitLoadout::normalize(['uniform' => 'U_"]];hack', 'vest' => 'V_Good_F']);

        self::assertSame('', $kit['simple']['uniform']);
        self::assertSame('V_Good_F', $kit['simple']['vest']);
        self::assertStringNotContainsString('hack', RoleKitLoadout::build($kit));
    }

    public function testNormalizeIsIdempotentOnStoredShape(): void
    {
        $kit = RoleKitLoadout::normalize(
            ['primary' => ['class' => 'arifle_MX_F'], 'backpack' => 'B_AssaultPack_mcamo'],
            ['backpack' => [['class' => 'Medikit', 'count' => 1]]],
        );
        $again = RoleKitLoadout::normalize(json_decode((string) json_encode($kit), true));

        self::assertSame($kit, $again);
    }

    public function testRequiredModsAndUnknownClasses(): void
    {
        $catalog = [
            'rhs_weap_m4a1' => ['mod' => 'RHS: USAF'],
            'rhsusf_spcs_ocp' => ['mod' => 'RHS: USAF'],
            'acre_prc152' => ['mod' => 'ACRE2'],
            'firstaidkit' => ['mod' => 'Arma 3'],
            'itemmap' => ['mod' => ''],
        ];
        $classes = ['rhs_weap_m4a1', 'rhsusf_spcs_ocp', 'ACRE_PRC152', 'FirstAidKit', 'ItemMap', 'Custom_Thing'];

        self::assertSame(['ACRE2', 'RHS: USAF'], RoleKitLoadout::requiredMods($classes, $catalog));
        self::assertSame(['Custom_Thing'], RoleKitLoadout::unknownClasses($classes, $catalog));
    }

    public function testAmmoAndWardrobeName(): void
    {
        self::assertSame(30, RoleKitLoadout::ammoFor('rhs_mag_30Rnd_556x45_M855A1_Stanag', []));
        self::assertSame(200, RoleKitLoadout::ammoFor('200Rnd_65x39_cased_Box', []));
        self::assertNull(RoleKitLoadout::ammoFor('SmokeShell', []));
        self::assertSame(1, RoleKitLoadout::ammoFor('SmokeShell', ['smokeshell' => ['max_ammo' => 1]]));
        self::assertSame('[Médecin] Kit standard', RoleKitLoadout::wardrobeName('Kit standard', 'Médecin'));
        self::assertSame('[Tous les rôles] Kit de base', RoleKitLoadout::wardrobeName('Kit de base', null));
    }
}
