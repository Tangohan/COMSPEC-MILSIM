<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Services\Portal\BackOfficeSearchService;
use App\Services\Portal\PortalSearchService;
use PHPUnit\Framework\TestCase;

/** Palette Ctrl+K : comptes rendus, heures Overwatch (OVL), carnets… présents et filtrés par les droits. */
final class CommandPaletteDestinationsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
        Gate::getInstance()->setPermissions([]);
    }

    protected function tearDown(): void
    {
        Gate::getInstance()->setPermissions([]);
    }

    /** @return list<string> */
    private function boTitles(array $permissions): array
    {
        $gate = Gate::getInstance();
        $gate->setPermissions($permissions);
        $svc = (new \ReflectionClass(BackOfficeSearchService::class))->newInstanceWithoutConstructor();
        $m = new \ReflectionMethod(BackOfficeSearchService::class, 'pageCatalog');

        return array_column($m->invoke($svc, $gate), 'title');
    }

    /** @return list<string> */
    private function portalTitles(string $q, array $permissions): array
    {
        $gate = Gate::getInstance();
        $gate->setPermissions($permissions);
        $svc = (new \ReflectionClass(PortalSearchService::class))->newInstanceWithoutConstructor();

        return array_column($svc->filterCommands($q, $gate), 'title');
    }

    public function testBackOfficeCatalogListsReportsPlaytimeAndLogbooks(): void
    {
        $staff = $this->boTitles(['admin.organization']);
        foreach (['CR des joueurs (fin d’op)', 'Remontées du terrain', 'Nouveau compte rendu', 'Modèles de comptes rendus', 'Diffusion des rapports', 'Heures de jeu (tableur)', 'Temps d’écran et rôles', 'Mon carnet de vol', 'Cursus pilote', 'Data Inspector'] as $title) {
            self::assertContains($title, $staff, $title);
        }
    }

    public function testBackOfficeCatalogIsPermissionGated(): void
    {
        $none = $this->boTitles([]);
        self::assertNotContains('Heures de jeu (tableur)', $none);
        self::assertNotContains('CR des joueurs (fin d’op)', $none);
        self::assertNotContains('Remontées du terrain', $none);
        // Le carnet de vol est personnel : visible pour quiconque ouvre le back-office.
        self::assertContains('Mon carnet de vol', $none);

        $intel = $this->boTitles(['atak.sse.case.manage']);
        self::assertContains('Remontées du terrain', $intel);
        self::assertNotContains('Heures de jeu (tableur)', $intel);
    }

    public function testPortalPaletteFindsOvlHoursAndCrOnlyForStaff(): void
    {
        self::assertNotContains('Heures de jeu (OVL)', $this->portalTitles('ovl', []));
        self::assertContains('Heures de jeu (OVL)', $this->portalTitles('ovl', ['admin.organization']));
        self::assertContains('Overwatch', $this->portalTitles('ovl', []));
        self::assertContains('CR des joueurs (fin d’op)', $this->portalTitles('compte rendu', ['admin.organization']));
        self::assertContains('Mon carnet de vol', $this->portalTitles('carnet', ['admin.organization']));
        self::assertNotContains('Courrier officiel', $this->portalTitles('courrier', []));
        self::assertContains('Courrier officiel', $this->portalTitles('courrier', ['courrier.view']));
        self::assertContains('Mes formations', $this->portalTitles('mes formations', []));
    }

    public function testSynonymsCoverOvlCrAndCarnet(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Portal/BackOfficeSearchService.php');
        foreach (["'ovl' =>", "'cr' =>", "'carnet' =>", "'spotrep' =>", "'aar' =>"] as $needle) {
            self::assertStringContainsString($needle, $src);
        }
        self::assertStringContainsString('portal_nav_href_permission_allowed', $src);
    }
}
