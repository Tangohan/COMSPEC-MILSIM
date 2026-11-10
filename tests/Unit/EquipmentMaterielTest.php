<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\GameEquipment\Configurator\ConfiguratorKinds;
use App\Services\GameEquipment\Configurator\PrekitDocument;
use App\Support\EquipmentMateriel;
use App\Support\EquipmentSpaces;
use PHPUnit\Framework\TestCase;

/**
 * Espace Matériel refait : catalogue des objets (familles, sources, tris, fiche d'un objet, favoris, tendances,
 * pré-kits, configurateur), onglets et anciennes adresses, contrat de la page et cloisonnement (données lues par les
 * points d'accès du configurateur, filtrés par la communauté de la session et la formule « equipment »).
 */
final class EquipmentMaterielTest extends TestCase
{
    private const EVIL = '<script>alert(1)</script>"\'&';

    private function read(string $rel): string
    {
        $path = dirname(__DIR__, 2) . '/' . $rel;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testEveryConfiguratorKindBelongsToExactlyOneFamily(): void
    {
        $seen = [];
        foreach (EquipmentMateriel::FAMILIES as $key => [$label, $kinds]) {
            self::assertNotSame('', $label);
            foreach ($kinds as $kind) {
                self::assertArrayHasKey($kind, ConfiguratorKinds::LABELS, $kind . ' : famille inconnue du configurateur');
                self::assertArrayNotHasKey($kind, $seen, $kind . ' est dans deux familles');
                $seen[$kind] = $key;
                self::assertSame($key, EquipmentMateriel::familyOf($kind));
            }
        }
        self::assertSame([], array_diff(array_keys(ConfiguratorKinds::LABELS), array_keys($seen)), 'famille du configurateur sans filtre');
        self::assertSame('divers', EquipmentMateriel::familyOf('inconnue'));
        // Les familles demandées : armes, accessoires, munitions, tenues, gilets, sacs, casques, médical, radio, divers.
        self::assertSame(['armes', 'accessoires', 'munitions', 'tenues', 'gilets', 'sacs', 'casques', 'medical', 'radio', 'divers'], array_keys(EquipmentMateriel::FAMILIES));
    }

    public function testTabsAndLegacyAddresses(): void
    {
        self::assertSame('catalogue', EquipmentSpaces::resolve(['espace' => 'materiel'])['tab']);
        self::assertSame('fiches', EquipmentSpaces::resolve(['espace' => 'materiel', 'tab' => 'fiches'])['tab']);
        self::assertSame('dotation', EquipmentSpaces::resolve(['tab' => 'dotation'])['tab']);
        self::assertSame(['espace' => 'materiel', 'collection' => 0, 'tab' => 'fiches'], EquipmentSpaces::resolve(['fiche' => '3']));
        self::assertSame(['espace' => 'materiel', 'collection' => 0, 'tab' => 'dotation'], EquipmentSpaces::resolve(['dotation' => '5']));
        self::assertSame('materiel', EquipmentSpaces::resolve(['tab' => 'fiches'])['espace']);
        self::assertSame('catalogue', EquipmentSpaces::materielTab(['tab' => ['x']]), 'paramètre mal formé : onglet par défaut');
        self::assertSame('/equipment?espace=materiel', EquipmentSpaces::url(['espace' => 'materiel', 'tab' => 'catalogue']));
        self::assertSame('/equipment?espace=materiel&tab=fiches', EquipmentSpaces::url(['espace' => 'materiel', 'tab' => 'fiches']));
        self::assertSame(['catalogue', 'fiches', 'dotation'], array_keys(EquipmentMateriel::TABS));
    }

    public function testBootContract(): void
    {
        $boot = EquipmentMateriel::boot(['catalogue' => '/c', 'favorite' => '/f'], 'tok', ['sharePrekits' => true], ['available' => false, 'needed' => true]);
        self::assertSame(['catalogue' => '/c', 'favorite' => '/f'], $boot['urls']);
        self::assertSame(['sharePrekits' => true, 'catalog' => false], $boot['can']);
        self::assertFalse($boot['base']['needed'], 'catalogue de base : rien à lancer s’il n’est pas disponible');
        self::assertTrue(EquipmentMateriel::boot([], 't', [], ['available' => true, 'needed' => true])['base']['needed']);
        self::assertFalse(EquipmentMateriel::boot([], 't', [], null)['base']['available']);
        self::assertSame(PrekitDocument::REFUSED_KINDS, $boot['prekitRefused'], 'mêmes refus que le serveur');
        self::assertSame(PrekitDocument::MAX_COUNT, $boot['prekitMax']['count']);
        self::assertSame(ConfiguratorKinds::LABELS, $boot['kinds']);
        self::assertSame(ConfiguratorKinds::CATEGORIES, $boot['cats']);
        self::assertSame([801 => 'uniform', 701 => 'vest', 901 => 'backpack'], $boot['slots']);
        self::assertSame('pertinence', array_key_first($boot['sorts']));
        self::assertCount(count(EquipmentMateriel::FAMILIES), $boot['families']);
        // Même conversion en kg que le configurateur.
        self::assertStringContainsString((string) EquipmentMateriel::MASS_TO_KG, $this->read('public/assets/js/equipment-configurator.js'));
        self::assertGreaterThanOrEqual(24, EquipmentMateriel::BATCH);
    }

    /** @return array<string, mixed> */
    private function hubData(string $tab, bool $manager): array
    {
        return [
            'wardrobes' => [], 'mineWardrobes' => [], 'gameWardrobes' => [], 'collections' => [],
            'equipmentClasses' => [['id' => 1, 'name' => 'Fiche ' . self::EVIL, 'slug' => 's', 'category' => 'Cat ' . self::EVIL, 'description' => '', 'cover_url' => null]],
            'dotationItems' => [['id' => 2, 'code' => 'C' . self::EVIL, 'name' => 'Art ' . self::EVIL, 'category' => '', 'description' => '', 'cover_url' => null, 'issued_count' => 3]],
            'kits' => [], 'kitsAllowed' => false, 'canManageKits' => false, 'kitUnitOptions' => [], 'kitRoleOptions' => [], 'kitUnitsReady' => false,
            'catalogCount' => 0, 'upgradeUrl' => '/u', 'myGameKit' => null, 'canManageCatalog' => $manager, 'canManageDotation' => $manager,
            'canSharePrekits' => $manager, 'equipmentKindLabels' => ['vest' => 'Gilet'], 'migrationMissing' => false, 'csrfToken' => 'tok',
            'flash_success' => '', 'flash_error' => '', 'coverHint' => 'h', 'eqBase' => ['available' => true, 'needed' => true],
            'eqNav' => [
                'espace' => 'materiel', 'collection' => 0, 'tab' => $tab, 'q' => self::EVIL, 'type' => '', 'sort' => 'name', 'paged' => null,
                'collection_row' => null, 'detail' => null, 'creer' => false, 'counts' => ['mes-tenues' => 0, 'materiel' => null],
            ],
        ];
    }

    public function testPageContract(): void
    {
        $html = Response::view('equipment.hub', $this->hubData('catalogue', true))->body();
        self::assertStringNotContainsString('<script>alert(1)</script>', $html, 'nom de fiche, d’article ou recherche non échappé');
        // Catalogue par défaut, deux autres onglets conservés.
        self::assertMatchesRegularExpression('/<div data-eq-panel="catalogue">/', $html);
        self::assertMatchesRegularExpression('/<div data-eq-panel="fiches" hidden>/', $html);
        self::assertMatchesRegularExpression('/<div data-eq-panel="dotation" hidden>/', $html);
        foreach (['catalogue', 'fiches', 'dotation'] as $t) {
            self::assertStringContainsString('data-eq-tab="' . $t . '"', $html);
        }
        // Recherche, filtres, tri, favoris, tendances, grille / liste, états, fiche d'un objet.
        foreach (['data-eqm-q', 'data-eqm-source', 'data-eqm-sort', 'data-eqm-families', 'data-eqm-cats', 'data-eqm-only="fav"', 'data-eqm-only="trend"', 'data-eqm-view="grid"', 'data-eqm-view="list"', 'data-eqm-state', 'data-eqm-more-btn', 'data-eqm-sentinel', 'eqm-card--skeleton', 'id="eqm-sheet"', 'aria-busy="true"'] as $needle) {
            self::assertStringContainsString($needle, $html, $needle);
        }
        foreach (array_keys(EquipmentMateriel::SORTS) as $sort) {
            self::assertStringContainsString('value="' . $sort . '"', $html);
        }
        self::assertStringContainsString('equipment-materiel.js', $html);
        self::assertStringContainsString('equipment-materiel.css', $html);
        self::assertStringContainsString('equipment-base-catalog.js', $html, 'catalogue de base lancé depuis le Matériel s’il manque');
        // Configuration du script : points d'accès du configurateur (filtrés par la communauté de la session), rien d'autre.
        self::assertSame(1, preg_match('#<script type="application/json" id="eqm-boot">(.*?)</script>#s', $html, $m));
        $boot = json_decode($m[1], true);
        self::assertIsArray($boot);
        self::assertSame(['catalogue', 'social', 'favorite', 'prekits', 'icons', 'configurator', 'base'], array_keys($boot['urls']));
        self::assertStringEndsWith('/equipment/configurateur/catalogue', $boot['urls']['catalogue']);
        self::assertStringEndsWith('/equipment/configurateur/membre', $boot['urls']['social']);
        self::assertTrue($boot['can']['sharePrekits']);
        self::assertTrue($boot['base']['needed']);
        self::assertStringNotContainsString('tenant', strtolower($m[1]), 'aucun identifiant de communauté envoyé au navigateur');
        self::assertStringContainsString('Nouvelle fiche', $html);
        self::assertStringContainsString('Nouvel article', $html);
        // Pas de petit libellé au-dessus du titre de l'espace.
        self::assertSame(0, preg_match('#<p[^>]*class="[^"]*(kicker|eyebrow)[^"]*"[^>]*>[^<]*</p>\s*<h2#', $html));

        $member = Response::view('equipment.hub', $this->hubData('fiches', false))->body();
        self::assertMatchesRegularExpression('/<div data-eq-panel="fiches">/', $member);
        self::assertMatchesRegularExpression('/<div data-eq-panel="catalogue" hidden>/', $member);
        self::assertStringNotContainsString('data-eq-open="fiche-new"', $member, 'membre : pas de création de fiche');
        self::assertStringNotContainsString('data-eq-open="dotation-new"', $member, 'membre : pas de création d’article');
        self::assertSame(1, preg_match('#id="eqm-boot">(.*?)</script>#s', $member, $mm));
        self::assertFalse(json_decode($mm[1], true)['can']['sharePrekits'], 'membre : pré-kits personnels seulement');
    }

    public function testRightsAndIsolationStayOnTheServer(): void
    {
        $cfg = $this->read('app/Controllers/Web/EquipmentConfiguratorController.php');
        // Catalogue, favoris, pré-kits : communauté de la session et formule « equipment » (gate), jeton CSRF à l'écriture.
        foreach (['catalogue', 'memberData', 'favorite', 'savePrekit'] as $fn) {
            self::assertMatchesRegularExpression('/function ' . $fn . '\(Request \$request, array \$params = \[\]\): Response\s*\{\s*\$gate = \$this->gate\(true\);/', $cfg, $fn);
        }
        self::assertStringContainsString("\$tenantId = (int) Session::get('tenant_id');", $cfg);
        self::assertStringContainsString("->allows(\$tenantId, 'equipment')", $cfg);
        self::assertStringContainsString('Seul un responsable des kits partage un pré-kit', $cfg);
        self::assertStringContainsString("'editable' => \$mine || (\$p['shared'] && \$manager)", $cfg);
        // ?objet= du configurateur : seulement une classe Arma valide.
        self::assertStringContainsString("'item' => preg_match(ItemCatalogImport::CLASS_RE, \$request->queryString('objet')) === 1", $cfg);
        $hub = $this->read('app/Controllers/Web/ArsenalWardrobeController.php');
        self::assertStringContainsString("'canSharePrekits' => \$this->canManageKits()", $hub);
    }

    public function testScriptContract(): void
    {
        $js = $this->read('public/assets/js/equipment-materiel.js');
        foreach (['urls.catalogue', 'urls.social', 'urls.favorite', 'urls.prekits', 'IntersectionObserver', 'replaceState', 'translate="no"', "'objet'", 'weaponsFor', 'compatOf', 'trendOf', 'AthenaEquipmentBase', "store('athena.eqm.view'"] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        // Textes du jeu toujours échappés : chaque nom ou classe passe par esc().
        self::assertSame(0, preg_match("/'[^']*>'\s*\+\s*(it|x|ri)\.(n|c)\b/", $js), 'nom ou classe inséré sans esc()');
        self::assertSame(0, preg_match('/\+\s*nameOf\((it|x|ri)\)\s*\+\s*\'</', $js), 'nom inséré sans esc()');
        self::assertStringNotContainsString('eval(', $js);
        $cfg = $this->read('public/assets/js/equipment-configurator.js');
        self::assertStringContainsString('if (boot.item) { showItem(boot.item); return; }', $cfg);
        $css = $this->read('public/assets/css/equipment-materiel.css');
        self::assertStringContainsString('html[data-bo-theme="dark"] .eqm', $css);
        self::assertStringContainsString('padding-left: 1rem; padding-right: 1rem;', $css, 'gouttière de 16 px sur téléphone');
        self::assertStringContainsString('repeat(auto-fill, minmax(min(100%,', $css, 'grille sans débordement horizontal');
    }
}
