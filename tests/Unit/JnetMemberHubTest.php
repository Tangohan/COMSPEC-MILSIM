<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Jnet\JnetMemberHub as H;
use App\Services\Jnet\JnetUnitSpaceSettings;
use App\Support\JnetSpaceTree;
use App\Support\PortalAccessChoice;
use PHPUnit\Framework\TestCase;

/** JNET : extranet du membre (« Une unité. Un espace. ») et arrivée par défaut des membres. */
final class JnetMemberHubTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private function tree(): JnetSpaceTree
    {
        return JnetSpaceTree::fromRows([
            [1, 0, 'Hydra'],
            [2, 1, 'Alpha'],
            [3, 0, 'Sierra'],
        ], 'Organisation');
    }

    public function testRightsFollowMembershipAndCommand(): void
    {
        $t = $this->tree();
        self::assertSame(H::RIGHT_ADMIN, H::rightFor($t, 3, [], [], true)['key']);
        self::assertSame(H::RIGHT_CHIEF, H::rightFor($t, 1, [1], [1], false)['key']);
        // Le chef de l'unité commande aussi ses sous-unités.
        self::assertSame(H::RIGHT_CHIEF, H::rightFor($t, 2, [1], [1], false)['key']);
        self::assertSame(H::RIGHT_WRITE, H::rightFor($t, 2, [1], [], false)['key']);
        self::assertSame(H::RIGHT_WRITE, H::rightFor($t, 1, [2 - 1], [], false)['key']);
        // Membre d'une sous-unité : lecture seule sur l'unité au-dessus et sur les unités latérales.
        self::assertSame(H::RIGHT_READ, H::rightFor($t, 1, [2], [], false)['key']);
        self::assertSame(H::RIGHT_READ, H::rightFor($t, 3, [1], [], false)['key']);
        self::assertSame('Lecture seule', H::rightFor($t, 3, [], [], false)['label']);
    }

    public function testRegisterSplitsOwnUnitsFromOthersAndCarriesUnread(): void
    {
        $reg = H::register($this->tree(), [2], [], false, [2 => 3, 3 => 1], [3 => ['accent_color' => '#60a5fa'] + JnetUnitSpaceSettings::defaults()]);
        self::assertSame([2], array_column($reg['mine'], 'id'));
        self::assertSame([1, 3], array_column($reg['others'], 'id'));
        self::assertSame(3, $reg['mine'][0]['unread']);
        self::assertSame('Hydra', $reg['mine'][0]['parent']);
        self::assertSame('#60a5fa', $reg['others'][1]['accent']);
        self::assertSame(0, $reg['others'][0]['unread']);
        self::assertTrue(str_ends_with($reg['others'][1]['href'], 'jnet/u/3'));
    }

    public function testRegisterCarriesTheHierarchyAndAReadableSubline(): void
    {
        $tree = JnetSpaceTree::fromOrbat(['unitId' => 0, 'children' => [
            ['unitId' => 1, 'label' => 'Hydra', 'role' => 'SOAR-B-A-1', 'echelon' => 'bataillon', 'symbolFunction' => 'infantry_light', 'leader' => 'LCL Marchetti', 'strength' => 34, 'children' => [
                ['unitId' => 2, 'label' => 'Alpha', 'role' => 'XX-9', 'echelon' => 'compagnie', 'leader' => '—', 'strength' => 18, 'children' => []],
            ]],
        ]], 'Organisation');
        $reg = H::register($tree, [2], [], false, [], [], [1 => ['total' => 34, 'available' => 27], 2 => ['total' => 18, 'available' => 12]]);

        // Toutes les unités visibles, dans l'ordre de la hiérarchie, avec leur profondeur et leur filiation.
        self::assertSame([1, 2], array_column($reg['tree'], 'id'));
        self::assertSame([1, 2], array_column($reg['tree'], 'depth'));
        self::assertSame(['Organisation', 'Hydra'], $reg['tree'][1]['chain']);
        self::assertSame('Hydra', $reg['tree'][1]['parent']);

        // Sous le nom : échelon et fonction lisibles, chef, effectif disponible sur total, parent. Jamais le code brut.
        self::assertSame('Bataillon · Infanterie légère', $reg['tree'][0]['kind']);
        self::assertSame(['Chef : LCL Marchetti', '27 / 34 disponibles'], $reg['tree'][0]['facts']);
        self::assertSame('Compagnie', $reg['tree'][1]['kind']);
        self::assertSame(['12 / 18 disponibles', 'Sous Hydra'], $reg['tree'][1]['facts']);
        self::assertStringNotContainsString('SOAR-B', (string) json_encode($reg['tree']));
        self::assertStringNotContainsString('XX-9', (string) json_encode($reg['tree']));
        // Chaque unité a une photo de survol, la même à chaque fois.
        self::assertSame($reg['tree'][0]['photo'], H::register($tree, [], [], false, [], [])['tree'][0]['photo']);
    }

    public function testDescribeFallsBackToUnitAndMembersWithoutAvailability(): void
    {
        $d = H::describe(['echelon' => '', 'function' => '', 'leader' => ''], 0, 12, '', false);
        self::assertSame('Unité', $d['kind']);
        self::assertSame(['12 membres'], $d['facts']);
        self::assertSame(['1 membre'], H::describe([], 0, 1, '', false)['facts']);
        self::assertSame([], H::describe([], 0, 0, '', false)['facts']);
    }

    /** Une rubrique par onglet, pilotée par l'ancre ; sans JavaScript toutes les rubriques restent affichées. */
    public function testHubIsSplitIntoTabsDrivenByTheHashAndWorksWithoutScript(): void
    {
        $view = (string) file_get_contents(self::ROOT . 'views/jnet/hub.php');
        self::assertStringContainsString("'hb-' . \$section['key']", $view);
        self::assertStringContainsString('id="hb-autres"', $view);
        self::assertStringContainsString('data-hb-tabs', $view);
        self::assertStringContainsString('data-hb-panel', $view);
        self::assertStringContainsString('Autres unités', $view);
        self::assertStringContainsString('hb-reg-h', $view);
        // Le HTML livre toutes les rubriques ; seul le script active le mode onglets.
        self::assertStringNotContainsString('hb-js', $view);
        self::assertStringNotContainsString('<section class="hb-panel" hidden', $view);
        $css = (string) file_get_contents(self::ROOT . 'public/assets/css/jnet_hub.css');
        self::assertStringContainsString('.hb-js .hb-panel {', $css);
        self::assertStringNotContainsString("\n.hb-panel {\n  display: none", $css);
        $js = (string) file_get_contents(self::ROOT . 'public/assets/js/jnet_hub.js');
        foreach (['hashchange', 'popstate', 'aria-selected', 'ArrowRight', 'ArrowLeft', 'tabpanel', 'hb-js'] as $needle) {
            self::assertStringContainsString($needle, $js, $needle);
        }
        self::assertStringNotContainsString('alert(', $js);
    }

    public function testRegisterOnlyListsUnitsOfTheFilteredTree(): void
    {
        // Une unité masquée au lecteur n'est pas dans l'arbre : elle ne peut pas apparaître au registre.
        $reg = H::register(JnetSpaceTree::fromRows([[1, 0, 'Hydra']], 'Organisation'), [], [], false, [9 => 4], []);
        self::assertSame([], $reg['mine']);
        self::assertSame([1], array_column($reg['others'], 'id'));
    }

    public function testSectionsLinkExistingPagesAndRespectTheCommunityProfile(): void
    {
        $full = H::sections(['tenantType' => 'full', 'pilotLogbook' => true, 'unreadMail' => 4]);
        self::assertSame(['unite', 'profil', 'atak', 'donnees', 'service'], array_column($full, 'key'));
        $links = [];
        foreach ($full as $s) {
            foreach ($s['items'] as $i) {
                $links[$i['label']] = $i;
            }
        }
        self::assertSame(4, $links['Messagerie']['badge']);
        self::assertArrayHasKey('Mon carnet de vol', $links);
        self::assertTrue(str_ends_with($links['Mon service']['href'], 'mon-service'));

        $withoutPilot = H::sections(['tenantType' => 'full', 'pilotLogbook' => false]);
        $labels = [];
        foreach ($withoutPilot as $s) {
            foreach ($s['items'] as $i) {
                $labels[] = $i['label'];
            }
        }
        self::assertNotContains('Mon carnet de vol', $labels);

        // Le profil « Carte ATAK » n'a pas le module Effectifs : fiche et qualifications disparaissent, ATAK reste.
        $atakOnly = [];
        foreach (H::sections(['tenantType' => 'atak']) as $s) {
            foreach ($s['items'] as $i) {
                $atakOnly[] = $i['label'];
            }
        }
        self::assertContains('Ma liaison ATAK', $atakOnly);
        self::assertNotContains('Ma fiche', $atakOnly);
        self::assertNotContains('Mes qualifications', $atakOnly);
    }

    public function testEveryLinkIsARealRoute(): void
    {
        $all = [];
        foreach (['full', 'effectifs', 'atak'] as $type) {
            foreach (H::sections(['tenantType' => $type, 'pilotLogbook' => true]) as $section) {
                foreach ($section['items'] as $item) {
                    $all[$item['path']] = $item['href'];
                }
            }
        }
        self::assertGreaterThan(0, count($all));
        foreach (array_keys($all) as $path) {
            self::assertNotNull(\Tests\Support\BackOfficeRouteMap::match('GET', '/' . $path), $path . ' : aucune route');
        }
        // Les pages « Ma situation » sont dans JNET, jamais dans le back-office.
        foreach ($all as $path => $href) {
            self::assertStringNotContainsString('back-office', $href);
        }
        self::assertNotNull(\Tests\Support\BackOfficeRouteMap::match('GET', '/jnet/espaces'));
    }

    public function testMembersLandOnJnetAndOthersKeepTheirArrival(): void
    {
        self::assertTrue(PortalAccessChoice::jnetOfferedTo('full'));
        self::assertFalse(PortalAccessChoice::jnetOfferedTo('effectifs'));
        self::assertFalse(PortalAccessChoice::jnetOfferedTo('atak'));

        $choice = (string) file_get_contents(self::ROOT . 'app/Support/PortalAccessChoice.php');
        self::assertMatchesRegularExpression('/isNoOrganizationContext\(\) \|\| self::canAccessTba\(\)\) \{\s+return null;/', $choice);
        $auth = (string) file_get_contents(self::ROOT . 'app/Controllers/Auth/AuthController.php');
        self::assertGreaterThanOrEqual(4, substr_count($auth, 'PortalAccessChoice::memberLandingUrl()'));

        $ctl = (string) file_get_contents(self::ROOT . 'app/Controllers/Web/JnetPortalController.php');
        self::assertStringNotContainsString('!$this->spaces->isOrgWideReader() && (string)', $ctl);
        self::assertStringContainsString("Response::view('jnet.hub'", $ctl);
    }

    public function testHubPageHasNoEyebrowNorPopup(): void
    {
        $view = (string) file_get_contents(self::ROOT . 'views/jnet/hub.php');
        self::assertStringContainsString('Une unité.<br><span>Un espace.</span>', $view);
        self::assertStringNotContainsString('hb-kk', $view);
        self::assertStringNotContainsString('toast', strtolower($view));
        self::assertStringNotContainsString('alert(', $view);
        self::assertTrue(is_file(self::ROOT . 'public/assets/css/jnet_hub.css'));
    }
}
