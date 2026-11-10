<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\EquipmentConfiguratorSocialRepository;
use App\Services\GameEquipment\Configurator\ConfiguratorCatalog;
use App\Services\GameEquipment\Configurator\ConfiguratorKinds;
use App\Services\GameEquipment\Configurator\EquipmentAudit;
use App\Services\GameEquipment\Configurator\EquipmentTrends;
use App\Services\GameEquipment\Configurator\LoadoutValidator;
use App\Services\GameEquipment\Configurator\PrekitDocument;
use PHPUnit\Framework\TestCase;

/**
 * Configurateur d'équipement, suite : compatibilité stricte (chargeurs orphelins, armes remplacées), poids et capacité
 * des contenants, pré-kits (contenu, plan d'insertion, insertion partielle), favoris d'objets et tendances limitées à
 * la communauté (aucune donnée personnelle, seuil de membres), droits et assets.
 */
final class EquipmentConfiguratorSocialTest extends TestCase
{
    protected function tearDown(): void
    {
        EquipmentAudit::useSink(null);
    }

    /**
     * Catalogue de test au format ConfiguratorCatalog::merge (masses en unités Arma).
     *
     * @return array<string, array<string, mixed>>
     */
    private static function catalog(): array
    {
        $rows = [];
        $add = static function (string $class, string $main, string $sub, ?float $mass, ?int $load = null, array $slots = [], ?array $mags = null, ?int $ammo = null) use (&$rows): void {
            $rows[] = [
                'class_name' => $class, 'root' => 'w', 'type_main' => $main, 'type_sub' => $sub, 'name' => $class, 'mass' => $mass,
                'max_load' => $load, 'slot_type' => null, 'ammo_count' => $ammo, 'allowed_slots' => implode(',', $slots),
                'compat_json' => $mags !== null ? json_encode(['muzzle' => [], 'optic' => [], 'pointer' => [], 'bipod' => []]) : null,
                'mags_json' => $mags !== null ? json_encode($mags) : null, 'mod_name' => 'Arma 3',
            ];
        };
        $add('arifle_MX_F', 'Weapon', 'AssaultRifle', 70, null, [], ['30Rnd_65x39_caseless_mag']);
        $add('hgun_P07_F', 'Weapon', 'Handgun', 20, null, [], ['16Rnd_9x21_Mag']);
        $add('30Rnd_65x39_caseless_mag', 'Magazine', 'Bullet', 12, null, [], null, 30);
        $add('16Rnd_9x21_Mag', 'Magazine', 'Bullet', 5, null, [], null, 16);
        $add('30Rnd_556x45_Stanag', 'Magazine', 'Bullet', 10, null, [], null, 30);
        $add('HandGrenade', 'Magazine', 'Grenade', 10, null, [], null, 1);
        $add('U_B_CombatUniform_mcam', 'Equipment', 'Uniform', 40, 30);
        $add('V_PlateCarrier1_rgr', 'Equipment', 'Vest', 80, 50);
        $add('B_AssaultPack_mcamo', 'Equipment', 'Backpack', 20, null);
        $add('FirstAidKit', 'Item', 'FirstAidKit', 4, null, [801, 701, 901]);
        $add('ItemMap', 'Item', 'Map', 1);
        $add('ItemRadio', 'Item', 'Radio', 1);
        $add('Uniform_Only_Item', 'Item', 'FirstAidKit', 2, null, [801]);
        $rows[] = ['class_name' => 'Heavy_Item', 'root' => 'w', 'type_main' => 'Item', 'type_sub' => 'Toolkit', 'name' => 'Lourd', 'mass' => null,
            'max_load' => null, 'slot_type' => null, 'ammo_count' => null, 'allowed_slots' => '', 'compat_json' => null, 'mags_json' => null, 'mod_name' => ''];

        return ConfiguratorCatalog::merge($rows, [['class' => 'ACE_tourniquet', 'name' => 'Garrot', 'kind' => 'medical', 'mod' => 'ACE', 'max_ammo' => null, 'players' => 4]]);
    }

    // ------------------------------------------------------------------ pré-kits : contenu

    public function testPrekitNormalizeMergesDuplicatesAndBoundsEverything(): void
    {
        $ok = PrekitDocument::normalize('[["FirstAidKit",2],["firstaidkit",3],["ItemMap","1"]]');
        self::assertTrue($ok['ok']);
        self::assertSame([['FirstAidKit', 5], ['ItemMap', 1]], $ok['items']);

        self::assertFalse(PrekitDocument::normalize('[]')['ok'], 'vide');
        self::assertFalse(PrekitDocument::normalize('nope')['ok'], 'illisible');
        self::assertFalse(PrekitDocument::normalize('[["bad class!",1]]')['ok'], 'classe invalide');
        self::assertFalse(PrekitDocument::normalize('[["FirstAidKit",0]]')['ok'], 'nombre nul');
        self::assertFalse(PrekitDocument::normalize('[["FirstAidKit",100]]')['ok'], 'nombre trop grand');
        self::assertFalse(PrekitDocument::normalize('{"a":["FirstAidKit",1]}')['ok'], 'pas une liste');
        $many = [];
        for ($i = 0; $i < PrekitDocument::MAX_LINES + 1; $i++) {
            $many[] = ['Item_' . $i, 1];
        }
        self::assertFalse(PrekitDocument::normalize($many)['ok'], 'trop de lignes');
    }

    public function testPrekitRefusesWeaponsBagsAndUnknownItems(): void
    {
        $cat = self::catalog();
        $problems = PrekitDocument::problems([['FirstAidKit', 1], ['arifle_MX_F', 1], ['B_AssaultPack_mcamo', 1], ['Nope_Item', 1]], $cat);
        self::assertCount(3, $problems);
        self::assertStringContainsString('ne va pas dans un pré-kit', $problems[0]);
        self::assertStringContainsString('absent du catalogue', $problems[2]);
        self::assertSame([], PrekitDocument::problems([['FirstAidKit', 2], ['ACE_tourniquet', 2]], $cat));
    }

    // ------------------------------------------------------------------ poids et capacité

    public function testPrekitMassAndFullInsertion(): void
    {
        $cat = self::catalog();
        $items = [['FirstAidKit', 2], ['ItemMap', 1], ['ItemRadio', 1]];
        self::assertSame(10.0, PrekitDocument::mass($items, $cat));
        self::assertNull(PrekitDocument::mass([['Heavy_Item', 1]], $cat), 'masse inconnue : null, jamais inventée');

        $plan = PrekitDocument::plan($items, 'vest', 'V_PlateCarrier1_rgr', 20.0, $cat);
        self::assertTrue($plan['ok']);
        self::assertTrue($plan['complete']);
        self::assertFalse($plan['unchecked']);
        self::assertSame(30.0, $plan['load']);
        self::assertSame(50, $plan['max']);
        self::assertSame([['FirstAidKit', 2], ['ItemMap', 1], ['ItemRadio', 1]], $plan['added']);
    }

    public function testPrekitPartialInsertionReportsWhatDoesNotFit(): void
    {
        $cat = self::catalog();
        // Uniforme : 30 de charge, déjà 22 → place pour 2 trousses (4 chacune), pas pour la 3e ni la carte qui suit ?
        $plan = PrekitDocument::plan([['FirstAidKit', 3], ['ItemMap', 1]], 'uniform', 'U_B_CombatUniform_mcam', 22.0, $cat);
        self::assertTrue($plan['ok']);
        self::assertFalse($plan['complete']);
        self::assertSame([['FirstAidKit', 2]], $plan['added']);
        self::assertSame('FirstAidKit', $plan['skipped'][0]['class']);
        self::assertSame(1, $plan['skipped'][0]['count']);
        self::assertStringContainsString('plus de place', $plan['skipped'][0]['reason']);
        self::assertSame('ItemMap', $plan['skipped'][1]['class'], 'la carte (1) ne tient plus : 30 / 30');
        self::assertSame(30.0, $plan['load']);
    }

    public function testPrekitRespectsAllowedSlotsAndMissingContainer(): void
    {
        $cat = self::catalog();
        $plan = PrekitDocument::plan([['Uniform_Only_Item', 1], ['FirstAidKit', 1]], 'vest', 'V_PlateCarrier1_rgr', 0.0, $cat);
        self::assertSame([['FirstAidKit', 1]], $plan['added']);
        self::assertStringContainsString('le jeu le refuse', $plan['skipped'][0]['reason']);

        $none = PrekitDocument::plan([['FirstAidKit', 1]], 'backpack', '', 0.0, $cat);
        self::assertFalse($none['ok']);
        self::assertStringContainsString('Posez d’abord un sac', (string) $none['error']);
        self::assertFalse(PrekitDocument::plan([['FirstAidKit', 1]], 'poche', 'X', 0.0, $cat)['ok']);
    }

    public function testUnknownCapacityOrMassInsertsButIsFlaggedUnchecked(): void
    {
        $cat = self::catalog();
        $bag = PrekitDocument::plan([['FirstAidKit', 50]], 'backpack', 'B_AssaultPack_mcamo', 0.0, $cat);
        self::assertTrue($bag['complete']);
        self::assertTrue($bag['unchecked'], 'capacité du sac inconnue');
        $heavy = PrekitDocument::plan([['Heavy_Item', 1], ['FirstAidKit', 1]], 'vest', 'V_PlateCarrier1_rgr', 0.0, $cat);
        self::assertTrue($heavy['unchecked']);
        self::assertNull($heavy['load']);
    }

    public function testValidatorContainerLoadAndMassStayInArmaUnits(): void
    {
        $cat = self::catalog();
        $lo = [[], [], [], ['U_B_CombatUniform_mcam', [['FirstAidKit', 8]]], [], [], '', '', [], ['', '', '', '', '', '']];
        $r = LoadoutValidator::validate($lo, $cat, true);
        self::assertFalse($r['ok'], '8 trousses × 4 = 32 pour 30');
        self::assertSame(['load' => 32.0, 'max' => 30], $r['loads']['uniform']);
        self::assertSame(72.0, $r['mass']);
    }

    // ------------------------------------------------------------------ compatibilité

    public function testOrphanMagazinesAreSignalledNotBlocking(): void
    {
        $cat = self::catalog();
        $mx = ['arifle_MX_F', '', '', '', ['30Rnd_65x39_caseless_mag', 30], [], ''];
        $vest = ['V_PlateCarrier1_rgr', [['30Rnd_556x45_Stanag', 2, 30], ['30Rnd_65x39_caseless_mag', 1, 30], ['HandGrenade', 1, 1]]];
        $lo = [$mx, [], [], [], $vest, [], '', '', [], ['', '', '', '', '', '']];
        self::assertSame(['30Rnd_556x45_Stanag'], LoadoutValidator::orphanMagazines($lo, $cat), 'grenade à main : jamais signalée');
        $r = LoadoutValidator::validate($lo, $cat, true);
        self::assertTrue($r['ok'], 'un avertissement, pas une erreur');
        self::assertNotEmpty(array_filter($r['warnings'], static fn (string $w): bool => str_contains($w, 'ne va dans aucune arme')));

        // Arme rangée dans le sac : ses chargeurs ne sont plus orphelins.
        $withPistol = $lo;
        $withPistol[4] = ['V_PlateCarrier1_rgr', [['16Rnd_9x21_Mag', 2, 16], [['hgun_P07_F', '', '', '', [], [], ''], 1]]];
        self::assertSame([], LoadoutValidator::orphanMagazines($withPistol, $cat));

        // Arme sans liste de chargeurs connue : on ne peut pas savoir, rien n'est signalé.
        $unknown = $cat;
        $unknown['arifle_mx_f']['mags'] = null;
        self::assertSame([], LoadoutValidator::orphanMagazines($lo, $unknown));
    }

    public function testSideIsReadOnlyFromTheVanillaUniformConvention(): void
    {
        self::assertSame('blufor', ConfiguratorKinds::sideFromUniform('U_B_CombatUniform_mcam'));
        self::assertSame('opfor', ConfiguratorKinds::sideFromUniform('U_O_CombatUniform_ocamo'));
        self::assertSame('indep', ConfiguratorKinds::sideFromUniform('u_i_g_resistanceleader_f'));
        self::assertSame('civil', ConfiguratorKinds::sideFromUniform('U_C_Poloshirt_blue'));
        self::assertSame('', ConfiguratorKinds::sideFromUniform('rhs_uniform_cu_ocp'), 'mods : inconnu, pas deviné');
    }

    // ------------------------------------------------------------------ tendances

    public function testTrendsCountDistinctMembersOnlyInsideTheTenant(): void
    {
        $lo = static fn (string $weapon): string => json_encode([[$weapon, '', '', '', [], [], ''], [], [], [], [], [], 'H_HelmetB', '', [], ['', '', '', '', '', '']]);
        $rows = [
            ['tenant_id' => 7, 'user_id' => 1, 'payload_text' => $lo('arifle_MX_F')],
            ['tenant_id' => 7, 'user_id' => 1, 'payload_text' => $lo('arifle_MX_F')],
            ['tenant_id' => 7, 'user_id' => 2, 'payload_text' => $lo('arifle_MX_F')],
            ['tenant_id' => 7, 'user_id' => 3, 'payload_text' => $lo('arifle_Katiba_F')],
            ['tenant_id' => 8, 'user_id' => 9, 'payload_text' => $lo('arifle_Katiba_F')],
            ['tenant_id' => 8, 'user_id' => 10, 'payload_text' => $lo('arifle_Katiba_F')],
            ['tenant_id' => 7, 'user_id' => 4, 'payload_text' => 'illisible'],
        ];
        $top = EquipmentTrends::fromLoadouts($rows, 7);
        self::assertSame([['class' => 'H_HelmetB', 'members' => 3], ['class' => 'arifle_MX_F', 'members' => 2]], $top, 'Katiba : 1 membre ici, les 2 autres sont d’une autre communauté');
        self::assertSame([], EquipmentTrends::fromLoadouts($rows, 0));

        $agg = EquipmentTrends::fromAggregates([
            ['tenant_id' => 7, 'class_name' => 'FirstAidKit', 'members' => 5],
            ['tenant_id' => 8, 'class_name' => 'ItemGPS', 'members' => 50],
            ['tenant_id' => 7, 'class_name' => 'bad class', 'members' => 9],
            ['tenant_id' => 7, 'class_name' => 'ItemMap', 'members' => 1],
        ], 7);
        self::assertSame([['class' => 'FirstAidKit', 'members' => 5]], $agg);

        $w = EquipmentTrends::wardrobes([
            ['tenant_id' => 7, 'id' => 3, 'name' => 'Fusilier', 'visibility' => 'tenant', 'members' => 4],
            ['tenant_id' => 7, 'id' => 4, 'name' => 'Privée', 'visibility' => 'personal', 'members' => 9],
            ['tenant_id' => 9, 'id' => 5, 'name' => 'Ailleurs', 'visibility' => 'tenant', 'members' => 9],
        ], 7);
        self::assertSame([['ref' => 'w:3', 'name' => 'Fusilier', 'members' => 4]], $w);
        self::assertArrayNotHasKey('user_id', $w[0], 'aucun propriétaire exposé');

        $by = EquipmentTrends::byClass([['class' => 'ItemMap', 'members' => 3]], [['class' => 'ItemMap', 'members' => 2]], []);
        self::assertSame(['itemmap' => ['w' => 3, 't' => 2]], $by);
    }

    public function testRepositoryScopesEveryQueryToTheTenant(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Repositories/EquipmentConfiguratorSocialRepository.php');
        preg_match_all('/(?:FROM|INTO|UPDATE) (?:game_item_favorites|game_item_prekits|game_item_usage|arsenal_wardrobes|arsenal_wardrobe_favorites)\b/', $src, $m, PREG_OFFSET_CAPTURE);
        self::assertGreaterThan(8, count($m[0]));
        foreach ($m[0] as [$hit, $offset]) {
            $sql = substr($src, $offset, 420);
            $sql = substr($sql, 0, (int) (strpos($sql, ';') ?: strlen($sql)));
            self::assertStringContainsString('tenant_id', $sql, 'requête sans communauté : ' . $hit);
        }
        self::assertSame(['days' => 30, 'worn' => [], 'added' => [], 'favored' => [], 'wardrobes' => []], (new EquipmentConfiguratorSocialRepository())->trends(0));
        self::assertStringContainsString("visibility <> 'personal'", $src, 'tendances de tenues : seulement les tenues de la communauté');
        self::assertStringContainsString('HAVING COUNT(DISTINCT', $src, 'seuil de membres distincts');
    }

    // ------------------------------------------------------------------ droits, routes, migration, assets

    public function testControllerChecksCsrfGateAndRightsOnNewWrites(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/EquipmentConfiguratorController.php');
        foreach (['favorite', 'savePrekit', 'deletePrekit'] as $method) {
            $body = self::methodBody($src, $method);
            self::assertStringContainsString('Csrf::validate', $body, $method);
            self::assertStringContainsString('$this->gate(true)', $body, $method);
        }
        self::assertStringContainsString('$this->gate(true)', self::methodBody($src, 'memberData'));
        $save = self::methodBody($src, 'savePrekit');
        self::assertStringContainsString('$shared && !$manager', $save, 'partage : responsables des kits seulement');
        self::assertStringContainsString('PrekitDocument::normalize', $save);
        self::assertStringContainsString('PrekitDocument::problems', $save);
        self::assertStringContainsString("\$existing['user_id'] !== \$userId", $save, 'modifier : son pré-kit ou un pré-kit partagé si responsable');
        self::assertStringContainsString("\$existing['user_id'] !== \$userId", self::methodBody($src, 'deletePrekit'));
        self::assertStringContainsString('EquipmentAudit::log', $save);
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach (['/equipment/configurateur/membre', '/equipment/configurateur/favori', '/equipment/configurateur/pre-kits', '/equipment/configurateur/pre-kits/{id}/supprimer'] as $r) {
            self::assertStringContainsString("'" . $r . "'", $routes);
        }
        $seen = [];
        EquipmentAudit::useSink(static function (string $a, int $t, int $u, string $e, int $id) use (&$seen): void {
            $seen[] = $e;
        });
        EquipmentAudit::log('equipment.prekit.save', 3, 7, 1, []);
        self::assertSame(['game_item_prekit'], $seen);
    }

    public function testMigrationIsRegisteredAndIdempotent(): void
    {
        $root = dirname(__DIR__, 2);
        $step = (string) file_get_contents($root . '/database/steps/4300_equipment_configurator_social.php');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS game_item_favorites', $step);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS game_item_prekits', $step);
        self::assertStringContainsString('tableExists', $step);
        self::assertStringContainsString('tenant_id INT UNSIGNED NOT NULL', $step);
        $registry = require $root . '/database/steps/registry.php';
        $found = array_values(array_filter($registry, static fn (array $s): bool => ($s['file'] ?? '') === '4300_equipment_configurator_social.php'));
        self::assertCount(1, $found);
        self::assertTrue($found[0]['rerun']);
    }

    public function testScriptAndViewCarryTheNewFeatures(): void
    {
        $root = dirname(__DIR__, 2);
        $js = (string) file_get_contents($root . '/public/assets/js/equipment-configurator.js');
        foreach (['function transferWeapon', 'Retiré car incompatible', 'function orphanMags', 'function outfitCompat', 'function insertEntries',
            'function toggleFav', 'data-cfg-star', 'data-cfg-pfilter', 'function trendLabel', 'urls.social', 'urls.prekits', 'data-pk-partial',
            'Insérer ce qui tient', 'function applySource', "['merge'", 'data-src-part', 'renderSourcePreview', 'kg(l.max)'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        self::assertDoesNotMatchRegularExpression('/innerHTML\s*=\s*[^;]*\.(n|name)\b(?![^;]*esc\()/', $js, 'nom inséré sans échappement');
        self::assertDoesNotMatchRegularExpression('#https?://#', $js);
        $view = (string) file_get_contents($root . '/views/equipment/configurator.php');
        foreach (['id="cfg-prekits"', 'data-cfg-sources-preview', 'data-cfg-sources-side', 'data-cfg-pfilter="fav"', 'data-cfg-pfilter="trend"', 'data-cfg-forweapons'] as $needle) {
            self::assertStringContainsString($needle, $view, $needle);
        }
        // Pas de petit libellé au-dessus des titres dans les nouvelles fenêtres.
        foreach (['cfg-prekits', 'cfg-sources'] as $id) {
            $start = strpos($view, 'id="' . $id . '"');
            $chunk = substr($view, (int) $start, (int) strpos($view, '</dialog>', (int) $start) - (int) $start);
            self::assertStringNotContainsString('cfg-kicker', $chunk, $id);
        }
    }

    private static function methodBody(string $src, string $method): string
    {
        $start = strpos($src, 'function ' . $method . '(');
        self::assertNotFalse($start, $method);
        $next = strpos($src, "\n    public function ", $start + 10);
        $nextPrivate = strpos($src, "\n    private function ", $start + 10);
        $nextStatic = strpos($src, "\n    private static function ", $start + 10);
        $end = min($next === false ? strlen($src) : $next, $nextPrivate === false ? strlen($src) : $nextPrivate, $nextStatic === false ? strlen($src) : $nextStatic);

        return substr($src, $start, $end - $start);
    }
}
