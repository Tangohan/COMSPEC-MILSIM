<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GameEquipment\Configurator\ConfiguratorCatalog;
use App\Services\GameEquipment\Configurator\ConfiguratorKinds;
use App\Services\GameEquipment\Configurator\EquipmentAudit;
use App\Services\GameEquipment\Configurator\ItemCatalogImport;
use App\Services\GameEquipment\Configurator\LoadoutDocument;
use App\Services\GameEquipment\Configurator\LoadoutPackBuilder;
use App\Services\GameEquipment\Configurator\LoadoutValidator;
use App\Services\GameEquipment\Configurator\SqfArrayText;
use App\Services\GameEquipment\RoleKitService;
use PHPUnit\Framework\TestCase;

/**
 * Configurateur d'équipement : lecture stricte d'une tenue (bornes, profondeur), règles (emplacement, compatibilités,
 * chargeurs, allowedSlots, charge maximale, masse), import du catalogue de configuration du jeu, catalogue compact,
 * copie profonde (aller-retour), bloc « loadouts » du paquet Athena, audit, droits et assets (JS, CSS, vue, routes).
 */
final class EquipmentConfiguratorTest extends TestCase
{
    private const MX = ['arifle_MX_F', 'muzzle_snds_H', 'acc_pointer_IR', 'optic_Aco', ['30Rnd_65x39_caseless_mag', 30], [], 'bipod_01_F_snd'];

    protected function tearDown(): void
    {
        EquipmentAudit::useSink(null);
    }

    /** @return list<mixed> */
    private static function loadout(array $over = []): array
    {
        $lo = [
            self::MX,
            [],
            ['hgun_P07_F', '', '', '', ['16Rnd_9x21_Mag', 16], [], ''],
            ['U_B_CombatUniform_mcam', [['FirstAidKit', 2]]],
            ['V_PlateCarrier1_rgr', [['30Rnd_65x39_caseless_mag', 4, 30]]],
            ['B_AssaultPack_mcamo', [[['hgun_P07_F', '', '', '', [], [], ''], 1], ['B_AssaultPack_rgr', true]]],
            'H_HelmetB',
            '',
            ['Binocular', '', '', '', [], [], ''],
            ['ItemMap', 'ItemGPS', 'ItemRadio', 'ItemCompass', 'ItemWatch', 'NVGoggles'],
        ];
        foreach ($over as $i => $v) {
            $lo[$i] = $v;
        }

        return $lo;
    }

    /** @return array<string, array<string, mixed>> */
    private static function catalog(): array
    {
        $specs = [
            ['arifle_MX_F', 'w', 'Weapon', 'AssaultRifle', 'MX 6,5 mm', 70, null, 1, null, [], ['muzzle' => ['muzzle_snds_H'], 'optic' => ['optic_Aco', 'optic_Hamr'], 'pointer' => ['acc_pointer_IR'], 'bipod' => ['bipod_01_F_snd']], ['30Rnd_65x39_caseless_mag']],
            ['hgun_P07_F', 'w', 'Weapon', 'Handgun', 'P07 9 mm', 20, null, 2, null, [], ['muzzle' => [], 'optic' => [], 'pointer' => [], 'bipod' => []], ['16Rnd_9x21_Mag']],
            ['launch_NLAW_F', 'w', 'Weapon', 'MissileLauncher', 'PCML', 100, null, 4, null, [901], ['muzzle' => [], 'optic' => [], 'pointer' => [], 'bipod' => []], ['NLAW_F']],
            ['muzzle_snds_H', 'w', 'Item', 'AccessoryMuzzle', 'Silencieux 6,5', 8, null, 131072, null, [], null, null],
            ['optic_Aco', 'w', 'Item', 'AccessorySights', 'ACO', 6, null, 131072, null, [], null, null],
            ['optic_Hamr', 'w', 'Item', 'AccessorySights', 'RCO', 8, null, 131072, null, [], null, null],
            ['optic_DMS', 'w', 'Item', 'AccessorySights', 'DMS', 14, null, 131072, null, [], null, null],
            ['acc_pointer_IR', 'w', 'Item', 'AccessoryPointer', 'Pointeur IR', 2, null, 131072, null, [], null, null],
            ['bipod_01_F_snd', 'w', 'Item', 'AccessoryBipod', 'Bipied', 4, null, 131072, null, [], null, null],
            ['30Rnd_65x39_caseless_mag', 'm', 'Magazine', 'Bullet', 'Chargeur 6,5 mm 30 cps', 12, null, null, 30, [], null, null],
            ['16Rnd_9x21_Mag', 'm', 'Magazine', 'Bullet', 'Chargeur 9 mm 16 cps', 5, null, null, 16, [], null, null],
            ['U_B_CombatUniform_mcam', 'w', 'Equipment', 'Uniform', 'Tenue de combat', 40, 30, 131072, null, [], null, null],
            ['V_PlateCarrier1_rgr', 'w', 'Equipment', 'Vest', 'Gilet porte-plaques', 80, 140, 131072, null, [], null, null],
            ['B_AssaultPack_mcamo', 'v', 'Equipment', 'Backpack', 'Sac d’assaut', 20, 160, null, null, [], null, null],
            ['B_AssaultPack_rgr', 'v', 'Equipment', 'Backpack', 'Sac d’assaut vert', 20, 160, null, null, [], null, null],
            ['H_HelmetB', 'w', 'Equipment', 'Headgear', 'Casque ECH', 40, null, 131072, null, [], null, null],
            ['Binocular', 'w', 'Item', 'Binocular', 'Jumelles', 10, null, 4096, null, [], null, null],
            ['FirstAidKit', 'w', 'Item', 'FirstAidKit', 'Trousse', 4, null, 131072, null, [801, 701, 901], null, null],
            ['ItemMap', 'w', 'Item', 'Map', 'Carte', 1, null, 131072, null, [], null, null],
            ['ItemGPS', 'w', 'Item', 'GPS', 'GPS', 1, null, 131072, null, [], null, null],
            ['ItemRadio', 'w', 'Item', 'Radio', 'Radio', 1, null, 131072, null, [], null, null],
            ['ItemCompass', 'w', 'Item', 'Compass', 'Boussole', 1, null, 131072, null, [], null, null],
            ['ItemWatch', 'w', 'Item', 'Watch', 'Montre', 1, null, 131072, null, [], null, null],
            ['NVGoggles', 'w', 'Item', 'NVGoggles', 'JVN', 3, null, 131072, null, [], null, null],
        ];
        $rows = [];
        foreach ($specs as $s) {
            $row = ItemCatalogImport::row([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6] ?? -1, $s[7] ?? -1, $s[8] ?? -1, $s[9],
                $s[10] === null ? [] : [$s[10]['muzzle'], $s[10]['optic'], $s[10]['pointer'], $s[10]['bipod']], $s[11] ?? [], 'Arma 3']);
            self::assertNotNull($row, $s[0]);
            $rows[] = [
                'class_name' => $row['class_name'], 'root' => $row['root'], 'type_main' => $row['type_main'], 'type_sub' => $row['type_sub'],
                'name' => $row['name'], 'mass' => $row['mass'], 'max_load' => $row['max_load'], 'slot_type' => $row['slot_type'],
                'ammo_count' => $row['ammo_count'], 'allowed_slots' => implode(',', $row['allowed_slots']),
                'compat_json' => $row['compat'] !== null ? json_encode($row['compat']) : null,
                'mags_json' => $row['mags'] !== null ? json_encode($row['mags']) : null, 'mod_name' => $row['mod_name'],
            ];
        }

        return ConfiguratorCatalog::merge($rows, [['class' => 'ACE_fieldDressing', 'name' => 'Bandage', 'kind' => 'medical', 'mod' => 'ACE', 'max_ammo' => null, 'players' => 9]]);
    }

    public function testNormalizesAFullLoadoutAndRoundTripsDeepCopy(): void
    {
        $doc = LoadoutDocument::normalize(self::loadout());
        self::assertTrue($doc['ok'], (string) ($doc['error'] ?? ''));
        $lo = $doc['loadout'];
        self::assertCount(10, $lo);
        self::assertSame([[['hgun_P07_F', '', '', '', [], [], ''], 1], ['B_AssaultPack_rgr', true]], $lo[5][1], 'arme rangée et sac rangé gardés');
        // Copie profonde : texte → tenue → texte, identique ; et la copie ne partage rien avec l'original.
        $again = LoadoutDocument::fromText(LoadoutDocument::toJson($lo));
        self::assertTrue($again['ok']);
        self::assertSame($lo, $again['loadout']);
        $copy = $again['loadout'];
        $copy[0][3] = 'optic_Hamr';
        self::assertSame('optic_Aco', $lo[0][3]);
        self::assertContains('hgun_P07_F', LoadoutDocument::classes($lo));
        self::assertContains('B_AssaultPack_rgr', LoadoutDocument::classes($lo));
        self::assertContains('NVGoggles', LoadoutDocument::classes($lo));
    }

    public function testAcceptsKnownWrappersAndRejectsMalformedPayloads(): void
    {
        $lo = self::loadout();
        $ace = json_encode([$lo, [['ace_arsenal_face', 'WhiteHead_01']]]);
        self::assertTrue(LoadoutDocument::fromText((string) $ace)['ok'], 'export ACE [tenue, infos étendues]');
        self::assertTrue(LoadoutDocument::fromText((string) json_encode(['format' => 'comspec-loadout', 'v' => 1, 'name' => 'X', 'loadout' => $lo]))['ok'], 'enveloppe du configurateur');
        self::assertTrue(LoadoutDocument::fromText('"' . str_replace('"', '""', (string) json_encode($lo)) . '"')['ok'], 'texte doublé par callExtension');

        self::assertFalse(LoadoutDocument::fromText('')['ok']);
        self::assertFalse(LoadoutDocument::fromText('<script>alert(1)</script>')['ok']);
        self::assertFalse(LoadoutDocument::normalize([1, 2, 3])['ok']);
        $bad = self::loadout([0 => ['arifle"MX', '', '', '', [], [], '']]);
        self::assertFalse(LoadoutDocument::normalize($bad)['ok'], 'classe avec guillemet refusée');
        $bad = self::loadout([4 => ['V_PlateCarrier1_rgr', [['FirstAidKit', 5000]]]]);
        self::assertFalse(LoadoutDocument::normalize($bad)['ok'], 'quantité bornée');
        $bad = self::loadout([5 => ['B_AssaultPack_mcamo', [[['hgun_P07_F', '', '', '', [], [], ''], 1, 3]]]]);
        self::assertFalse(LoadoutDocument::normalize($bad)['ok'], 'arme rangée à 3 champs refusée');
        $tooMany = array_fill(0, LoadoutDocument::MAX_LINES_PER_CONTAINER + 1, ['FirstAidKit', 1]);
        self::assertFalse(LoadoutDocument::normalize(self::loadout([5 => ['B_AssaultPack_mcamo', $tooMany]]))['ok'], 'lignes par conteneur bornées');
        self::assertFalse(LoadoutDocument::fromText(str_repeat(' ', LoadoutDocument::MAX_BYTES + 1) . '[]')['ok'], 'taille bornée');
        // Profondeur : un conteneur dans une ligne de conteneur n'existe pas dans le format.
        $deep = self::loadout([5 => ['B_AssaultPack_mcamo', [[['B_AssaultPack_rgr', [['FirstAidKit', 1]]], 1]]]]);
        self::assertFalse(LoadoutDocument::normalize($deep)['ok'], 'profondeur bornée par le format');
    }

    public function testValidatorAcceptsAValidOutfitWithMassAndLoads(): void
    {
        $lo = LoadoutDocument::normalize(self::loadout())['loadout'];
        $r = LoadoutValidator::validate($lo, self::catalog());
        self::assertTrue($r['ok'], implode(' / ', $r['errors']));
        self::assertSame([], $r['unknown']);
        self::assertSame(8.0, $r['loads']['uniform']['load']);
        self::assertSame(30, $r['loads']['uniform']['max']);
        self::assertSame(48.0, $r['loads']['vest']['load']);
        self::assertSame(40.0, $r['loads']['backpack']['load'], 'arme rangée (20) + sac rangé (20)');
        // MX monté 70+8+2+6+4+12, P07 + chargeur 25, jumelles 10, uniforme 40+8, gilet 80+48, sac 20+40, casque 40, assignés 1×5 + JVN 3.
        self::assertSame(102.0 + 25 + 10 + 48 + 128 + 60 + 40 + 8, $r['mass']);
    }

    public function testValidatorRejectsIncompatibleAccessoriesMagazinesSlotsAndOverload(): void
    {
        $cat = self::catalog();
        $lo = LoadoutDocument::normalize(self::loadout([0 => ['arifle_MX_F', '', '', 'optic_DMS', ['16Rnd_9x21_Mag', 16], [], '']]))['loadout'];
        $r = LoadoutValidator::validate($lo, $cat);
        self::assertFalse($r['ok']);
        self::assertStringContainsString('ne se monte pas', implode(' ', $r['errors']));
        self::assertStringContainsString('chargeur incompatible', implode(' ', $r['errors']));

        $lo = LoadoutDocument::normalize(self::loadout([0 => ['hgun_P07_F', '', '', '', [], [], '']]))['loadout'];
        self::assertStringContainsString('ne va pas à l’emplacement', implode(' ', LoadoutValidator::validate($lo, $cat)['errors']), 'pistolet en arme principale');

        $lo = LoadoutDocument::normalize(self::loadout([3 => ['U_B_CombatUniform_mcam', [['launch_NLAW_F', 1]]]]))['loadout'];
        self::assertStringContainsString('ne se range pas', implode(' ', LoadoutValidator::validate($lo, $cat)['errors']), 'lanceur refusé hors du sac (allowedSlots)');

        $lo = LoadoutDocument::normalize(self::loadout([4 => ['V_PlateCarrier1_rgr', [['30Rnd_65x39_caseless_mag', 12, 30]]]]))['loadout'];
        $r = LoadoutValidator::validate($lo, $cat);
        self::assertStringContainsString('déborde', implode(' ', $r['errors']), '12 × 12 = 144 > 140');

        $lo = LoadoutDocument::normalize(self::loadout([4 => ['V_PlateCarrier1_rgr', [['30Rnd_65x39_caseless_mag', 1, 45]]]]))['loadout'];
        self::assertStringContainsString('45 munitions', implode(' ', LoadoutValidator::validate($lo, $cat)['errors']));
    }

    public function testUnknownClassesAreErrorsWhenSavingAndWarningsWhenAnalysing(): void
    {
        $lo = LoadoutDocument::normalize(self::loadout([6 => 'H_Mod_Inconnu']))['loadout'];
        $strict = LoadoutValidator::validate($lo, self::catalog(), true);
        self::assertFalse($strict['ok']);
        self::assertSame(['H_Mod_Inconnu'], $strict['unknown']);
        $soft = LoadoutValidator::validate($lo, self::catalog(), false);
        self::assertTrue($soft['ok']);
        self::assertNotSame([], $soft['warnings']);
        self::assertNull($soft['mass'], 'masse totale inconnue : jamais inventée');
    }

    public function testObservedOnlyItemsAreUncheckedNotInvented(): void
    {
        // Sans configuration importée : objets vus en jeu seulement, compatibilités et charge non vérifiées.
        $observed = [
            ['class' => 'arifle_MX_F', 'name' => 'MX', 'kind' => 'primary', 'mod' => 'Arma 3', 'max_ammo' => null],
            ['class' => 'optic_DMS', 'name' => 'DMS', 'kind' => 'optic', 'mod' => 'Arma 3', 'max_ammo' => null],
            ['class' => 'V_PlateCarrier1_rgr', 'name' => 'Gilet', 'kind' => 'vest', 'mod' => 'Arma 3', 'max_ammo' => null],
            ['class' => '30Rnd_65x39_caseless_mag', 'name' => 'Chargeur', 'kind' => 'magazine', 'mod' => 'Arma 3', 'max_ammo' => 30],
        ];
        $cat = ConfiguratorCatalog::merge([], $observed);
        self::assertNull($cat['arifle_mx_f']['mass']);
        self::assertNull($cat['arifle_mx_f']['compat']);
        $lo = LoadoutDocument::normalize([['arifle_MX_F', '', '', 'optic_DMS', [], [], ''], [], [], [], ['V_PlateCarrier1_rgr', [['30Rnd_65x39_caseless_mag', 50, 30]]], [], '', '', [], ['', '', '', '', '', '']])['loadout'];
        $r = LoadoutValidator::validate($lo, $cat);
        self::assertTrue($r['ok'], implode(' / ', $r['errors']));
        self::assertNull($r['mass']);
        self::assertNull($r['loads']['vest']['load']);
        self::assertStringContainsString('non vérifiées', implode(' ', $r['warnings']));
    }

    public function testCatalogImportReadsSqfAndJsonAndBoundsEverything(): void
    {
        $item = ['arifle_MX_F', 'w', 'Weapon', 'AssaultRifle', 'MX "6,5"', 70, -1, 1, -1, [], [['muzzle_snds_H'], ['optic_Aco'], [], ['bipod_01_F_snd']], ['30Rnd_65x39_caseless_mag'], 'Arma 3'];
        $root = ['comspec-equipment-catalog', 1, 'Arma 3 2.18', [['', 'Arma 3']], [$item, ['bad class', 'w'], 'junk']];
        $json = ItemCatalogImport::parse((string) json_encode($root));
        self::assertTrue($json['ok']);
        self::assertCount(1, $json['rows']);
        self::assertSame(2, $json['skipped']);
        self::assertSame(['muzzle_snds_H'], $json['rows'][0]['compat']['muzzle']);
        self::assertNull($json['rows'][0]['max_load']);
        // Texte SQF de `str` : guillemets doublés dans les chaînes.
        $sqf = '["comspec-equipment-catalog",1,"Arma 3",[],[["arifle_MX_F","w","Weapon","AssaultRifle","MX ""6,5""",70,-1,1,-1,[],[[],["optic_Aco"],[],[]],["30Rnd_65x39_caseless_mag"],"Arma 3"]]]';
        $parsed = ItemCatalogImport::parse($sqf);
        self::assertTrue($parsed['ok']);
        self::assertSame('MX "6,5"', $parsed['rows'][0]['name']);
        self::assertFalse(ItemCatalogImport::parse('["autre-chose",1]')['ok']);
        self::assertFalse(ItemCatalogImport::parse('["comspec-equipment-catalog",2,"",[],[]]')['ok'], 'version inconnue');
        $row = ItemCatalogImport::row(['V_X', 'w', 'Equipment<script>', 'Vest', "Nom\x07", -5, 'x', null, null, [801, 'z', 701], [], [], '']);
        self::assertSame('', $row['type_main'], 'type hors lettres écarté');
        self::assertNull($row['mass']);
        self::assertSame([801, 701], $row['allowed_slots']);
        self::assertSame('Nom', $row['name']);
        self::assertNull(SqfArrayText::decode('[1,2] trailing', 1000));
        self::assertSame([[1, true, null]], SqfArrayText::decode('[[1,true,nil]]', 1000));
    }

    public function testKindsAndCompactCatalogDeduplicateCompatibilityGroups(): void
    {
        self::assertSame(['kind' => 'primary', 'cat' => 'mitrailleuse'], ConfiguratorKinds::fromSpec('Weapon', 'MachineGun', 1));
        self::assertSame(['kind' => 'handgun', 'cat' => 'pistolet'], ConfiguratorKinds::fromSpec('Weapon', 'Handgun', 2));
        self::assertSame(['kind' => 'secondary', 'cat' => 'lanceur'], ConfiguratorKinds::fromSpec('Weapon', 'MissileLauncher', 4));
        self::assertSame('optic', ConfiguratorKinds::fromSpec('Item', 'AccessorySights', 131072)['kind']);
        self::assertSame('goggles', ConfiguratorKinds::fromSpec('Equipment', 'Glasses', null)['kind']);
        self::assertSame(['kind' => 'magazine', 'cat' => 'grenades'], ConfiguratorKinds::fromSpec('Magazine', 'SmokeShell', null));

        $cat = self::catalog();
        $cat['arifle_mx_f_copy'] = $cat['arifle_mx_f'];
        $cat['arifle_mx_f_copy']['class'] = 'arifle_MX_F_copy';
        $c = ConfiguratorCatalog::compact($cat, ['optic_aco' => 'abcdef0123456789']);
        self::assertSame(count($cat), $c['stats']['items']);
        $by = [];
        foreach ($c['items'] as $it) {
            $by[$it['c']] = $it;
        }
        self::assertSame($by['arifle_MX_F']['x'], $by['arifle_MX_F_copy']['x'], 'même liste = même groupe');
        self::assertSame(['optic_aco', 'optic_hamr'], $c['groups'][$by['arifle_MX_F']['x']['op']]);
        self::assertSame('abcdef0123456789', $by['optic_Aco']['i']);
        self::assertArrayNotHasKey('m', $by['ACE_fieldDressing'], 'masse absente : clé absente');
        self::assertArrayNotHasKey('v', $by['ACE_fieldDressing']);
        self::assertSame(1, $by['H_HelmetB']['v']);
    }

    public function testDefaultKitAndAudienceValues(): void
    {
        self::assertSame('member:4', RoleKitService::audienceValue(['member_user_id' => 4, 'unit_id' => 2]));
        self::assertSame('qualification:3', RoleKitService::audienceValue(['qualification_id' => 3]));
        self::assertSame('role:7', RoleKitService::audienceValue(['job_role_id' => 7]));
        self::assertSame('all', RoleKitService::audienceValue([]));
        $all = ['id' => 1];
        $role = ['id' => 2, 'job_role_id' => 7];
        $mine = ['id' => 3, 'member_user_id' => 9];
        self::assertSame(3, RoleKitService::defaultKitId([$mine, $role, $all]), 'kit personnel d’abord');
        self::assertSame(2, RoleKitService::defaultKitId([$role, $all]), 'seul kit de rôle');
        self::assertSame(0, RoleKitService::defaultKitId([$role, ['id' => 5, 'job_role_id' => 8], $all]), 'ambigu : le joueur choisit');
        self::assertSame(1, RoleKitService::defaultKitId([$all]));
    }

    public function testPackBlockOnlyCarriesOfferedValidKitsAsText(): void
    {
        $good = LoadoutDocument::toJson(LoadoutDocument::normalize(self::loadout())['loadout']);
        $kits = [
            ['id' => 1, 'name' => 'Fusilier', 'payload_text' => $good],
            ['id' => 2, 'name' => 'Cassé', 'payload_text' => 'pas une tenue'],
            ['id' => 3, 'name' => 'Médecin', 'payload_text' => $good],
        ];
        $block = LoadoutPackBuilder::assemble($kits, [
            '76561198000000001' => [['id' => 1], ['id' => 2]],
            '76561198000000002' => [['id' => 3, 'member_user_id' => 5], ['id' => 1]],
            '76561198000000003' => [],
        ]);
        self::assertNotNull($block);
        self::assertSame([1, 3], array_column($block['kits'], 'id'));
        self::assertIsString($block['kits'][0]['loadout']);
        self::assertStringStartsWith('[', $block['kits'][0]['loadout']);
        self::assertStringNotContainsString('{', $block['kits'][0]['loadout'], 'tableaux seulement (parseSimpleArray)');
        self::assertSame('76561198000000001', $block['players'][0]['uid']);
        self::assertSame([1], $block['players'][0]['kits']);
        self::assertSame(1, $block['players'][0]['default']);
        self::assertSame(3, $block['players'][1]['default']);
        self::assertCount(2, $block['players'], 'joueur sans kit absent');
        self::assertNull(LoadoutPackBuilder::assemble([['id' => 2, 'name' => 'x', 'payload_text' => '']], []));
    }

    public function testAuditUsesTheBackOfficeJournalWithEntityTypes(): void
    {
        $seen = [];
        EquipmentAudit::useSink(static function (string $a, int $t, int $u, string $e, int $id, array $d) use (&$seen): void {
            $seen[] = [$a, $t, $u, $e, $id];
        });
        EquipmentAudit::log('equipment.kit.save', 3, 7, 12, ['name' => 'X']);
        EquipmentAudit::log('equipment.catalog.import', 3, 7, 0, []);
        EquipmentAudit::log('equipment.wardrobe.save', 3, 7, 40, []);
        self::assertSame([
            ['equipment.kit.save', 3, 7, 'game_role_kit', 12],
            ['equipment.catalog.import', 3, 7, 'game_item_specs', 0],
            ['equipment.wardrobe.save', 3, 7, 'arsenal_wardrobe', 40],
        ], $seen);
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/GameEquipment/Configurator/EquipmentAudit.php');
        self::assertStringContainsString('AuditService', $src);
    }

    public function testControllerChecksCsrfRightsAndTenantOnEveryWrite(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/EquipmentConfiguratorController.php');
        foreach (['analyse', 'save', 'restore', 'importCatalog'] as $method) {
            $body = self::methodBody($src, $method);
            self::assertStringContainsString('Csrf::validate', $body, $method . ' : CSRF');
            self::assertStringContainsString('$this->gate(true)', $body, $method . ' : session et formule');
        }
        self::assertStringContainsString('canManageKits()', self::methodBody($src, 'save'));
        self::assertStringContainsString('KitsAccess::allows', self::methodBody($src, 'save'));
        self::assertStringContainsString('canManageKits()', self::methodBody($src, 'restore'));
        self::assertStringContainsString('canManageCatalog()', self::methodBody($src, 'importCatalog'));
        self::assertStringContainsString('LoadoutValidator::validate', $src);
        self::assertStringContainsString("Session::get('tenant_id')", $src, 'communauté lue en session, jamais dans la requête');
        self::assertStringNotContainsString("input('tenant", $src);
        self::assertStringContainsString('ArsenalWardrobeRepository::canView', self::methodBody($src, 'resolveSource'), 'tenue d’un autre : seulement si visible');
        self::assertStringContainsString('EquipmentAudit::log', self::methodBody($src, 'save'));
        self::assertStringContainsString('EquipmentAudit::log', self::methodBody($src, 'importCatalog'));
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach (['/equipment/configurateur', '/equipment/configurateur/enregistrer', '/equipment/configurateur/catalogue/import', '/equipment/configurateur/kits/{id}/versions/{version}/restaurer'] as $r) {
            self::assertStringContainsString("'" . $r . "'", $routes);
        }
        self::assertLessThan(strpos($routes, "'/equipment/{slug}'"), strpos($routes, "'/equipment/configurateur'"), 'avant la route générique');
    }

    public function testMigrationIsRegisteredRerunnableWithUnsignedTenant(): void
    {
        $root = dirname(__DIR__, 2);
        $step = (string) file_get_contents($root . '/database/steps/4270_equipment_configurator.php');
        self::assertStringContainsString('tenant_id INT UNSIGNED NOT NULL', $step);
        self::assertStringContainsString("addColumnIfMissing('game_role_kits', 'member_user_id', 'INT UNSIGNED", $step);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS game_item_specs', $step);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS game_role_kit_versions', $step);
        $registry = require $root . '/database/steps/registry.php';
        $found = array_values(array_filter($registry, static fn (array $s): bool => ($s['file'] ?? '') === '4270_equipment_configurator.php'));
        self::assertCount(1, $found);
        self::assertTrue($found[0]['rerun']);
    }

    public function testScriptsAndStylesFollowTheToolContract(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/equipment-configurator.js');
        foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel', 'e.ctrlKey || e.altKey || e.metaKey', 'data-grip', 'undoStack', 'redoStack',
            "e.key === 'Delete'", "e.key === 'Enter'", 'data-menu-copy', 'data-menu-drop', 'requestAnimationFrame', 'containerFits', 'attachFits',
            'kindFits', 'translate="no"', 'esc(', 'urls.save', 'boot.csrf', 'localStorage'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        self::assertDoesNotMatchRegularExpression('/innerHTML\s*=\s*[^;]*\.(n|name)\b(?![^;]*esc\()/', $js, 'nom du jeu inséré sans échappement');
        self::assertStringNotContainsString('eval(', $js);
        self::assertDoesNotMatchRegularExpression('#https?://#', $js, 'aucune ressource externe');
        $css = (string) file_get_contents($root . '/public/assets/css/equipment-configurator.css');
        self::assertStringContainsString('prefers-color-scheme: dark', $css);
        self::assertStringContainsString('[data-cfg-theme="dark"]', $css);
        self::assertStringContainsString('data-bo-theme="dark"', $css);
        self::assertStringContainsString('@media (max-width: 560px)', $css);
        self::assertStringContainsString('touch-action: none', $css);
        self::assertStringContainsString('prefers-reduced-motion', $css);
        self::assertDoesNotMatchRegularExpression('#url\(\s*["\']?https?://#', $css);
        $view = (string) file_get_contents($root . '/views/equipment/configurator.php');
        self::assertStringContainsString('Comment ça marche', $view);
        self::assertStringContainsString('Exemple', $view);
        self::assertStringContainsString('Raccourcis', $view);
        self::assertStringContainsString('JSON_HEX_TAG', $view);
        self::assertStringContainsString('equipment-configurator.js', $view);
        $hub = (string) file_get_contents($root . '/views/equipment/hub.php');
        self::assertStringContainsString("url('equipment/configurateur')", $hub, 'onglet et bouton depuis la page Équipement');
    }

    private static function methodBody(string $src, string $method): string
    {
        $start = strpos($src, 'function ' . $method . '(');
        self::assertNotFalse($start, $method);
        $next = strpos($src, "\n    public function ", $start + 10);
        $nextPrivate = strpos($src, "\n    private function ", $start + 10);
        $end = min($next === false ? strlen($src) : $next, $nextPrivate === false ? strlen($src) : $nextPrivate);

        return substr($src, $start, $end - $start);
    }
}
