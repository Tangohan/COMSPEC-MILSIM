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
        $routes = (string) file_get_contents(self::ROOT . 'routes/web.php');
        $hub = (string) file_get_contents(self::ROOT . 'app/Services/Jnet/JnetMemberHub.php');
        preg_match_all("/\['[^']+', '[^']+', '([a-z\-\/]+)'/", $hub, $m);
        self::assertGreaterThan(0, count($m[1]));
        foreach (array_unique($m[1]) as $path) {
            self::assertStringContainsString("'/" . $path . "'", $routes, $path);
        }
        self::assertStringContainsString("'/jnet/espaces'", $routes);
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
