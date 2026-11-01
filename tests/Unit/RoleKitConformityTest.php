<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GameEquipment\DotationOverviewService;
use App\Services\GameEquipment\RoleKitConformity;
use App\Services\GameEquipment\RoleKitLoadout;
use PHPUnit\Framework\TestCase;

/**
 * Dotation : kit par rôle (dotation type) comparé au dernier équipement remonté par le mod.
 */
final class RoleKitConformityTest extends TestCase
{
    /** @return list<mixed> */
    private function loadout(): array
    {
        return [
            ['arifle_MX_F', '', 'acc_pointer_IR', 'optic_Hamr', ['30Rnd_65x39_caseless_mag', 30], [], ''],
            [],
            ['hgun_P07_F', '', '', '', ['16Rnd_9x21_Mag', 17], [], ''],
            ['U_B_CombatUniform_mcam', [['FirstAidKit', 2]]],
            ['V_PlateCarrier1_rgr', [['30Rnd_65x39_caseless_mag', 4, 30], ['ItemRadio', 1]]],
            [],
            'H_Booniehat_khk',
            '',
            ['Binocular', '', '', '', [], [], ''],
            ['ItemMap', '', '', 'ItemCompass', 'ItemWatch', ''],
        ];
    }

    public function testWornMainClassesIgnoresContainerContent(): void
    {
        $main = RoleKitConformity::wornMainClasses($this->loadout());

        self::assertContains('arifle_MX_F', $main);
        self::assertContains('optic_Hamr', $main);
        self::assertContains('V_PlateCarrier1_rgr', $main);
        self::assertContains('H_Booniehat_khk', $main);
        self::assertContains('ItemCompass', $main);
        self::assertNotContains('FirstAidKit', $main);
        self::assertNotContains('30Rnd_65x39_caseless_mag', $main);
    }

    public function testCompareCountsItemsStoredElsewhereAndListsGaps(): void
    {
        $kit = RoleKitLoadout::normalize([
            'primary' => ['class' => 'arifle_MX_F', 'optic' => 'optic_Hamr'],
            'uniform' => 'U_B_CombatUniform_mcam',
            'vest' => 'V_PlateCarrier1_rgr',
            'headgear' => 'H_HelmetB',
            'radio' => 'ItemRadio',
        ], [
            'uniform' => [['class' => 'FirstAidKit', 'count' => 3]],
        ]);
        $loadout = $this->loadout();
        $all = ['arifle_MX_F', 'acc_pointer_IR', 'optic_Hamr', '30Rnd_65x39_caseless_mag', 'hgun_P07_F', '16Rnd_9x21_Mag',
            'U_B_CombatUniform_mcam', 'FirstAidKit', 'V_PlateCarrier1_rgr', 'ItemRadio', 'H_Booniehat_khk', 'Binocular',
            'ItemMap', 'ItemCompass', 'ItemWatch'];

        $res = RoleKitConformity::compare($kit, $all, RoleKitConformity::wornMainClasses($loadout));

        self::assertSame(7, $res['expected']);
        self::assertSame(6, $res['matched']);
        self::assertSame(['H_HelmetB'], $res['missing']);
        self::assertSame(85, $res['coverage']);
        self::assertSame('partiel', $res['level']);
        self::assertContains('H_Booniehat_khk', $res['extra']);
        self::assertContains('hgun_P07_F', $res['extra']);
        self::assertNotContains('ItemRadio', $res['extra']);
    }

    public function testBestPrefersHigherCoverageThenSpecificRole(): void
    {
        $generic = ['id' => 1, 'job_role_id' => null, 'kit' => RoleKitLoadout::normalize(['vest' => 'V_A'])];
        $specific = ['id' => 2, 'job_role_id' => 7, 'kit' => RoleKitLoadout::normalize(['vest' => 'V_A'])];
        $other = ['id' => 3, 'job_role_id' => 8, 'kit' => RoleKitLoadout::normalize(['vest' => 'V_B'])];

        $best = RoleKitConformity::best([$generic, $other, $specific], ['V_A'], ['V_A']);

        self::assertNotNull($best);
        self::assertSame(2, $best['kit']['id']);
        self::assertSame(100, $best['result']['coverage']);
        self::assertNull(RoleKitConformity::best([], ['V_A'], ['V_A']));
    }

    public function testLevels(): void
    {
        self::assertSame('conforme', RoleKitConformity::level(90));
        self::assertSame('partiel', RoleKitConformity::level(60));
        self::assertSame('ecart', RoleKitConformity::level(59));
        self::assertSame('vide', RoleKitConformity::level(0, 0));
    }

    public function testArticlesCompareWearersWithNominativeIssue(): void
    {
        $defs = [
            ['id' => 10, 'game_class_name' => 'ItemRadio'],
            ['id' => 11, 'game_class_name' => null],
        ];
        $rows = [
            ['player_name' => 'Alpha', 'user_id' => 1, 'worn_classes' => ['ItemRadio']],
            ['player_name' => 'Bravo', 'user_id' => 2, 'worn_classes' => ['itemradio']],
            ['player_name' => 'Charlie', 'user_id' => 3, 'worn_classes' => []],
            ['player_name' => 'Invité', 'user_id' => null, 'worn_classes' => ['ItemRadio']],
        ];

        $out = DotationOverviewService::articles($defs, [10 => [1, 3, 99]], $rows, ['itemradio' => ['name' => 'AN/PRC-152']]);

        self::assertArrayNotHasKey(11, $out);
        self::assertSame('AN/PRC-152', $out[10]['game_name']);
        self::assertSame(3, $out[10]['wearers']);
        self::assertSame(['Bravo', 'Invité'], $out[10]['without_issue_names']);
        // Charlie est doté, vu en jeu, sans la radio ; l’utilisateur 99 n’a pas été vu : pas compté.
        self::assertSame(1, $out[10]['issued_not_worn']);
    }

    public function testPeriodFallsBackToThirtyDays(): void
    {
        self::assertSame(7, DotationOverviewService::period('7'));
        self::assertSame(30, DotationOverviewService::period('365'));
        self::assertSame(30, DotationOverviewService::period(null));
    }
}
