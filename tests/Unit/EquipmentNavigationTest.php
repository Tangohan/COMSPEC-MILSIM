<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\GameEquipment\OutfitAssistant;
use App\Services\GameEquipment\RoleKitLoadout;
use App\Support\ArsenalLoadoutItems;
use App\Support\EquipmentSpaces;
use PHPUnit\Framework\TestCase;

/**
 * Page Équipement par espaces (adresses, recherche, pagination, anciennes adresses), fenêtres, favori en un clic, et
 * assistant : choix multiple d'objets par emplacement, qui donne des variantes (une tenue par choix) toutes acceptées
 * par l'export ACE.
 */
final class EquipmentNavigationTest extends TestCase
{
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
            ['arifle_Katiba_F', 'Katiba 6.5 mm', 'primary', 4],
            ['arifle_SPAR_01_blk_F', 'SPAR-16 5.56 mm', 'primary', 3],
            ['U_B_CombatUniform_mcam', 'Tenue de combat', 'uniform', 8],
            ['V_PlateCarrier1_rgr', 'Porte-plaques', 'vest', 8],
            ['V_TacVest_khk', 'Gilet tactique', 'vest', 5],
            ['H_HelmetB', 'Casque ECH', 'headgear', 8],
            ['ItemMap', 'Carte', 'item', 9],
            ['ACE_fieldDressing', 'Pansement', 'medical', 9],
            ['30Rnd_65x39_caseless_mag', '6.5 mm 30Rnd', 'magazine', 9],
        ];

        return array_map(static fn (array $r): array => [
            'class' => $r[0], 'name' => $r[1], 'kind' => $r[2], 'mod' => 'Arma 3',
            'max_ammo' => $r[2] === 'magazine' ? 30 : null, 'players' => $r[3],
        ], $rows);
    }

    public function testEachSpaceHasItsOwnAddressAndUnknownOnesFallBackToMyOutfits(): void
    {
        self::assertSame(['mes-tenues', 'favoris', 'collections', 'communaute', 'kits', 'materiel'], array_keys(EquipmentSpaces::SPACES));
        self::assertSame('favoris', EquipmentSpaces::normalize('favoris'));
        self::assertSame('mes-tenues', EquipmentSpaces::normalize('inconnu'));
        self::assertSame('mes-tenues', EquipmentSpaces::normalize(['x']));
        self::assertSame('/equipment', EquipmentSpaces::url(['espace' => 'mes-tenues']));
        self::assertSame('/equipment?espace=favoris', EquipmentSpaces::url(['espace' => 'favoris']));
        self::assertSame('/equipment?espace=communaute&q=m4&page=3', EquipmentSpaces::url(['espace' => 'communaute', 'q' => 'm4', 'page' => 3, 'tri' => 'name', 'type' => '']));
        self::assertSame('/equipment?espace=collections&collection=7', EquipmentSpaces::url(['espace' => 'collections', 'collection' => 7, 'page' => 1]));
    }

    public function testOldAddressesLandOnTheSpaceThatCarriesThemNow(): void
    {
        self::assertSame('collections', EquipmentSpaces::resolve(['collection' => '4'])['espace']);
        self::assertSame(4, EquipmentSpaces::resolve(['collection' => '4', 'edit_collection' => '1'])['collection']);
        self::assertSame('materiel', EquipmentSpaces::resolve(['tab' => 'dotation'])['espace']);
        self::assertSame('dotation', EquipmentSpaces::resolve(['tab' => 'dotation'])['tab']);
        self::assertSame('materiel', EquipmentSpaces::resolve(['fiche' => '9'])['espace']);
        self::assertSame('mes-tenues', EquipmentSpaces::resolve(['tenue' => '12'])['espace'], 'la tenue s’ouvre en fenêtre sur la liste');
        self::assertSame('kits', EquipmentSpaces::resolve(['espace' => 'kits', 'collection' => '4'])['espace'], 'l’espace demandé l’emporte');
    }

    public function testSearchIgnoresAccentsAndLooksInOwnerCollectionAndItems(): void
    {
        $wardrobes = [
            ['id' => 1, 'name' => 'Médecin', 'owner_label' => 'Alpha', 'kinds' => ['vest'], 'preview' => [['name' => 'Gilet lourd']]],
            ['id' => 2, 'name' => 'Fusilier', 'owner_label' => 'Bravo', 'collection_name' => 'Section Éclair', 'kinds' => ['primary'], 'preview' => []],
            ['id' => 3, 'name' => 'Radio', 'owner_label' => 'Charlie', 'kinds' => ['backpack'], 'preview' => [['name' => 'Sac RT-1523']]],
        ];
        self::assertSame([1], array_column(EquipmentSpaces::filterWardrobes($wardrobes, 'medecin', ''), 'id'));
        self::assertSame([2], array_column(EquipmentSpaces::filterWardrobes($wardrobes, 'eclair', ''), 'id'));
        self::assertSame([3], array_column(EquipmentSpaces::filterWardrobes($wardrobes, 'rt-1523', ''), 'id'));
        self::assertSame([2], array_column(EquipmentSpaces::filterWardrobes($wardrobes, '', 'primary'), 'id'));
        self::assertSame([1, 2, 3], array_column(EquipmentSpaces::filterWardrobes($wardrobes, '', ''), 'id'));
        self::assertSame([1, 3, 2], array_column(EquipmentSpaces::sortWardrobes($wardrobes, 'collection'), 'id'), 'sans collection d’abord, puis par nom');
        self::assertSame([2, 1, 3], array_column(EquipmentSpaces::sortWardrobes($wardrobes, 'name'), 'id'), 'alphabétique : Fusilier, Médecin, Radio');
    }

    public function testPaginationKeepsEveryItemReachableAndClampsThePage(): void
    {
        $items = range(1, 30);
        $first = EquipmentSpaces::paginate($items, 1);
        self::assertSame(12, EquipmentSpaces::PER_PAGE);
        self::assertSame(3, $first['pages']);
        self::assertSame(30, $first['total']);
        self::assertSame([1, 12], [$first['from'], $first['to']]);
        $last = EquipmentSpaces::paginate($items, 99);
        self::assertSame(3, $last['page']);
        self::assertSame([25, 30], [$last['from'], $last['to']]);
        self::assertSame(range(25, 30), $last['items']);
        $none = EquipmentSpaces::paginate([], 5);
        self::assertSame([1, 1, 0, 0], [$none['page'], $none['pages'], $none['from'], $none['to']]);
    }

    public function testCardsAreRealLinksAndFavoriteWorksWithAndWithoutScript(): void
    {
        $card = $this->read('views/equipment/_wardrobe_card.php');
        // Sans JavaScript : la carte mène à la fiche de la tenue, le favori est un formulaire qui revient à l'espace d'origine.
        self::assertStringContainsString('<a class="eq-hub__card" href="<?= $h(url(\'equipment/tenues/\' . $wid)) ?>"', $card);
        self::assertStringContainsString('data-eq-fav-form', $card);
        self::assertStringContainsString('name="_return"', $card);
        self::assertStringContainsString('aria-pressed=', $card);
        $controller = $this->read('app/Controllers/Web/ArsenalWardrobeController.php');
        self::assertStringContainsString("'favorite' => \$ok ? \$on : null", $controller, 'réponse JSON du favori en un clic');
        // Le retour ne peut viser qu'une adresse de la page Équipement (pas de redirection ouverte).
        self::assertStringContainsString('#^/equipment(\?[A-Za-z0-9_=&%.\-]*)?$#', $controller);
        $js = $this->read('public/assets/js/equipment-catalog.js');
        self::assertStringContainsString('data-eq-fav-form', $js);
        self::assertStringContainsString('function plainClick', $js);
    }

    public function testDialogsUseNativeModalsWithFocusReturnAndMobileFullScreen(): void
    {
        $js = $this->read('public/assets/js/equipment-catalog.js') . $this->read('public/assets/js/equipment-assistant.js');
        self::assertStringContainsString('showModal()', $js, 'fenêtre modale native : focus gardé dans la fenêtre, Échap qui ferme');
        self::assertStringContainsString("addEventListener('close'", $js, 'le focus revient sur le bouton d’origine');
        $dialogs = $this->read('views/equipment/_dialogs.php');
        self::assertSame(substr_count($dialogs, '<dialog'), substr_count($dialogs, 'aria-labelledby='), 'chaque fenêtre est nommée');
        $css = $this->read('public/assets/css/equipment-refonte.css');
        self::assertStringContainsString('height: 100dvh', $css);
        self::assertStringContainsString('overflow-x: auto', $css, 'onglets défilants sur téléphone');
        $assistant = $this->read('views/equipment/assistant.php');
        self::assertStringContainsString('aria-labelledby="eqp-title"', $assistant);
        // Les noms d'objets viennent du jeu : le script ne les insère jamais comme du HTML.
        $picker = $this->read('public/assets/js/equipment-assistant.js');
        self::assertDoesNotMatchRegularExpression('/innerHTML\s*=\s*[^\'"\s]/', $picker);
        self::assertStringNotContainsString('insertAdjacentHTML', $picker);
    }

    public function testSeveralChoicesForOneSlotBecomeVariantsCappedAtSix(): void
    {
        $two = OutfitAssistant::expandVariants(['primary' => ['arifle_MX_F', 'arifle_Katiba_F'], 'vest' => ['V_PlateCarrier1_rgr']]);
        self::assertFalse($two['truncated']);
        self::assertSame([
            ['primary' => 'arifle_MX_F', 'vest' => 'V_PlateCarrier1_rgr'],
            ['primary' => 'arifle_Katiba_F', 'vest' => 'V_PlateCarrier1_rgr'],
        ], $two['combos'], 'la première combinaison reprend les premiers choix');
        $four = OutfitAssistant::expandVariants(['primary' => ['a', 'b'], 'vest' => ['c', 'd']]);
        self::assertSame(['a', 'a', 'b', 'b'], array_column($four['combos'], 'primary'));
        $many = OutfitAssistant::expandVariants(['primary' => ['a', 'b', 'c', 'd'], 'vest' => ['e', 'f', 'g']]);
        self::assertTrue($many['truncated']);
        self::assertCount(OutfitAssistant::MAX_VARIANTS, $many['combos']);
        $none = OutfitAssistant::expandVariants(['primary' => [], 'vest' => ['', ' ']]);
        self::assertSame([['primary' => '', 'vest' => '']], $none['combos'], 'aucun choix = un emplacement vide, pas de variante');
        $dupes = OutfitAssistant::expandVariants(['primary' => ['A', 'a', 'A']]);
        self::assertSame([['primary' => 'A']], $dupes['combos']);
    }

    public function testEveryVariantIsAValidSingleValuedOutfitAcceptedByTheAceExport(): void
    {
        $assistant = new OutfitAssistant();
        $picks = ['primary' => ['arifle_MX_F', 'arifle_Katiba_F', 'arifle_SPAR_01_blk_F'], 'vest' => ['V_PlateCarrier1_rgr', 'V_TacVest_khk'], 'uniform' => ['U_B_CombatUniform_mcam']];
        $expanded = OutfitAssistant::expandVariants($picks);
        self::assertGreaterThan(1, count($expanded['combos']));
        $seen = [];
        foreach ($expanded['combos'] as $combo) {
            $r = $assistant->propose('rifleman', self::catalogue(), [], ['slot' => $combo, 'extra' => []]);
            self::assertNotSame('', $r['payload']);
            self::assertTrue($r['ace_ok'], 'chaque variante est lisible par l’arsenal ACE');
            self::assertNotNull(ArsenalLoadoutItems::aceExport($r['payload']));
            // Un emplacement à valeur unique par tenue : exactement une arme principale et un gilet.
            $classes = array_map('strtolower', RoleKitLoadout::classes($r['kit']));
            $weapons = array_intersect($classes, ['arifle_mx_f', 'arifle_katiba_f', 'arifle_spar_01_blk_f']);
            self::assertCount(1, $weapons);
            $vests = array_intersect($classes, ['v_platecarrier1_rgr', 'v_tacvest_khk']);
            self::assertCount(1, $vests);
            $seen[implode('|', $weapons) . '/' . implode('|', $vests)] = true;
        }
        self::assertSame(count($expanded['combos']), count($seen), 'deux variantes ne sont jamais identiques');
    }

    public function testSeveralValuesInTheFormKeepTheFirstValidOneAsTheBaseOutfit(): void
    {
        $r = (new OutfitAssistant())->propose('rifleman', self::catalogue(), [], ['slot' => ['primary' => ['arifle_Katiba_F', 'arifle_MX_F'], 'vest' => ['']], 'extra' => []]);
        self::assertSame('arifle_Katiba_F', $r['slots']['primary']['chosen']);
        self::assertSame(['arifle_Katiba_F', 'arifle_MX_F'], $r['slots']['primary']['chosen_all']);
        self::assertSame('', $r['slots']['vest']['chosen']);
        self::assertSame([], $r['slots']['vest']['chosen_all']);
        // Ancienne adresse : un seul objet en texte reste accepté.
        $old = (new OutfitAssistant())->propose('rifleman', self::catalogue(), [], ['slot' => ['primary' => 'arifle_MX_F'], 'extra' => []]);
        self::assertSame(['arifle_MX_F'], $old['slots']['primary']['chosen_all']);
    }

    public function testSaveCreatesOneOutfitPerVariantWithTheExistingRightsAndKeyOfTheKits(): void
    {
        $controller = $this->read('app/Controllers/Web/ArsenalWardrobeController.php');
        self::assertStringContainsString('OutfitAssistant::expandVariants($picks)', $controller);
        self::assertStringContainsString('KitsAccess::allows($tenantId)', $controller);
        self::assertStringContainsString('canManageKits()', $controller);
        self::assertStringContainsString('Csrf::validate', $controller);
        $view = $this->read('views/equipment/assistant.php');
        self::assertStringContainsString('name="slot[<?= $h($slotKey) ?>][]"', $view);
    }

    public function testAssistantViewRendersWithSeveralChoicesAndShowsTheVariantCount(): void
    {
        $evil = '<script>alert(1)</script>"\'&';
        $catalogue = self::catalogue();
        $catalogue[0]['name'] = 'MX ' . $evil;
        $proposal = (new OutfitAssistant())->propose('rifleman', $catalogue, [], ['slot' => ['primary' => ['arifle_MX_F', 'arifle_Katiba_F']], 'extra' => []]);
        $html = Response::view('equipment.assistant', [
            'assistantLocked' => false, 'catalogCount' => 10, 'canManageKits' => true, 'wantsKit' => false, 'csrfToken' => 't', 'upgradeUrl' => '/u',
            'roleChoices' => [['id' => 5, 'name' => 'Médecin ' . $evil, 'abbreviation' => '', 'category' => 'Santé']],
            'profileOptions' => OutfitAssistant::profileOptions(), 'selectedRole' => 5, 'profile' => 'rifleman', 'proposal' => $proposal,
            'preview' => ArsenalLoadoutItems::grouped($proposal['payload'], $proposal['names']), 'suggestedName' => 'Fusilier', 'detectedFromRole' => false,
            'unitOptions' => [], 'roleOptions' => [], 'unitsReady' => true, 'flash_success' => '', 'flash_error' => '',
            'variantCount' => 2, 'variantsTruncated' => false,
        ])->body();
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('2 tenues seront créées', $html);
        self::assertStringContainsString('name="slot[primary][]" value="arifle_MX_F"', $html);
        self::assertStringContainsString('name="slot[primary][]" value="arifle_Katiba_F"', $html);
        self::assertStringContainsString('data-eqp-role', $html, 'l’emploi se choisit aussi dans la fenêtre');
        $json = (string) substr($html, (int) strpos($html, 'id="eqp-data"'));
        self::assertStringContainsString('arifle_Katiba_F', $json);
    }
}
