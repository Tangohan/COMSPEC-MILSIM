<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\UnitSpaceTabs;
use PHPUnit\Framework\TestCase;

/**
 * Espace d'unité (audit du back-office, lot 2, point 2.5, phase 1) : onglets de la fiche d'unité
 * (membres, postes, chaîne de commandement, qualifications) qui reprennent les pages des effectifs filtrées sur l'unité.
 */
final class UnitSpaceTabsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents($this->root . '/' . $relative);
    }

    public function testTabsAndDefault(): void
    {
        self::assertSame(['fiche', 'postes', 'membres', 'chaine', 'qualifications'], UnitSpaceTabs::keys());
        self::assertSame('fiche', UnitSpaceTabs::DEFAULT);
        self::assertSame('Chaîne de commandement', UnitSpaceTabs::label('chaine'));
        self::assertFalse(UnitSpaceTabs::isKnown('exports'));
    }

    public function testEveryTabKeepsTheRightOfItsOriginPageAndAddsNone(): void
    {
        // Fiche et postes : la page d'unité d'origine, ouverte à qui passe sa route.
        self::assertTrue(UnitSpaceTabs::allows('fiche', false));
        self::assertTrue(UnitSpaceTabs::allows('postes', false));
        // Membres, chaîne, qualifications : le droit des pages Effectifs correspondantes.
        foreach (['membres', 'chaine', 'qualifications'] as $key) {
            self::assertFalse(UnitSpaceTabs::allows($key, false), $key);
            self::assertTrue(UnitSpaceTabs::allows($key, true), $key);
        }
        self::assertFalse(UnitSpaceTabs::allows('inconnu', true));
        self::assertSame(['fiche', 'postes'], UnitSpaceTabs::visible(false));
        self::assertSame(UnitSpaceTabs::keys(), UnitSpaceTabs::visible(true));
    }

    public function testResolveFallsBackToTheSheetAndNeverOpensAClosedTab(): void
    {
        self::assertSame(['tab' => 'fiche', 'denied' => ''], UnitSpaceTabs::resolve('', true));
        self::assertSame(['tab' => 'fiche', 'denied' => ''], UnitSpaceTabs::resolve('nimporte-quoi', true));
        self::assertSame(['tab' => 'membres', 'denied' => ''], UnitSpaceTabs::resolve('membres', true));
        self::assertSame(['tab' => 'postes', 'denied' => ''], UnitSpaceTabs::resolve(' postes ', false));
        self::assertSame(['tab' => 'fiche', 'denied' => 'qualifications'], UnitSpaceTabs::resolve('qualifications', false));
    }

    public function testOldAddressStaysTheSheetAndTabsUseTheOngletParameter(): void
    {
        self::assertSame('back-office/organisation/unites/12', UnitSpaceTabs::path(12));
        self::assertSame('back-office/organisation/unites/12', UnitSpaceTabs::path(12, 'fiche'));
        self::assertSame('back-office/organisation/unites/12?onglet=membres', UnitSpaceTabs::path(12, 'membres'));
        self::assertSame('back-office/organisation/unites/12?onglet=membres&page=3', UnitSpaceTabs::path(12, 'membres', 3));
        self::assertSame('back-office/organisation/unites/12?onglet=chaine', UnitSpaceTabs::path(12, 'chaine', 1));
        self::assertSame('back-office/organisation/unites/1', UnitSpaceTabs::path(0, 'tout-et-nimporte-quoi'));
    }

    public function testPaginationIsBoundedAndClamped(): void
    {
        self::assertSame(['page' => 1, 'pages' => 1, 'offset' => 0, 'per' => 25], UnitSpaceTabs::paginate(null, 0));
        self::assertSame(['page' => 1, 'pages' => 1, 'offset' => 0, 'per' => 25], UnitSpaceTabs::paginate('abc', 10));
        self::assertSame(['page' => 2, 'pages' => 2, 'offset' => 25, 'per' => 25], UnitSpaceTabs::paginate('2', 26));
        // Page hors limites : ramenée à la dernière, jamais d'offset négatif.
        self::assertSame(['page' => 4, 'pages' => 4, 'offset' => 75, 'per' => 25], UnitSpaceTabs::paginate(99, 100));
        self::assertSame(['page' => 1, 'pages' => 4, 'offset' => 0, 'per' => 25], UnitSpaceTabs::paginate(-5, 100));
        self::assertSame(400, UnitSpaceTabs::MEMBER_CAP);
    }

    public function testControllerResolvesTheTabAndComputesOnlyThatTab(): void
    {
        $ctrl = $this->read('app/Controllers/Admin/Organization/UnitSheetController.php');
        $show = substr($ctrl, (int) strpos($ctrl, 'public function show('), (int) strpos($ctrl, 'public function update('));

        self::assertStringContainsString("UnitSpaceTabs::resolve(\$request->queryString('onglet', '')", $show);
        self::assertStringContainsString('EffectifsLmsAccess::allows($gate)', $show);
        self::assertStringContainsString("Response::view('layout.main', \$vars)", $show);
        self::assertStringContainsString("'content' => 'admin.organization.unit_sheet'", $show);

        // Calcul paresseux : les services d'un onglet ne sont appelés que dans la branche de cet onglet.
        $switch = (int) strpos($show, 'switch ($tab)');
        self::assertGreaterThan(0, $switch);
        foreach (['->members(', '->chain(', '->qualifications(', '->listForUnit(', '->listTypesForTenant(', 'OrgAdequacyService'] as $needle) {
            self::assertGreaterThan($switch, (int) strpos($show, $needle), $needle . ' doit être calculé dans la branche de son onglet');
        }
        foreach (['case \'postes\':', 'case \'membres\':', 'case \'chaine\':', 'case \'qualifications\':', 'default:'] as $branch) {
            self::assertStringContainsString($branch, $show);
        }
        // Les postes enregistrés reviennent sur l'onglet Postes ; aucune route ni aucun droit nouveau.
        self::assertStringContainsString("UnitSpaceTabs::path(\$unitId, 'postes')", $ctrl);
        self::assertStringContainsString('#onglet-postes', $ctrl);
        self::assertStringNotContainsString('organization.units.space', $ctrl);
    }

    public function testNoNewRouteAndOldAddressesStayInPlace(): void
    {
        $routes = $this->read('routes/web.php');
        foreach ([
            "'/back-office/organisation/unites/{id}', [UnitSheetController::class, 'show']",
            "'/back-office/organisation/unites/{id}', [UnitSheetController::class, 'update']",
            "'/back-office/organisation/unites/{id}/activites'",
            "'/back-office/organisation/unites/{id}/postes'",
            "'/back-office/ressources/effectifs', [EffectifsWorkspaceController::class, 'roster']",
            "'/back-office/ressources/effectifs/chaine', [EffectifsWorkspaceController::class, 'commandChain']",
            "'/back-office/ressources/effectifs/qualifications', [EffectifsWorkspaceController::class, 'qualifications']",
        ] as $route) {
            self::assertStringContainsString($route, $routes);
        }
        self::assertSame(0, substr_count($routes, 'UnitSpace'));
    }

    public function testPanelsReuseTheServicesOfTheOriginPages(): void
    {
        $panels = $this->read('app/Services/Organization/UnitSpacePanels.php');
        // Membres : même périmètre que le filtre « unité » du tableur (l'unité et ses descendants), mêmes lignes.
        self::assertStringContainsString('expandUnitIdsWithDescendants', $panels);
        self::assertStringContainsString('listActiveUserIdsForUnits', $panels);
        self::assertStringContainsString('listEffectifsRosterByIds', $panels);
        // Chaîne : le service de la page Chaîne de commandement et l'adéquation de la fiche d'unité.
        self::assertStringContainsString('PersonnelCommandChainService', $panels);
        self::assertStringContainsString('OrgAdequacyView::forUnit', $panels);
        // Qualifications : la matrice des effectifs, calculée pour la page de membres seulement.
        self::assertStringContainsString('->matrix($tenantId, $ids)', $panels);
        // Bornes : plafond de membres chargés et pagination.
        self::assertStringContainsString('UnitSpaceTabs::MEMBER_CAP', $panels);
        self::assertStringContainsString('UnitSpaceTabs::paginate', $panels);

        $service = $this->read('app/Services/Personnel/QualificationAwardRuleService.php');
        self::assertStringContainsString('public function matrix(int $tenantId, ?array $onlyUserIds = null): array', $service);
        self::assertStringContainsString('array_intersect_key($ctx[\'members\'], $keep)', $service);
        // La page Effectifs › Qualifications appelle toujours la matrice complète.
        $effectifs = $this->read('app/Controllers/Admin/EffectifsWorkspaceController.php');
        self::assertStringContainsString('$service->matrix($tenantId)', $effectifs);
    }

    public function testViewsRenderTabsAsPlainLinksWithoutEyebrowOrPopup(): void
    {
        $sheet = $this->read('views/admin/organization/unit_sheet.php');
        self::assertStringContainsString('aria-label="Espace de l’unité"', $sheet);
        self::assertStringContainsString('UnitSpaceTabs::path($unitIdNow, $tabKey)', $sheet);
        self::assertStringContainsString("aria-current=\"true\"", $sheet);
        self::assertStringContainsString("require __DIR__ . '/unit_space/' . \$unitSpaceTab . '.php'", $sheet);
        self::assertStringContainsString('role="status"', $sheet);
        self::assertStringNotContainsString('Fiche unité</p>', $sheet, 'Pas de surtitre au-dessus du titre.');
        self::assertStringNotContainsString('<script', $sheet, 'Les onglets fonctionnent sans JavaScript.');

        foreach (['fiche', 'postes', 'membres', 'chaine', 'qualifications'] as $tab) {
            $file = $this->root . '/views/admin/organization/unit_space/' . $tab . '.php';
            self::assertFileExists($file);
            $src = (string) file_get_contents($file);
            self::assertStringNotContainsString('toast', strtolower($src), $tab);
            self::assertStringNotContainsString('alert(', $src, $tab);
            self::assertSame(0, preg_match('/(?:class|id)="[^"]*\b(?:eyebrow|kicker|overline|surtitre)\b/i', $src), $tab);
        }

        // Anciennes ancres de la fiche conservées.
        $fiche = $this->read('views/admin/organization/unit_space/fiche.php');
        self::assertStringContainsString('id="postes"', $fiche);
        self::assertStringContainsString('id="journal"', $fiche);
        self::assertStringContainsString('id="adequation"', $fiche);
        self::assertStringContainsString('Identité', $fiche);
        self::assertStringContainsString('Documents', $fiche);

        $postes = $this->read('views/admin/organization/unit_space/postes.php');
        self::assertStringContainsString('Postes (TO&amp;E)', $postes);
        self::assertStringContainsString('id="postes"', $postes);
        self::assertStringContainsString('name="new_title"', $postes);
    }

    public function testQualificationsTabEmbedsTheExistingMatrixView(): void
    {
        $tab = $this->read('views/admin/organization/unit_space/qualifications.php');
        self::assertStringContainsString("Response::view('admin.effectifs_workspace.qualifications'", $tab);
        self::assertStringContainsString("'qmxEmbedded' => true", $tab);

        $view = $this->read('views/admin/effectifs_workspace/qualifications.php');
        self::assertStringContainsString('empty($qmxEmbedded)', $view);
        // La page d'origine garde son titre.
        self::assertStringContainsString('<h2>Qualifications</h2>', $view);

        $ctrl = $this->read('app/Controllers/Admin/Organization/UnitSheetController.php');
        self::assertStringContainsString("'qualifications-matrix.css'", $ctrl);
    }

    public function testMembersAndChainTabsLinkBackToTheFilteredOriginPages(): void
    {
        $members = $this->read('views/admin/organization/unit_space/membres.php');
        self::assertStringContainsString("effectifs_workspace_url() . '?unite=' . \$unitIdNow", $members);
        self::assertStringContainsString("effectifs_workspace_url('membres/'", $members);
        self::assertStringContainsString('rel="next"', $members);
        self::assertStringContainsString('rel="prev"', $members);

        $chain = $this->read('views/admin/organization/unit_space/chaine.php');
        self::assertStringContainsString("effectifs_workspace_url('chaine')", $chain);
        // L'écriture reste sur la page d'origine : aucun formulaire dans l'onglet.
        self::assertStringNotContainsString('<form', $chain);
        self::assertStringNotContainsString('<form', $members);
    }
}
