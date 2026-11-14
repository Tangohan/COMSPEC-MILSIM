<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Services\Effectifs\HrRequestsDesk;
use App\Support\BackOfficeLegacyRedirects;
use App\Support\BackOfficeTabs;
use PHPUnit\Framework\TestCase;

/**
 * Demandes RH (audit du back-office, lot 2.3, RD-13) : une page à filtres par type, avec l'avis du circuit dans la ligne.
 * Les anciens écrans deviennent des onglets (301 en un saut) ; les droits de décision restent ceux de chaque type.
 */
final class HrRequestsPageTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function src(string $relative): string
    {
        return (string) file_get_contents($this->root() . '/' . $relative);
    }

    /** @param list<string> $permissions */
    private function visible(array $permissions): array
    {
        $gate = new Gate();
        $gate->setPermissions($permissions);

        return HrRequestsDesk::visibleTypes($gate);
    }

    public function testNobodyWithoutRightsSeesAnyType(): void
    {
        self::assertSame([], $this->visible([]));
        self::assertSame([], $this->visible(['personnel.profile.view']));
    }

    public function testAnAdministratorSeesTheThreeTypes(): void
    {
        self::assertSame(
            [HrRequestsDesk::TYPE_ELEVATION, HrRequestsDesk::TYPE_CORRECTION, HrRequestsDesk::TYPE_MOBILITY],
            $this->visible(['admin.organization'])
        );
    }

    public function testAGradesManagerSeesCorrectionsAndMobilityButNotElevations(): void
    {
        // Même règle que l'écran des élévations : gestion des rôles ou des statuts exigée.
        self::assertSame(
            [HrRequestsDesk::TYPE_CORRECTION, HrRequestsDesk::TYPE_MOBILITY],
            $this->visible(['personnel.grades.manage'])
        );
    }

    public function testARolesManagerSeesElevationsAndMobilityButNotCorrections(): void
    {
        self::assertSame(
            [HrRequestsDesk::TYPE_ELEVATION, HrRequestsDesk::TYPE_MOBILITY],
            $this->visible(['admin.roles.manage'])
        );
    }

    public function testAStatusManagerSeesTheThreeTypes(): void
    {
        self::assertSame(
            [HrRequestsDesk::TYPE_ELEVATION, HrRequestsDesk::TYPE_CORRECTION, HrRequestsDesk::TYPE_MOBILITY],
            $this->visible(['personnel.status.manage'])
        );
    }

    public function testElevationRowShowsTheProposalAndTheCircuitOpinion(): void
    {
        $row = HrRequestsDesk::elevationRow(
            ['id' => 7, 'kind' => 'grade', 'target_display_name' => 'Cpl Diaz', 'created_at' => '2026-11-02 10:00:00'],
            ['grade' => 'SGT', 'role' => 'Adjoint RH', 'job_role' => null, 'unit' => null],
            [
                'state' => 'opinion_done',
                'assignee_label' => 'Cne Marin',
                'assignee_role' => 'unit_commander',
                'opinion' => 'favorable',
                'opinions' => [['opinion' => 'favorable', 'comment' => '', 'at' => '', 'label' => 'Cne Marin', 'role' => 'unit_commander']],
            ]
        );
        self::assertSame('elevation', $row['type']);
        self::assertSame('elevations', $row['tab']);
        self::assertSame('Cpl Diaz', $row['member']);
        self::assertSame('Grade « SGT » · Rôle « Adjoint RH »', $row['request']);
        self::assertSame(['Avis favorable du chef d’unité (Cne Marin)'], $row['opinion']);
    }

    public function testElevationWithoutProposalFallsBackToItsKind(): void
    {
        $row = HrRequestsDesk::elevationRow(['id' => 1, 'kind' => 'droits', 'target_email' => 'a@b.fr'], [], null);
        self::assertSame('Droits d’accès', $row['request']);
        self::assertSame('a@b.fr', $row['member']);
        self::assertSame([], $row['opinion']);
    }

    public function testCorrectionRowKeepsTheFirstChangeAndCountsTheOthers(): void
    {
        $row = HrRequestsDesk::correctionRow(
            ['id' => 3, 'target_display_name' => 'Sdt Roux', 'created_at' => '2026-11-01 08:00:00'],
            ['Section : « 2e » → « 3e »', 'Indicatif : « A » → « B »'],
            null
        );
        self::assertSame('correction', $row['type']);
        self::assertSame('corrections', $row['tab']);
        self::assertSame('Section : « 2e » → « 3e » (+1)', $row['request']);
        self::assertSame('Correction de la fiche', HrRequestsDesk::correctionRow(['id' => 4], [], null)['request']);
    }

    public function testMobilityRowNamesTheTypeAndTheTarget(): void
    {
        $labels = ['unit_change' => 'Changement d’unité'];
        $row = HrRequestsDesk::mobilityRow(
            ['id' => 9, 'request_type' => 'unit_change', 'user_display_name' => 'Sgt Leroy', 'target_unit_name' => 'Appui', 'created_at' => '2026-11-03 09:00:00'],
            $labels,
            ['state' => 'opinion_pending', 'assignee_label' => 'Lt Roy', 'assignee_role' => 'upper', 'opinion' => '', 'opinions' => []]
        );
        self::assertSame('mobility', $row['type']);
        self::assertSame('mobilite', $row['tab']);
        self::assertSame('Changement d’unité : Appui', $row['request']);
        self::assertSame(['Avis attendu de l’échelon supérieur : Lt Roy'], $row['opinion']);
    }

    public function testOpinionLinesCoverRefusalCommentAndSkippedOpinion(): void
    {
        $lines = HrRequestsDesk::opinionLines([
            'state' => 'done',
            'opinion' => 'skipped',
            'opinions' => [['opinion' => 'unfavorable', 'comment' => 'trop tôt', 'label' => 'Cne Marin', 'role' => 'unit_commander']],
        ]);
        self::assertSame(['Avis défavorable du chef d’unité (Cne Marin) : « trop tôt »', 'Décidé sans attendre l’avis demandé'], $lines);
        self::assertSame([], HrRequestsDesk::opinionLines(null));
    }

    public function testOldestRequestsComeFirst(): void
    {
        $rows = HrRequestsDesk::sortOldestFirst([
            ['type' => 'mobility', 'id' => 1, 'created_at' => '2026-11-05 10:00:00'],
            ['type' => 'elevation', 'id' => 2, 'created_at' => '2026-11-01 10:00:00'],
            ['type' => 'correction', 'id' => 3, 'created_at' => '2026-11-03 10:00:00'],
        ]);
        self::assertSame(['elevation', 'correction', 'mobility'], array_column($rows, 'type'));
    }

    public function testOldPagesBecomeFiltersOfTheNewPage(): void
    {
        self::assertSame('back-office/demandes-rh?onglet=elevations', BackOfficeLegacyRedirects::target('back-office/ressources/effectifs/elevations'));
        self::assertSame('back-office/demandes-rh?onglet=corrections', BackOfficeLegacyRedirects::target('back-office/personnel/corrections'));
        self::assertSame('back-office/demandes-rh?onglet=mobilite', BackOfficeLegacyRedirects::target('back-office/ressources/effectifs/mobilite'));
        foreach (HrRequestsDesk::TYPES as $meta) {
            self::assertSame($meta['legacy'], BackOfficeTabs::FAMILIES['back-office/demandes-rh']['tabs'][$meta['tab']]['legacy']);
        }
    }

    public function testEachFilterKeepsItsControllerAndItsGuards(): void
    {
        $tabs = BackOfficeTabs::FAMILIES['back-office/demandes-rh']['tabs'];
        self::assertSame(['App\\Controllers\\Admin\\EffectifsWorkspaceController', 'elevationRequests'], $tabs['elevations']['handler']);
        self::assertSame(['App\\Controllers\\Web\\PersonnelCorrectionController', 'index'], $tabs['corrections']['handler']);
        self::assertSame(['App\\Controllers\\Admin\\RhDossierWorkspaceController', 'mobility'], $tabs['mobilite']['handler']);
        // Mêmes intergiciels qu'avant : organisation pour élévations et mobilité, connexion seule pour les corrections.
        self::assertSame(['App\\Middleware\\AuthMiddleware', 'App\\Middleware\\OrganizationAdminMiddleware'], $tabs['elevations']['middleware']);
        self::assertSame(['App\\Middleware\\AuthMiddleware', 'App\\Middleware\\OrganizationAdminMiddleware'], $tabs['mobilite']['middleware']);
        self::assertSame(['App\\Middleware\\AuthMiddleware'], $tabs['corrections']['middleware']);
    }

    public function testOldListPagesAreNoLongerServedButDecisionsKeepTheirRoutes(): void
    {
        $routes = $this->src('routes/web.php');
        self::assertStringNotContainsString("get('/back-office/personnel/corrections'", $routes);
        self::assertStringNotContainsString("get('/back-office/ressources/effectifs/elevations'", $routes);
        self::assertStringNotContainsString("get('/back-office/ressources/effectifs/mobilite'", $routes);
        self::assertStringContainsString("'/back-office/personnel/corrections/{id}/decide'", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/elevations/{id}/statut'", $routes);
        self::assertStringContainsString("'/back-office/ressources/effectifs/mobilite/{id}/statut'", $routes);
    }

    public function testTheNewPageDecidesNothing(): void
    {
        $controller = $this->src('app/Controllers/Admin/HrRequestsController.php');
        $service = $this->src('app/Services/Effectifs/HrRequestsDesk.php');
        $view = $this->src('views/admin/hr_requests/index.php');

        foreach (['updateStatus(', 'applyDirect(', 'applyApproved', 'resolve(', '->decide', 'approve('] as $needle) {
            self::assertStringNotContainsString($needle, $controller, $needle);
            self::assertStringNotContainsString($needle, $service, $needle);
        }
        self::assertStringNotContainsString('<form', $view);
        self::assertStringNotContainsString('_csrf_token', $view);
        self::assertStringContainsString('Décider', $view);
        self::assertStringContainsString('Avis du circuit', $view);
        // Seuls les types lisibles aujourd'hui sont chargés.
        self::assertStringContainsString('HrRequestsDesk::visibleTypes($gate)', $controller);
    }

    public function testNoEyebrowNoPopupOnTheNewPage(): void
    {
        $view = $this->src('views/admin/hr_requests/index.php');
        foreach (['kicker', 'eyebrow', 'toast', 'systemChat', 'alert('] as $needle) {
            self::assertStringNotContainsString($needle, $view, $needle);
        }
    }

    public function testInternalLinksAndRedirectsPointToTheFiltersDirectly(): void
    {
        foreach ([
            'app/Controllers/Admin/EffectifsWorkspaceController.php' => "BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations')",
            'app/Controllers/Admin/RhDossierWorkspaceController.php' => "BackOfficeTabs::urlFor('back-office/ressources/effectifs/mobilite')",
            'app/Controllers/Web/PersonnelCorrectionController.php' => "BackOfficeTabs::urlFor('back-office/personnel/corrections')",
            'views/admin/effectifs_workspace/elevation_requests.php' => "BackOfficeTabs::urlFor('back-office/ressources/effectifs/elevations'",
            'views/admin/effectifs_workspace/rh_mobility.php' => "BackOfficeTabs::urlFor('back-office/ressources/effectifs/mobilite'",
            'views/personnel/corrections_queue.php' => "BackOfficeTabs::urlFor('back-office/personnel/corrections')",
        ] as $file => $needle) {
            self::assertStringContainsString($needle, $this->src($file), $file);
        }
        self::assertStringNotContainsString("Response::redirect(url('back-office/personnel/corrections'))", $this->src('app/Controllers/Web/PersonnelCorrectionController.php'));
        self::assertStringNotContainsString("Response::redirect(effectifs_workspace_url('mobilite'))", $this->src('app/Controllers/Admin/RhDossierWorkspaceController.php'));
    }

    public function testAbsencesStayOutBecauseTheyHaveNoDecision(): void
    {
        // Une absence est déclarée par le membre (statuts active / cancelled) : rien à décider, donc pas de filtre.
        $types = array_keys(HrRequestsDesk::TYPES);
        self::assertSame(['elevation', 'correction', 'mobility'], $types);
    }
}
