<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Billing\SubscriptionPlanFeaturesCatalog as Catalog;
use App\Services\GameEquipment\KitsAccess;
use App\Services\GameEquipment\ObservedLoadouts;
use App\Services\GameEquipment\OutfitAssistant;
use App\Services\GameEquipment\RoleKitLoadout;
use App\Support\ArsenalLoadoutItems;
use PHPUnit\Framework\TestCase;
use Tests\Support\MysqlDdlSqlitePdo;

/**
 * Refonte de la page Équipement : clé de formule des kits et de l'assistant, routes (anciennes adresses conservées),
 * assistant de création (règles lisibles, objets pris uniquement dans le catalogue de la communauté, format accepté par
 * l'export ACE), reprise d'une tenue du jeu en kit, vues rendues sans erreur avec des noms hostiles, étape de migration.
 */
final class EquipmentRefonteTest extends TestCase
{
    private const EVIL = '<script>alert(1)</script>"\'&';

    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null, players: int}> */
    private static function catalogue(): array
    {
        $rows = [
            ['arifle_MX_F', 'MX 6.5 mm', 'primary', 9],
            ['srifle_DMR_03_F', 'Mk-I EMR 7.62 mm', 'primary', 3],
            ['LMG_Mk200_F', 'Mk200 6.5 mm', 'primary', 2],
            ['hgun_P07_F', 'P07 9 mm', 'handgun', 5],
            ['optic_Hamr', 'RCO', 'optic', 7],
            ['optic_LRPS', 'LRPS', 'optic', 2],
            ['U_B_CombatUniform_mcam', 'Tenue de combat', 'uniform', 8],
            ['V_PlateCarrier1_rgr', 'Porte-plaques', 'vest', 8],
            ['H_HelmetB', 'Casque ECH', 'headgear', 8],
            ['B_Kitbag_mcamo', 'Sac Kitbag', 'backpack', 4],
            ['ItemMap', 'Carte', 'item', 9],
            ['ItemCompass', 'Boussole', 'item', 9],
            ['ItemWatch', 'Montre', 'item', 9],
            ['tf_anprc152', 'AN/PRC-152', 'radio', 5],
            ['FirstAidKit', 'Trousse', 'medical', 9],
            ['ACE_fieldDressing', 'Pansement', 'medical', 9],
            ['ACE_tourniquet', 'Garrot', 'medical', 5],
            ['30Rnd_65x39_caseless_mag', '6.5 mm 30Rnd', 'magazine', 9],
            ['16Rnd_9x21_Mag', '9 mm 16Rnd', 'magazine', 5],
        ];

        return array_map(static fn (array $r): array => [
            'class' => $r[0], 'name' => $r[1], 'kind' => $r[2], 'mod' => 'Arma 3',
            'max_ammo' => $r[2] === 'magazine' ? 30 : null, 'players' => $r[3],
        ], $rows);
    }

    private const WORN = '[["arifle_MX_F","","","optic_Hamr",["30Rnd_65x39_caseless_mag",30],[],""],[],["hgun_P07_F","","","",["16Rnd_9x21_Mag",16],[],""],["U_B_CombatUniform_mcam",[["ACE_fieldDressing",4]]],["V_PlateCarrier1_rgr",[["30Rnd_65x39_caseless_mag",6,30]]],[],"H_HelmetB","",[],["ItemMap","","","ItemCompass","ItemWatch",""]]';

    public function testKitsKeyIsInTheCatalogWithProDefaultsAndBothLanguages(): void
    {
        self::assertContains('equipment_kits', Catalog::BOOL_FEATURES);
        $def = Catalog::definitions()['equipment_kits'];
        self::assertSame('bool', $def['type']);
        self::assertTrue($def['read']);
        self::assertNotSame('', $def['label']);
        self::assertNotSame('', $def['label_en']);
        self::assertNotSame('', $def['help']);
        self::assertNotSame('', $def['help_en']);
        $defaults = Catalog::defaultsByPlanSlug();
        self::assertFalse($defaults['free']['equipment_kits']);
        self::assertFalse($defaults['standard']['equipment_kits']);
        self::assertTrue($defaults['pro']['equipment_kits']);
        self::assertTrue($defaults['pro_plus']['equipment_kits']);
        // La synchro avec le jeu garde sa propre clé, l'accès aux tenues aussi.
        self::assertArrayHasKey('equipment_game_sync', $defaults['pro']);
        self::assertTrue($defaults['free']['equipment']);
        self::assertSame('equipment_kits', KitsAccess::FEATURE);
        self::assertStringContainsString('equipment_kits', $this->read('docs/FEATURE-TIERS.md'));
        self::assertStringContainsString("allowsWithCatalogDefault(\$tenantId, self::FEATURE)", $this->read('app/Services/GameEquipment/KitsAccess.php'));
    }

    public function testNewRoutesComeBeforeTheSlugRouteAndOldAddressesRemain(): void
    {
        $routes = $this->read('routes/web.php');
        $slug = strpos($routes, "'/equipment/{slug}'");
        self::assertNotFalse($slug);
        foreach ([
            "get('/equipment/assistant'", "post('/equipment/assistant/enregistrer'", "get('/equipment/kits'", "post('/equipment/kits/depuis-tenue'",
            "post('/equipment/kits/{id}/copier'", "post('/equipment/kits/{id}/destinataire'", "post('/equipment/kits/{id}/supprimer'",
            "post('/equipment/tenues/{id}/favori'", "get('/equipment/tenues'",
        ] as $needle) {
            $pos = strpos($routes, $needle);
            self::assertNotFalse($pos, $needle);
            self::assertLessThan($slug, $pos, $needle . ' doit précéder /equipment/{slug}');
        }
        foreach ([
            "get('/equipment/wardrobes'", "get('/equipment/ace-export'", "get('/equipment/tenues/{id}'", "post('/equipment/tenues/{id}/delete'",
            "post('/equipment/mon-kit/tenue'", "get('/equipment/collections/{id}'", "post('/equipment/wardrobes/{id}/delete'",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes, 'ancienne adresse conservée : ' . $needle);
        }
    }

    public function testKitAndAssistantMutationsAreGatedByPlanAndRights(): void
    {
        $controller = $this->read('app/Controllers/Web/ArsenalWardrobeController.php');
        foreach (['storeKitFromWardrobe', 'copyKit', 'updateKitAudience', 'assistantSave'] as $method) {
            $body = substr($controller, (int) strpos($controller, 'function ' . $method), 1400);
            self::assertStringContainsString('KitsAccess::allows', $body, $method);
            self::assertStringContainsString('Csrf::validate', $body, $method);
        }
        foreach (['storeKitFromWardrobe', 'updateKitAudience', 'destroyKit'] as $method) {
            $body = substr($controller, (int) strpos($controller, 'function ' . $method), 1600);
            self::assertStringContainsString('canManageKits()', $body, $method);
        }
        // Le back-office des kits suit la même clé de formule.
        self::assertStringContainsString('KitsAccess::allows', $this->read('app/Controllers/Admin/Organization/GameEquipmentAdminController.php'));
        // Le jeu reste gouverné par equipment_game_sync.
        self::assertStringContainsString("'equipment_game_sync'", $this->read('app/Controllers/Api/AtakWardrobeApiController.php'));
    }

    public function testRoleNamesMapToAssistantProfiles(): void
    {
        $cases = [
            'Médecin de section' => 'medic', 'Infirmier' => 'medic', 'Tireur d’élite' => 'marksman', 'Sniper' => 'marksman',
            'Mitrailleur FM' => 'mg', 'Antichar' => 'at', 'Opérateur radio' => 'radio', 'JTAC' => 'radio', 'Chef d’équipe' => 'team_leader',
            'Pilote d’hélicoptère' => 'pilot', 'Génie' => 'engineer', 'Fusilier voltigeur' => 'rifleman', 'Grenadier' => 'rifleman',
        ];
        foreach ($cases as $role => $profile) {
            self::assertSame($profile, OutfitAssistant::profileForRole($role), $role);
        }
        self::assertSame('medic', OutfitAssistant::profileForRole('Spécialiste', 'CLS', 'Santé'));
        self::assertSame('rifleman', OutfitAssistant::normalizeProfile('inconnu'));
    }

    public function testAssistantProposesOnlyWhatTheCommunityReportedAndKeepsTheAceFormat(): void
    {
        $catalogue = self::catalogue();
        $known = array_map(static fn (array $i): string => strtolower($i['class']), $catalogue);
        $setups = ObservedLoadouts::tally([self::WORN, self::WORN]);
        $assistant = new OutfitAssistant();

        foreach (array_keys(OutfitAssistant::PROFILES) as $profile) {
            $r = $assistant->propose($profile, $catalogue, $setups);
            foreach (RoleKitLoadout::classes($r['kit']) as $class) {
                self::assertContains(strtolower($class), $known, $profile . ' : ' . $class . ' ne vient pas du catalogue');
            }
            if ($r['payload'] === '') {
                continue;
            }
            // Format getUnitLoadout : 10 entrées, accepté tel quel par l'export ACE (arsenal et liste « Mes équipements »).
            $loadout = json_decode($r['payload'], true);
            self::assertIsArray($loadout, $profile);
            self::assertCount(10, $loadout, $profile);
            self::assertTrue($r['ace_ok'], $profile);
            self::assertNotNull(ArsenalLoadoutItems::aceExport($r['payload']), $profile);
            self::assertStringContainsString('"Test"', ArsenalLoadoutItems::aceExportList([['name' => 'Test', 'payload' => $r['payload']]]), $profile);
            self::assertCount(6, $loadout[9], 'objets assignés : 6 emplacements');
        }

        $rifleman = $assistant->propose('rifleman', $catalogue, $setups);
        self::assertSame('arifle_MX_F', $rifleman['slots']['primary']['chosen']);
        self::assertSame('optic_Hamr', $rifleman['slots']['optic']['chosen'], 'optique habituelle de cette arme');
        $payload = json_decode($rifleman['payload'], true);
        self::assertSame(['30Rnd_65x39_caseless_mag', 30], $payload[0][4], 'chargeur relevé en jeu avec cette arme');
        self::assertSame([], $rifleman['missing']);
    }

    public function testAssistantFlagsWhatIsMissingAndNeverInvents(): void
    {
        $catalogue = array_values(array_filter(self::catalogue(), static fn (array $i): bool => !in_array($i['kind'], ['backpack', 'medical'], true) && $i['class'] !== 'srifle_DMR_03_F'));
        $assistant = new OutfitAssistant();

        $medic = $assistant->propose('medic', $catalogue, []);
        $slots = array_column($medic['missing'], 'slot');
        self::assertContains('backpack', $slots, 'sac médical obligatoire mais aucun sac remonté');
        self::assertSame('', $medic['slots']['backpack']['chosen']);
        self::assertContains('med_bandage', $slots);

        $sniper = $assistant->propose('marksman', $catalogue, []);
        self::assertSame('', $sniper['slots']['primary']['chosen'], 'pas de fusil de précision reconnu : rien n’est choisi');
        self::assertContains('primary', array_column($sniper['missing'], 'slot'));
        self::assertNotSame([], $sniper['slots']['primary']['candidates'], 'les autres armes restent proposées à la main');
    }

    public function testAssistantWithoutReportedDataProposesNothing(): void
    {
        $r = (new OutfitAssistant())->propose('medic', [], []);
        self::assertFalse($r['has_data']);
        self::assertSame('', $r['payload']);
        self::assertFalse($r['ace_ok']);
        self::assertNotSame([], $r['missing']);
        $view = $this->read('views/equipment/assistant.php');
        self::assertStringContainsString('n’a pas encore remonté d’objets du jeu', $view);
        self::assertStringContainsString('Envoyer toutes', $view);
    }

    public function testChoicesFromTheFormAreValidatedAgainstTheCatalogue(): void
    {
        $assistant = new OutfitAssistant();
        $r = $assistant->propose('rifleman', self::catalogue(), [], ['slot' => ['primary' => 'LMG_Mk200_F', 'vest' => 'Classe_Inventee', 'headgear' => ''], 'extra' => []]);
        self::assertSame('LMG_Mk200_F', $r['slots']['primary']['chosen']);
        self::assertSame('', $r['slots']['vest']['chosen'], 'une classe absente du catalogue est refusée');
        self::assertSame('', $r['slots']['headgear']['chosen'], 'aucun = aucun');
        $classes = array_map('strtolower', RoleKitLoadout::classes($r['kit']));
        self::assertNotContains('classe_inventee', $classes);
    }

    public function testObservedLoadoutsTallyWeaponPairings(): void
    {
        $setups = ObservedLoadouts::tally([self::WORN, self::WORN, 'illisible', '[]']);
        self::assertSame(2, $setups['arifle_mx_f']['seen']);
        self::assertSame('30Rnd_65x39_caseless_mag', ObservedLoadouts::top($setups['arifle_mx_f']['magazine']));
        self::assertSame('optic_Hamr', ObservedLoadouts::top($setups['arifle_mx_f']['optic']));
        self::assertSame('16Rnd_9x21_Mag', ObservedLoadouts::top($setups['hgun_p07_f']['magazine']));
        self::assertSame('', ObservedLoadouts::top([]));
    }

    public function testWardrobeFromTheGameBecomesKitSlotsAndBack(): void
    {
        $kit = RoleKitLoadout::fromPayload(self::WORN);
        self::assertNotNull($kit);
        self::assertSame('arifle_MX_F', $kit['weapons']['primary']['class']);
        self::assertSame('optic_Hamr', $kit['weapons']['primary']['optic']);
        self::assertSame('30Rnd_65x39_caseless_mag', $kit['weapons']['primary']['magazine']);
        self::assertSame('hgun_P07_F', $kit['weapons']['handgun']['class']);
        self::assertSame('U_B_CombatUniform_mcam', $kit['simple']['uniform']);
        self::assertSame('ItemMap', $kit['simple']['map']);
        self::assertSame([['class' => 'ACE_fieldDressing', 'count' => 4]], $kit['cargo']['uniform']);
        self::assertNull(RoleKitLoadout::fromPayload('pas une tenue'));

        $again = RoleKitLoadout::build($kit, []);
        self::assertNotNull(ArsenalLoadoutItems::aceExport($again));
        self::assertEqualsCanonicalizing(ArsenalLoadoutItems::classes(self::WORN), ArsenalLoadoutItems::classes($again));
    }

    public function testViewsRenderWithHostileNamesAndEveryState(): void
    {
        $w = static fn (int $id, bool $mine, string $source, bool $fav): array => [
            'id' => $id, 'user_id' => $mine ? 1 : 2, 'name' => 'Tenue ' . self::EVIL, 'display_name' => 'Tenue ' . self::EVIL, 'slug' => 'kit-de-depart-7',
            'mine' => $mine, 'owner_label' => self::EVIL, 'collection_id' => null, 'collection_name' => null, 'kinds' => ['vest'],
            'created_at' => '2026-11-01', 'visibility' => 'personal', 'source' => $source, 'is_favorite_me' => $fav, 'cover_url' => null,
            'gallery_urls' => [], 'preview' => [['name' => 'Gilet ' . self::EVIL, 'icon' => null, 'class' => 'V_X', 'qty' => 1]],
        ];
        $kit = [
            'id' => 7, 'name' => 'Kit ' . self::EVIL, 'description' => 'desc ' . self::EVIL, 'job_role_id' => null, 'unit_id' => 3,
            'role_name' => null, 'unit_name' => 'Section ' . self::EVIL, 'source' => 'game_loadout', 'required_mods' => ['Mod ' . self::EVIL],
            'item_count' => 9, 'copy_wardrobe_id' => 0, 'preview' => [], 'payload_text' => '[]',
            'sections' => [['title' => 'Arme', 'items' => [['name' => 'MX ' . self::EVIL, 'qty' => 2, 'class' => 'arifle_MX_F']]]],
        ];
        $base = [
            'wardrobes' => [$w(1, true, 'ace_arsenal', true), $w(2, false, 'ace_arsenal', false), $w(3, false, 'role_kit', true)],
            'mineWardrobes' => [$w(1, true, 'ace_arsenal', true)], 'gameWardrobes' => [$w(2, false, 'ace_arsenal', false)],
            'collections' => [['id' => 1, 'name' => 'Coll ' . self::EVIL, 'visibility' => 'tenant', 'wardrobe_count' => 2, 'mosaic' => [], 'cover_url' => null, 'mine' => true, 'description' => '']],
            'equipmentClasses' => [['id' => 1, 'name' => 'Fiche ' . self::EVIL, 'slug' => 's', 'category' => 'Cat', 'description' => '', 'cover_url' => null]],
            'dotationItems' => [['id' => 1, 'code' => 'C' . self::EVIL, 'name' => 'Art', 'category' => '', 'description' => '', 'cover_url' => null, 'issued_count' => 0]],
            'kits' => [$kit], 'kitsAllowed' => true, 'canManageKits' => true, 'kitUnitOptions' => [3 => 'Unité ' . self::EVIL],
            'kitRoleOptions' => [5 => 'Rôle ' . self::EVIL], 'kitUnitsReady' => true, 'catalogCount' => 42, 'upgradeUrl' => '/platform/upgrade',
            'myGameKit' => ['last_seen_at' => '2026-11-02 10:00:00', 'role' => 'Rôle ' . self::EVIL, 'sections' => [['title' => 'Arme', 'items' => [['name' => 'N ' . self::EVIL, 'qty' => 3]]]]],
            'canManageCatalog' => true, 'canManageDotation' => true, 'equipmentKindLabels' => ['vest' => 'Gilet'], 'migrationMissing' => false,
            'csrfToken' => 'tok', 'flash_success' => 'ok ' . self::EVIL, 'flash_error' => '', 'coverHint' => 'indication',
        ];
        $states = [
            'complet' => $base,
            'sans formule' => array_merge($base, ['kitsAllowed' => false, 'canManageKits' => false, 'kits' => []]),
            'vide' => array_merge($base, ['wardrobes' => [], 'mineWardrobes' => [], 'gameWardrobes' => [], 'collections' => [], 'equipmentClasses' => [], 'dotationItems' => [], 'kits' => [], 'myGameKit' => null, 'catalogCount' => 0]),
            'migration absente' => array_merge($base, ['migrationMissing' => true]),
        ];
        foreach ($states as $label => $data) {
            $html = Response::view('equipment.hub', $data)->body();
            self::assertNotSame('', $html, $label);
            self::assertStringNotContainsString('<script>alert(1)</script>', $html, $label . ' : nom du jeu ou de membre non échappé');
            if ($label !== 'migration absente') {
                foreach (['id="mes-tenues"', 'id="communaute"', 'id="kits"', 'id="favoris"', 'id="materiel"'] as $anchor) {
                    self::assertStringContainsString($anchor, $html, $label);
                }
            }
        }
        $locked = Response::view('equipment.hub', $states['sans formule'])->body();
        self::assertStringContainsString('eqx-offer', $locked, 'présentation de l’offre, pas une erreur');
        self::assertStringNotContainsString('Copier dans mes tenues', $locked);
        $full = Response::view('equipment.hub', $states['complet'])->body();
        self::assertStringContainsString('Copier dans mes tenues', $full);
        $copied = Response::view('equipment.hub', array_merge($base, ['kits' => [array_merge($kit, ['copy_wardrobe_id' => 12])]]))->body();
        self::assertStringContainsString('Mettre à jour ma copie', $copied);
        self::assertStringContainsString('?tenue=12', $copied);
        $withCover = Response::view('equipment.hub', array_merge($base, ['wardrobes' => [array_merge($w(1, true, 'x', false), ['cover_url' => '/c.jpg'])]]))->body();
        self::assertStringContainsString('loading="lazy"', $withCover, 'images chargées à la demande');
        // Un kit publié en tenue de communauté n'est plus listé deux fois quand la section Kits est ouverte.
        self::assertStringNotContainsString('id="community-tenue-3"', $full);
        self::assertStringContainsString('id="community-tenue-3"', $locked);

        $asst = [
            'assistantLocked' => false, 'catalogCount' => 10, 'canManageKits' => true, 'wantsKit' => false, 'csrfToken' => 't', 'upgradeUrl' => '/u',
            'roleChoices' => [['id' => 5, 'name' => 'Médecin ' . self::EVIL, 'abbreviation' => '', 'category' => 'Santé ' . self::EVIL]],
            'profileOptions' => OutfitAssistant::profileOptions(), 'selectedRole' => 5, 'profile' => '', 'proposal' => null, 'preview' => [],
            'unitOptions' => [3 => 'U'], 'roleOptions' => [5 => 'R'], 'unitsReady' => true, 'flash_success' => '', 'flash_error' => '',
        ];
        foreach (['verrouillé' => array_merge($asst, ['assistantLocked' => true]), 'sans données' => array_merge($asst, ['catalogCount' => 0]), 'départ' => $asst] as $label => $data) {
            $html = Response::view('equipment.assistant', $data)->body();
            self::assertNotSame('', $html, $label);
            self::assertStringNotContainsString('<script>alert(1)</script>', $html, $label);
        }
        $catalogue = self::catalogue();
        $catalogue[0]['name'] = 'MX ' . self::EVIL;
        $proposal = (new OutfitAssistant())->propose('medic', $catalogue, []);
        $html = Response::view('equipment.assistant', array_merge($asst, [
            'proposal' => $proposal, 'profile' => 'medic', 'suggestedName' => 'Médecin ' . self::EVIL, 'detectedFromRole' => true,
            'preview' => ArsenalLoadoutItems::grouped($proposal['payload'], $proposal['names']),
        ]))->body();
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('name="slot[primary]"', $html);
        self::assertStringContainsString('name="_csrf_token"', $html);
        $guide = Response::view('equipment.assistant', array_merge($asst, ['catalogCount' => 0]))->body();
        self::assertStringContainsString('Envoyer toutes', $guide, 'sans objets remontés, la page explique comment en obtenir');
        self::assertStringNotContainsString('<select', $guide);
    }

    public function testStarterKitsMigrationStepIsRegisteredAndIdempotent(): void
    {
        $registry = require dirname(__DIR__, 2) . '/database/steps/registry.php';
        $ids = array_column($registry, 'id');
        self::assertContains('equipment_starter_kits', $ids);
        $entry = $registry[array_search('equipment_starter_kits', $ids, true)];
        self::assertSame('3990_equipment_starter_kits.php', $entry['file']);
        self::assertTrue($entry['rerun']);
        self::assertFileExists(dirname(__DIR__, 2) . '/database/steps/' . $entry['file']);

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestIncomplete('pdo_sqlite requis pour rejouer l’étape.');
        }
        $pdo = new MysqlDdlSqlitePdo();
        $pdo->exec('CREATE TABLE game_role_kits (id INTEGER PRIMARY KEY, tenant_id INTEGER, job_role_id INTEGER, name TEXT)');
        $pdo->changes = 0;
        $pdo->ddl = [];
        $first = $this->runStep($entry['file'], $pdo);
        self::assertStringNotContainsString('ATTENTION', $first);
        $schema = new \App\Support\Migrations\SchemaInspector($pdo);
        self::assertTrue($schema->columnExists('game_role_kits', 'unit_id'));
        self::assertTrue($schema->columnExists('game_role_kits', 'source'));
        self::assertTrue($schema->tableExists('arsenal_wardrobe_favorites'));
        $pdo->changes = 0;
        $pdo->ddl = [];
        $this->runStep($entry['file'], $pdo);
        self::assertSame(0, $pdo->changes, 'second passage : ' . implode(' | ', $pdo->ddl));
    }

    private function runStep(string $file, \PDO $pdo): string
    {
        $root = dirname(__DIR__, 2);
        ob_start();
        try {
            require $root . '/database/steps/' . $file;
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }
}
