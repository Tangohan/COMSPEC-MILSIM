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

    public function testSqfTextAndAceExtendedInfoAreRead(): void
    {
        $inner = '[["arifle_MX_F","","","",["30Rnd_65x39_caseless_mag",30],[],""],[],[],["U_B_CombatUniform_mcam",[]],[],[],"H_HelmetB","",[],["ItemMap","","","","",""]]';
        // `str` SQF : infos étendues ACE (true), guillemets doublés dans une chaîne — pas du JSON.
        $sqf = '[' . $inner . ',[["ace_earplugs",true],["ace_insignia","Say ""hi"""]]]';
        $titles = array_column(ArsenalLoadoutItems::grouped($sqf), 'title');
        self::assertContains('Arme', $titles);
        self::assertContains('Casque', $titles);
        // Export ACE « Mes équipements » : [nom, tenue].
        self::assertContains('Tenue', array_column(ArsenalLoadoutItems::grouped('["Kit A",' . $inner . ']'), 'title'));
    }

    public function testPayloadWithQuotesDoubledByCallExtensionIsRead(): void
    {
        // SyncWardrobe : Arma passe l’argument en str, chaque guillemet du str du loadout arrive doublé.
        $inner = '[["arifle_MX_F","","","",["30Rnd_65x39_caseless_mag",30],[],""],[],[],["U_B_CombatUniform_mcam",[]],[],[],"H_HelmetB","",[],["ItemMap","","","","",""]]';
        $sent = '[' . $inner . ',[["ace_insignia","Say ""hi"""]]]';
        $stored = str_replace('"', '""', $sent);

        $titles = array_column(ArsenalLoadoutItems::grouped($stored), 'title');
        self::assertContains('Arme', $titles);
        self::assertContains('Casque', $titles);
        self::assertContains('helmet', ArsenalLoadoutItems::presentKinds($stored));
        // Copier pour ACE : [tenue, infos étendues], infos étendues gardées.
        $expected = '[' . $inner . ',[["ace_insignia","Say ""hi"""]]]';
        self::assertSame($expected, ArsenalLoadoutItems::aceExport($stored));
        self::assertSame($expected, ArsenalLoadoutItems::aceExport('"' . $stored . '"'));
        self::assertSame(['Say "hi"'], array_slice(ArsenalLoadoutItems::decode($stored)[1][0] ?? [], 1));
    }

    public function testUnknownShapeStillListsItems(): void
    {
        $sections = ArsenalLoadoutItems::grouped('[["arifle_MX_F","H_HelmetB"]]');
        self::assertSame('Contenu', $sections[0]['title'] ?? null);
        self::assertCount(2, $sections[0]['items']);
    }

    public function testAceExportIsSqfForTheArsenal(): void
    {
        $inner = '[["arifle_MX_F","","","",["30Rnd_65x39_caseless_mag",30],[],""],[],[],["U_B_CombatUniform_mcam",[]],[],[],"H_HelmetB","",[],["ItemMap","","","","",""]]';
        self::assertSame('[' . $inner . ',[]]', ArsenalLoadoutItems::aceExport('[' . $inner . ',2]'));
        self::assertNull(ArsenalLoadoutItems::aceExport('pas une tenue'));

        $list = ArsenalLoadoutItems::aceExportList([
            ['name' => 'Kit "A"', 'payload' => $inner],
            ['name' => 'Kit "A"', 'payload' => $inner],
            ['name' => 'Illisible', 'payload' => 'x'],
        ]);
        self::assertStringStartsWith('[["Kit ""A""",[[["arifle_MX_F"', $list);
        self::assertStringContainsString('["Kit ""A"" (2)",', $list);
        self::assertStringNotContainsString('Illisible', $list);
        self::assertSame('[1.5,0,-2,"",false]', ArsenalLoadoutItems::sqf([1.5, 0.0, -2, null, false]));
    }

    /**
     * « Copier pour ACE » : toujours [getUnitLoadout à 10 entrées, infos étendues], quel que soit l’emballage stocké.
     * Une tenue emballée une fois de trop faisait planter la liste des tenues d’ACE (fnc_fillLoadoutsList, ligne 101 :
     * « Type Tableau, Chaîne attendu / 1 éléments fournis, 6 attendus »).
     */
    public function testAceExportIsAlwaysOneCompleteLoadoutWithExtendedInfo(): void
    {
        $inner = '[["arifle_MX_F","","","",["30Rnd_65x39_caseless_mag",30],[],""],[],[],["U_B_CombatUniform_mcam",[]],[],[],"H_HelmetB","",[],["ItemMap","","","","",""]]';
        $ext = '[["ace_earplugs",true]]';
        $expected = '[' . $inner . ',' . $ext . ']';
        self::assertSame('[' . $inner . ',[]]', ArsenalLoadoutItems::aceExport($inner), 'tenue nue');
        foreach ([
            'avec infos' => '[' . $inner . ',' . $ext . ']',
            'emballée une fois de trop' => '[[' . $inner . ',' . $ext . ']]',
            'nom + tenue' => '["Kit A",[' . $inner . ',' . $ext . ']]',
            'liste d’une tenue' => '[["Kit A",[' . $inner . ',' . $ext . ']]]',
            'infos + version' => '[[' . $inner . ',' . $ext . '],2]',
            'doublée par callExtension' => str_replace('"', '""', '[' . $inner . ',' . $ext . ']'),
        ] as $label => $payload) {
            self::assertSame($expected, ArsenalLoadoutItems::aceExport($payload), $label);
        }

        // Emplacements remis au bon type : casque / lunettes en texte, toujours 6 objets assignés.
        $broken = '[[],[],[],[],[],[],["H_HelmetB"],null,[],["ItemMap"]]';
        $data = ArsenalLoadoutItems::decode((string) ArsenalLoadoutItems::aceExport($broken));
        self::assertIsArray($data);
        [$loadout, $extended] = $data;
        self::assertCount(10, $loadout);
        self::assertSame('', $loadout[6]);
        self::assertSame('', $loadout[7]);
        self::assertSame(['ItemMap', '', '', '', '', ''], $loadout[9]);
        self::assertSame([], $extended);
        // Tenue à 9 emplacements (ancienne) : complétée à 10.
        self::assertCount(10, ArsenalLoadoutItems::aceLoadout('[[],[],[],[],[],[],"","",[]]')[0] ?? []);
        self::assertNull(ArsenalLoadoutItems::aceExport('[[1,2,3]]'));
    }

    /**
     * Le mod compare payload_sha256 (sha256 du texte reçu de SyncWardrobe : « str loadout » aux guillemets doublés par
     * callExtension) avec le sha256 qu’il calcule sur la tenue locale. Une tenue recollée dans ACE depuis « Copier pour
     * ACE » redonne exactement le même texte, donc la même empreinte.
     */
    public function testCopiedTenueGivesBackTheTextTheModHashes(): void
    {
        $inner = '[["arifle_MX_F","","","",["30Rnd_65x39_caseless_mag",30],[],""],[],["hgun_P07_F","","","",[],[],""],["U_B_CombatUniform_mcam",[["FirstAidKit",2]]],["V_PlateCarrier1_rgr",[["30Rnd_65x39_caseless_mag",3,30]]],[],"H_HelmetB","",[],["ItemMap","","ItemRadio","ItemCompass","ItemWatch",""]]';
        $stored = str_replace('"', '""', $inner);
        $siteSha = hash('sha256', $stored);

        $copied = (string) ArsenalLoadoutItems::aceExport($stored);
        $reimported = ArsenalLoadoutItems::decode($copied)[0] ?? null;
        self::assertIsArray($reimported);
        $modText = str_replace('"', '""', ArsenalLoadoutItems::sqf($reimported));
        self::assertSame($stored, $modText);
        self::assertSame($siteSha, hash('sha256', $modText));
    }

    public function testPreviewPicksMainPieces(): void
    {
        $inner = '[["arifle_MX_F","","","",[],[],""],[],["hgun_P07_F","","","",[],[],""],["U_B_CombatUniform_mcam",[]],["V_PlateCarrier1_rgr",[]],["B_AssaultPack_rgr",[]],"H_HelmetB","",[],["","","","","",""]]';
        self::assertSame(
            ['arifle_MX_F', 'U_B_CombatUniform_mcam', 'V_PlateCarrier1_rgr', 'H_HelmetB', 'B_AssaultPack_rgr'],
            ArsenalLoadoutItems::previewClasses($inner),
        );
        $items = ArsenalLoadoutItems::previewItems(['arifle_MX_F'], ['arifle_mx_f' => ['name' => 'MX 6,5 mm']]);
        self::assertSame('MX 6,5 mm', $items[0]['name']);
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
