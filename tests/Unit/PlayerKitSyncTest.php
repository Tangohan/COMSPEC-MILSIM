<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GameEquipment\PlayerKitService;
use App\Support\ArsenalLoadoutItems;
use PHPUnit\Framework\TestCase;

/**
 * Kits portés en jeu ↔ tenues : même lecture du loadout, noms affichés en jeu, enregistrement en tenue,
 * et câblage sur Équipement des joueurs (back-office) et la page Équipement (« Mon kit en jeu »).
 */
final class PlayerKitSyncTest extends TestCase
{
    private const LOADOUT = '[["arifle_MX_F","","","optic_Aco",["30Rnd_65x39_caseless_mag",30],[],""],[],["hgun_P07_F","","","",["16Rnd_9x21_Mag",16],[],""],["U_B_CombatUniform_mcam",[["FirstAidKit",2]]],["V_PlateCarrier1_rgr",[["30Rnd_65x39_caseless_mag",5,30]]],[],"H_HelmetB","",[],["ItemMap","","ItemRadio","ItemCompass","ItemWatch",""]]';

    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testCatalogNamesReplaceGuessedNamesAndCarryPlayers(): void
    {
        $sections = ArsenalLoadoutItems::grouped(self::LOADOUT, [
            'arifle_mx_f' => ['name' => 'MX 6.5 mm', 'players' => 7],
            'v_platecarrier1_rgr' => ['name' => 'Porte-plaques (vert)', 'players' => 3],
        ]);
        $byTitle = [];
        foreach ($sections as $s) {
            $byTitle[$s['title']] = $s['items'];
        }
        self::assertSame('MX 6.5 mm', $byTitle['Arme'][0]['name']);
        self::assertSame('arifle_MX_F', $byTitle['Arme'][0]['class']);
        self::assertSame(7, $byTitle['Arme'][0]['players']);
        self::assertSame('Porte-plaques (vert)', $byTitle['Gilet'][0]['name']);
        // Objet absent du catalogue : nom déduit de la classe, sans compteur.
        self::assertSame('HelmetB', $byTitle['Casque'][0]['name']);
        self::assertArrayNotHasKey('players', $byTitle['Casque'][0]);
        // Le catalogue ne fuit pas d’un appel à l’autre.
        $plain = ArsenalLoadoutItems::grouped(self::LOADOUT);
        self::assertSame('MX', $plain[0]['items'][0]['name']);
    }

    public function testClassesListsEveryItemOnce(): void
    {
        $classes = ArsenalLoadoutItems::classes(self::LOADOUT);
        foreach (['arifle_MX_F', 'optic_Aco', '30Rnd_65x39_caseless_mag', 'FirstAidKit', 'H_HelmetB', 'ItemRadio'] as $c) {
            self::assertContains($c, $classes);
        }
        self::assertSame(count($classes), count(array_unique(array_map('strtolower', $classes))));
    }

    public function testGameLoadoutBecomesWardrobePayload(): void
    {
        self::assertSame(json_encode(json_decode(self::LOADOUT, true), JSON_UNESCAPED_SLASHES), PlayerKitService::payloadFrom(self::LOADOUT));
        // ACE étendu : [loadout, [[clé, valeur]…]].
        $extended = '[' . self::LOADOUT . ',[["ace_earplugs",true]]]';
        self::assertNotSame('', PlayerKitService::payloadFrom($extended));
        self::assertNotSame([], ArsenalLoadoutItems::grouped($extended));
        self::assertSame('', PlayerKitService::payloadFrom('[]'));
        self::assertSame('', PlayerKitService::payloadFrom('{"a":1}'));
    }

    public function testPagesAreWired(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("post('/back-office/equipement-joueurs/kits/{steam}/tenue', [GameEquipmentAdminController::class, 'saveKit'], [AuthMiddleware::class, OrganizationAdminMiddleware::class]", $routes);
        self::assertStringContainsString("post('/equipment/mon-kit/tenue', [ArsenalWardrobeController::class, 'saveMyGameKit']", $routes);
        self::assertLessThan(strpos($routes, "'/equipment/{slug}'"), strpos($routes, "'/equipment/mon-kit/tenue'"));

        self::assertStringContainsString('PlayerKitService::class => new', $this->read('app/Core/Container.php'));
        self::assertStringContainsString("include __DIR__ . '/partials/game_player_kits.php'", $this->read('views/admin/organization/game_equipment.php'));
        self::assertStringContainsString('id="kits-portes"', $this->read('views/admin/organization/partials/game_player_kits.php'));
        self::assertStringContainsString('Mon kit en jeu', $this->read('views/equipment/hub.php'));
        self::assertStringContainsString('catalogFor($tenantId, ArsenalLoadoutItems::classes($payloadText))', $this->read('app/Controllers/Web/ArsenalWardrobeController.php'));
        self::assertStringContainsString('eq-hub__item-seen', $this->read('public/assets/js/equipment-catalog.js'));
    }
}
