<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Core\Request;
use App\Support\BackOfficeRightGuard;
use PHPUnit\Framework\TestCase;

/**
 * Audit du back-office, lot 4 (S6) : les contrôleurs servis derrière le seul OrganizationAdminMiddleware, qui laisse entrer
 * `site.support`, vérifient eux-mêmes un droit, en lecture comme en écriture.
 */
final class SecurityAuditLot4Test extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    private const ORG = 'app/Controllers/Admin/Organization/';

    /** Contrôleur => droits qui s'ajoutent à « administrer la communauté ou les accès » (null : rien de plus). */
    private const GUARDED = [
        self::ORG . 'TenantInitialSetupController.php' => null,
        self::ORG . 'OrganizationCommunityController.php' => null,
        self::ORG . 'MissionsPortalController.php' => null,
        self::ORG . 'CategoryAdminController.php' => null,
        self::ORG . 'GradeReferentielController.php' => null,
        self::ORG . 'RankCatalogAdminController.php' => null,
        self::ORG . 'QualificationRulesController.php' => null,
        self::ORG . 'QualificationPrerequisiteController.php' => null,
        self::ORG . 'CompetencyMatrixController.php' => null,
        self::ORG . 'GroupAdminController.php' => null,
        'app/Controllers/Admin/AdminUnitsController.php' => null,
        self::ORG . 'TeamAdminController.php' => null,
        self::ORG . 'PersonnelPhaseAdminController.php' => null,
        self::ORG . 'AtakMarkerDetectionAdminController.php' => null,
        self::ORG . 'RoleplayGameSessionAdminController.php' => null,
        self::ORG . 'OrganizationSeniorityAdminController.php' => null,
        self::ORG . 'PersonnelPassAdminController.php' => null,
        self::ORG . 'TenantAlertsController.php' => null,
        self::ORG . 'TenantMiniArticlesController.php' => null,
        self::ORG . 'OrganizationAuditController.php' => ['admin.audit.view'],
        self::ORG . 'AwardReferentielController.php' => ['personnel.awards.manage', 'personnel.badges.manage'],
        self::ORG . 'EquipmentReferentielController.php' => ['personnel.equipment.manage', 'personnel.assignments.manage'],
        'app/Controllers/Admin/AdminRecruitmentDiscordQuestionsController.php' => ['organization.recruitment.manage'],
        self::ORG . 'RecruitmentInviteCodesController.php' => ['organization.recruitment.manage'],
        self::ORG . 'RecruitmentOffersController.php' => ['organization.recruitment.openings.manage', 'organization.recruitment.manage'],
    ];

    /** Actions publiques qui ne font que rediriger (aucune donnée) : volontairement sans garde. */
    private const REDIRECT_ONLY = [
        self::ORG . 'OrganizationCommunityController.php' => ['settings', 'settingsUpdate', 'onboardingMembers'],
    ];

    private static function src(string $path): string
    {
        return (string) file_get_contents(self::ROOT . $path);
    }

    /** @return array<string, string> action => corps jusqu'à l'action suivante */
    private static function actions(string $source): array
    {
        $parts = preg_split('/^    public function (\w+)\(/m', str_replace("\r\n", "\n", $source), -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = [];
        for ($i = 1, $n = count($parts); $i < $n; $i += 2) {
            if ($parts[$i] !== '__construct') {
                $out[$parts[$i]] = $parts[$i + 1];
            }
        }

        return $out;
    }

    /** Le garde par défaut est celui de la navigation, sans l'assistance. */
    public function testGuardDefaultsToTheCommunityManagersAndNeverToSupport(): void
    {
        self::assertSame(['admin.organization', 'admin.access'], BackOfficeRightGuard::MANAGERS);
        $guard = self::src('app/Support/BackOfficeRightGuard.php');
        self::assertStringNotContainsString("'site.support'", $guard);
        $trait = self::src('app/Support/GuardsBackOfficeRight.php');
        self::assertStringContainsString('return BackOfficeRightGuard::MANAGERS;', $trait);
        self::assertStringContainsString('BackOfficeRightGuard::deniedUnless($request, $this->backOfficeRights())', $trait);
    }

    /** Chaque action du contrôleur commence par le garde, lecture comme écriture, et le contrôleur utilise le trait. */
    public function testEveryActionOfTheListedControllersStartsWithTheGuard(): void
    {
        foreach (array_keys(self::GUARDED) as $path) {
            $s = self::src($path);
            self::assertStringContainsString('use GuardsBackOfficeRight', $s, $path);
            $actions = self::actions($s);
            self::assertGreaterThan(0, count($actions), $path);
            foreach ($actions as $name => $body) {
                if (in_array($name, self::REDIRECT_ONLY[$path] ?? [], true)) {
                    self::assertStringNotContainsString('Response::view(', $body, $path . '::' . $name . ' ne doit que rediriger');
                    continue;
                }
                $head = substr($body, 0, 400);
                self::assertStringContainsString('if (($denied = $this->guardBackOfficeRight($request)) !== null) {', $head, $path . '::' . $name);
                self::assertStringContainsString('return $denied;', $head, $path . '::' . $name);
            }
        }
    }

    /** Les droits fins que le middleware accepte déjà sur le préfixe restent acceptés ; rien d'autre n'est ajouté. */
    public function testFineRightsAreKeptExactlyAsTheMiddlewareOpensThem(): void
    {
        foreach (self::GUARDED as $path => $extra) {
            $s = self::src($path);
            if ($extra === null) {
                self::assertStringNotContainsString('function backOfficeRights()', $s, $path);
                continue;
            }
            self::assertStringContainsString('function backOfficeRights(): array', $s, $path);
            $list = implode(', ', array_map(static fn (string $r): string => "'" . $r . "'", $extra));
            self::assertStringContainsString('return [...BackOfficeRightGuard::MANAGERS, ' . $list . '];', $s, $path);
        }
        $mw = self::src('app/Middleware/OrganizationAdminMiddleware.php');
        foreach (['personnel.awards.manage', 'personnel.badges.manage', 'personnel.equipment.manage', 'personnel.assignments.manage', 'organization.recruitment.manage', 'organization.recruitment.openings.manage', 'admin.audit.view'] as $right) {
            self::assertStringContainsString("'" . $right . "'", $mw, $right . ' doit rester ouvert par le middleware, sinon le contrôleur ne le voit jamais');
        }
    }

    /**
     * Gate::allows('site.support') n'est vrai que pour un administrateur de la plateforme (slug réservé, jamais accordé par un
     * rôle) : le droit « assistance » du middleware n'ouvre donc rien à un compte d'assistance sans autre droit, et les gardes
     * n'ont pas à le citer.
     */
    public function testSupportSlugIsReservedSoItNeverGrantsAccessByItself(): void
    {
        self::assertContains('site.support', \App\Authorization\SystemReservedPermissions::EXACT);
        $gate = Gate::getInstance();
        try {
            $gate->setPermissions(['site.support']);
            self::assertFalse($gate->allows('site.support'));
            self::assertFalse(BackOfficeRightGuard::allows(BackOfficeRightGuard::MANAGERS));
        } finally {
            $gate->setPermissions([]);
        }
    }

    /** Les groupes servent aussi /formation/groupes (droit de formation) : le droit de gestion ne s'y applique pas. */
    public function testGroupActionsKeepTheTrainingRoutesOutsideTheManagerRight(): void
    {
        $s = self::src(self::ORG . 'GroupAdminController.php');
        self::assertStringContainsString('guardBackOfficeRight as private guardManagerRight;', $s);
        self::assertStringContainsString("return str_starts_with(\$request->path(), '/back-office') ? \$this->guardManagerRight(\$request) : null;", $s);
    }

    /** La traçabilité du courrier demande le droit du menu (courrier.view) ou l'administration, sans rediriger vers la page admin. */
    public function testCourrierTraceabilityNeedsCourrierViewOrAManager(): void
    {
        $s = self::src('app/Controllers/Courrier/CourrierDashboardController.php');
        $body = self::actions($s)['traceability'];
        self::assertStringContainsString("BackOfficeRightGuard::deniedUnless(\$request, ['courrier.view', ...BackOfficeRightGuard::MANAGERS], url('courrier'))", substr($body, 0, 400));
        $nav = self::src('config/navigation.php');
        self::assertStringContainsString("'path' => 'courrier/traceabilite', 'permission' => 'courrier.view'", $nav);
        $routes = self::src('routes/web.php');
        self::assertStringContainsString("\$router->get('/courrier/traceabilite', [CourrierDashboardController::class, 'traceability'], \$mwCourrier);", $routes);
    }

    /** Le modérateur (admin.members.moderate seul) entre sur /back-office/moderation et sur rien d'autre grâce à ce droit. */
    public function testModeratorReachesSanctionsPageThroughAPathException(): void
    {
        $mw = self::src('app/Middleware/OrganizationAdminMiddleware.php');
        self::assertStringContainsString("(\$path === '/back-office/moderation' || str_starts_with(\$path, '/back-office/moderation/')) && \$gate->allows('admin.members.moderate')", str_replace("\r\n", "\n", $mw));
        $routes = self::src('routes/web.php');
        self::assertStringContainsString('$mwTenantMemberModeration = [AuthMiddleware::class, OrganizationAdminMiddleware::class, TenantMemberModerationMiddleware::class];', $routes);
        self::assertStringContainsString("allows('admin.members.moderate')", self::src('app/Middleware/TenantMemberModerationMiddleware.php'));
    }

    /** Comportement du garde : l'assistance seule est refusée, les gestionnaires et les droits fins déclarés passent. */
    public function testGuardRefusesSupportOnlyAndAdmitsTheRightHolders(): void
    {
        $_SERVER['HTTP_SEC_FETCH_DEST'] = 'empty';
        $_SERVER['REQUEST_URI'] = '/back-office/categories';
        $request = new Request();
        $gate = Gate::getInstance();
        try {
            $gate->setPermissions(['site.support']);
            $denied = BackOfficeRightGuard::deniedUnless($request);
            self::assertNotNull($denied);
            self::assertSame(403, $denied->statusCode());

            $gate->setPermissions(['personnel.awards.manage']);
            self::assertNotNull(BackOfficeRightGuard::deniedUnless($request));
            self::assertNull(BackOfficeRightGuard::deniedUnless($request, [...BackOfficeRightGuard::MANAGERS, 'personnel.awards.manage']));

            foreach (['admin.organization', 'admin.access'] as $manager) {
                $gate->setPermissions([$manager]);
                self::assertNull(BackOfficeRightGuard::deniedUnless($request), $manager);
            }

            $gate->setPermissions([]);
            $_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
            $page = BackOfficeRightGuard::deniedUnless(new Request());
            self::assertNotNull($page);
            self::assertSame(302, $page->statusCode());
        } finally {
            $gate->setPermissions([]);
            unset($_SERVER['HTTP_SEC_FETCH_DEST'], $_SERVER['REQUEST_URI'], $_SESSION['_flash']);
        }
    }
}
