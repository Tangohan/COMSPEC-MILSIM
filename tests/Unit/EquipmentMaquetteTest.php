<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\GameEquipment\PlayerKitService;
use App\Support\EquipmentSpaces;
use PHPUnit\Framework\TestCase;

/**
 * Visuel de la page Équipement : les compteurs du bandeau ne montrent que des données réelles, aucune donnée de
 * démonstration de la maquette n'entre dans la page, et les fonctions de la page restent là (filtres sans script,
 * contenu des cartes, fenêtre de l'assistant).
 */
final class EquipmentMaquetteTest extends TestCase
{
    private const EVIL = '<script>alert(1)</script>"\'&';

    private function read(string $rel): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
    }

    /** @return array<string, mixed> */
    private static function wardrobe(int $id, string $slug = 'tenue'): array
    {
        return [
            'id' => $id, 'user_id' => 1, 'name' => 'Tenue ' . self::EVIL, 'display_name' => 'Tenue ' . self::EVIL, 'slug' => $slug, 'mine' => true,
            'owner_label' => 'Moi', 'collection_id' => null, 'collection_name' => null, 'kinds' => ['vest'], 'created_at' => '2026-11-01', 'visibility' => 'tenant',
            'source' => '', 'is_favorite_me' => false, 'cover_url' => null, 'gallery_urls' => [], 'preview' => [],
            'inventory' => [
                ['title' => 'Tenue', 'count' => 2, 'items' => [['name' => 'Garrot ' . self::EVIL, 'qty' => 2, 'class' => 'a']]],
                ['title' => 'JPC', 'count' => 1, 'items' => [['name' => 'Radio', 'qty' => 1, 'class' => 'b']]],
                ['title' => 'Sac', 'count' => 3, 'items' => [['name' => 'Pansement', 'qty' => 3, 'class' => 'c']]],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $nav
     */
    private function hub(array $data = [], array $nav = []): string
    {
        $mine = $data['mineWardrobes'] ?? [];

        return Response::view('equipment.hub', $data + [
            'wardrobes' => $mine, 'mineWardrobes' => [], 'gameWardrobes' => [], 'collections' => [], 'equipmentClasses' => [], 'dotationItems' => [], 'kits' => [],
            'kitsAllowed' => true, 'canManageKits' => false, 'kitUnitOptions' => [], 'kitRoleOptions' => [], 'kitUnitsReady' => true, 'catalogCount' => 0, 'upgradeUrl' => '/u',
            'myGameKit' => null, 'canManageCatalog' => false, 'canManageDotation' => false, 'equipmentKindLabels' => ['vest' => 'Gilet', 'backpack' => 'Sac'],
            'migrationMissing' => false, 'csrfToken' => 't', 'flash_success' => '', 'flash_error' => '', 'coverHint' => 'x',
            'eqNav' => $nav + [
                'espace' => 'mes-tenues', 'collection' => 0, 'tab' => 'fiches', 'q' => '', 'type' => '', 'sort' => 'name', 'collection_row' => null, 'detail' => null, 'creer' => false,
                'paged' => EquipmentSpaces::paginate($mine, 1),
                'counts' => ['mes-tenues' => 17, 'favoris' => 2, 'collections' => 0, 'communaute' => 23, 'kits' => 0, 'materiel' => 0],
            ],
        ])->body();
    }

    public function testCountersWithoutRealDataAreNotShown(): void
    {
        $html = $this->hub();
        self::assertStringContainsString('Mes tenues', $html);
        self::assertMatchesRegularExpression('~<dt>Mes tenues</dt>\s*<dd>17</dd>~', $html);
        self::assertMatchesRegularExpression('~<dt>Communauté</dt>\s*<dd>23</dd>~', $html);
        self::assertStringNotContainsString('Kit porté en jeu', $html, 'jamais vu en jeu : pas de compteur');
        self::assertStringNotContainsString('Objets suivis', $html, 'aucun objet remonté : pas de compteur');

        $notReady = $this->hub(['migrationMissing' => true], ['counts' => [], 'paged' => null]);
        self::assertStringNotContainsString('eqx-stats', $notReady, 'page pas prête : aucun compteur');
    }

    public function testCountersShowTheRealGameKitAndCatalogueEscaped(): void
    {
        $html = $this->hub(['catalogCount' => 42, 'myGameKit' => ['last_seen_at' => '2026-11-02 10:00:00', 'role' => 'Rôle ' . self::EVIL, 'sections' => []]]);
        self::assertMatchesRegularExpression('~<dt>Objets suivis</dt>\s*<dd>42</dd>~', $html);
        self::assertStringContainsString('Kit porté en jeu', $html);
        self::assertStringContainsString('02/11/2026', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertMatchesRegularExpression('~<span translate="no">Rôle &lt;script&gt;~', $html);
    }

    public function testNoDemoDataFromTheMockupEntersThePage(): void
    {
        $sources = '';
        foreach (glob(dirname(__DIR__, 2) . '/views/equipment/*.php') ?: [] as $file) {
            $sources .= (string) file_get_contents($file);
        }
        $sources .= $this->read('public/assets/css/equipment-refonte.css');
        foreach (['Jake Gylenhall', 'Gold Digger', 'Chill Connard', 'FS Cool', 'TA1', 'YA4', 'HAWK', 'MISTRAL', 'KESTREL', 'copies</', 'font-face', 'data:image', 'radial-gradient(ellipse 18'] as $demo) {
            self::assertStringNotContainsString($demo, $sources, $demo);
        }
        // Le bouton « Enfiler » de la maquette n'existe pas côté site : on enfile depuis l'arsenal en jeu.
        self::assertStringNotContainsString('data-wear', $sources);
    }

    public function testLandingHasTheFullHeroAndOtherAddressesAReducedOne(): void
    {
        $landing = $this->hub();
        self::assertStringContainsString('Votre kit.', $landing);
        self::assertStringContainsString('Prêt en jeu.', $landing);
        // Vue d'ensemble (4 sections avec appel à l'action) seulement à l'arrivée.
        self::assertStringContainsString('Par où commencer', $landing);
        foreach (['Mes tenues', 'Kits de la communauté', 'Modèles', 'Configurateur'] as $section) {
            self::assertStringContainsString('<h3>' . $section . '</h3>', $landing, $section);
        }
        self::assertStringNotContainsString('eqx-hero--short', $landing);

        foreach ([['espace' => 'favoris'], ['q' => 'radio'], ['type' => 'vest'], ['sort' => 'date']] as $nav) {
            $other = $this->hub([], $nav);
            self::assertStringContainsString('eqx-hero--short', $other);
            self::assertStringNotContainsString('Par où commencer', $other);
            self::assertSame(1, substr_count($other, 'aria-current="page"'), 'un seul espace à la fois');
        }
    }

    public function testCardKeepsContentsFavoriteAndAceWithoutNestingFormsInLinks(): void
    {
        $slug = PlayerKitService::MEMBER_SLUG;
        $html = $this->hub(['mineWardrobes' => [self::wardrobe(4), self::wardrobe(5, $slug)]]);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        foreach (['Tenue (2)', 'JPC (1)', 'Sac (3)'] as $box) {
            self::assertStringContainsString($box, $html);
        }
        self::assertStringContainsString('class="eqx-inv-items" translate="no"', $html);
        self::assertStringContainsString('Garrot &lt;script&gt;', $html);
        self::assertStringContainsString('flex-grow:3', $html);
        self::assertStringContainsString('data-eq-ace-copy-card="4"', $html);
        self::assertSame(1, substr_count($html, 'eqx-tcard__worn'), 'seule la tenue de mon kit en jeu porte le badge');
        $card = $this->read('views/equipment/_wardrobe_card.php');
        self::assertLessThan(strpos($card, '<form'), strpos($card, '</a>'), 'le favori reste hors du lien de la carte');
    }

    public function testFiltersRemainAPlainGetFormAndAutoSubmitWithScript(): void
    {
        $html = $this->hub([], ['type' => 'vest']);
        self::assertStringContainsString('<form method="get"', $html);
        self::assertStringContainsString('name="q"', $html);
        self::assertStringContainsString('name="tri"', $html);
        self::assertStringContainsString('type="radio" name="type" value="vest" checked', $html);
        self::assertStringContainsString('Filtrer', $html, 'le bouton reste pour le repli sans script');
        self::assertStringContainsString('input[type="radio"]', $this->read('public/assets/js/equipment-catalog.js'));
        // Sans script, les images ne doivent pas rester transparentes sous leur squelette.
        self::assertStringContainsString('<noscript><style>.eq-hub__img { opacity: 1; }', $html);
    }

    public function testPageVariableAndModalFrameStaySafe(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/views/equipment/*.php') ?: [] as $file) {
            self::assertDoesNotMatchRegularExpression('/\$nav\b/', (string) file_get_contents($file), basename($file) . ' : le menu du portail écrase $nav');
        }
        self::assertStringContainsString('data-ath-modal', $this->hub(), 'assistant en fenêtre');
        $css = $this->read('public/assets/css/equipment-refonte.css');
        self::assertDoesNotMatchRegularExpression('/ath-in-modal[^{]*\{[^}]*display:\s*none/', $css, 'le contenu reste visible dans la fenêtre');
        self::assertStringContainsString('html.ath-in-modal .eqx', $css);
    }
}
