<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\MemberAdvancementController;
use App\Controllers\Admin\Organization\MemberSituationController;
use App\Controllers\Web\CommunityEventsController;
use App\Controllers\Web\PersonnelController;
use App\Controllers\Web\RhWorkspaceController;
use App\Core\Request;
use App\Core\Session;
use App\Support\JnetMemberBridge;
use App\Support\JnetMemberPaths;
use App\Support\JnetMemberShell;
use PHPUnit\Framework\TestCase;

/**
 * « Ma situation » dans JNET, groupe A : ma fiche (et sa modification), mes démarches, unité, événements, avancement, carrière,
 * décorations, qualifications, coffre, dotation. Les contrôleurs rendent dans la coque JNET, tous les liens, formulaires et retours
 * visent /jnet/moi/*, et chaque vue est RENDUE pour de vrai (données fictives, sans base) : une classe appelée sans « use » ferait
 * ici une erreur, pas en production.
 */
final class JnetMaSituationGroupATest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private ?bool $sessionStarted = null;

    /** @var array<string, mixed> */
    private array $savedSession = [];

    private array $server = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'tenant_id' => 3, 'user_id' => 7, 'callsign' => 'VIPER 1-1', 'display_name' => 'Jean Martin'];
        $GLOBALS['__app_locale'] = 'fr';
        $_GET = $_POST = [];
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        $_SERVER = $this->server;
        $_GET = $_POST = [];
        unset($GLOBALS['__app_locale']);
    }

    private function src(string $relative): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $relative);
    }

    /** Rend une vue avec ses variables, comme la coque la requiert, et rend le HTML. */
    private function render(string $view, array $vars): string
    {
        $file = self::ROOT . '/views/' . str_replace('.', '/', $view) . '.php';
        ob_start();
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__file;
            })($file, $vars);
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    // —— Contrôleurs ——

    public function testGroupControllersAreDeclaredReadyAndRenderInTheJnetShell(): void
    {
        foreach ([PersonnelController::class, RhWorkspaceController::class, MemberAdvancementController::class, MemberSituationController::class] as $class) {
            self::assertTrue(JnetMemberBridge::ready($class), $class . ' doit déclarer JNET_MOI_READY.');
        }
        foreach (['ma-fiche', 'ma-fiche/modifier', 'mes-demarches', 'avancement', 'unite', 'evenements', 'qualifications', 'carriere', 'decorations', 'dotation', 'coffre'] as $slug) {
            self::assertTrue(JnetMemberBridge::ready(JnetMemberPaths::PAGES[$slug]['owner']), $slug);
        }
        foreach ([
            'app/Controllers/Web/PersonnelController.php',
            'app/Controllers/Web/RhWorkspaceController.php',
            'app/Controllers/Admin/Organization/MemberAdvancementController.php',
        ] as $file) {
            self::assertStringContainsString('JnetMemberShell::view($request', $this->src($file), $file);
        }
    }

    public function testNoGroupSourceBuildsTheOldAddressAnymore(): void
    {
        foreach ([
            'app/Controllers/Web/PersonnelController.php',
            'app/Controllers/Web/PersonnelCorrectionController.php',
            'app/Controllers/Web/RhWorkspaceController.php',
            'app/Controllers/Web/CommunityEventsController.php',
            'app/Controllers/Admin/Organization/MemberAdvancementController.php',
            'app/Services/Personnel/OperatorDocumentVaultService.php',
            'views/admin/member_situation/unite.php',
            'views/admin/member_situation/evenements.php',
            'views/admin/member_situation/avancement.php',
            'views/admin/member_situation/carriere.php',
            'views/admin/member_situation/decorations.php',
            'views/admin/member_situation/qualifications.php',
            'views/admin/member_situation/coffre.php',
            'views/admin/member_situation/dotation.php',
            'views/personnel/file.php',
            'views/personnel/edit.php',
            'views/personnel/rh_workspace.php',
            'views/partials/personnel/advancement_panel.php',
            'views/partials/personnel/file_page_notices.php',
            'views/community/events.php',
        ] as $file) {
            // Les commentaires peuvent citer l'ancienne adresse ; le code, jamais, sauf la compatibilité d'un retour de formulaire.
            $code = (string) preg_replace('#/\*.*?\*/|^\s*//.*$|^\s*\*.*$#ms', '', $this->src($file));
            if ($file === 'app/Controllers/Web/CommunityEventsController.php') {
                $code = str_replace(["'back-office/ma-situation/evenements'", "'ma-situation/evenements'"], '', $code);
            }
            self::assertStringNotContainsString('back-office/ma-situation', $code, $file);
        }
    }

    public function testRedirectsAndConstantsPointToTheMemberSpace(): void
    {
        self::assertSame('jnet/moi/mes-demarches', RhWorkspaceController::WORKSPACE_PATH);
        $adv = $this->src('app/Controllers/Admin/Organization/MemberAdvancementController.php');
        self::assertSame(11, substr_count($adv, "jnet_moi_url('avancement')"));
        $person = $this->src('app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString("jnet_moi_url('ma-fiche/modifier')", $person);
        self::assertStringContainsString("jnet_moi_url('ma-fiche')", $person);
        self::assertStringContainsString("jnet_moi_url('ma-fiche/modifier') . '#edit-orbat'", $this->src('app/Controllers/Web/PersonnelCorrectionController.php'));
        self::assertStringContainsString("jnet_moi_url('qualifications/' . \$id . '/brevet')", $this->src('app/Services/Personnel/OperatorDocumentVaultService.php'));
    }

    public function testTheOldWorkshopAddressOfDemarchesStillLeadsToTheNewPage(): void
    {
        $src = $this->src('app/Controllers/Web/RhWorkspaceController.php');
        self::assertStringContainsString('!JnetMemberPaths::isMemberPath($request->path())', $src);
        self::assertStringContainsString('Response::redirect(url(self::WORKSPACE_PATH))', $src);
        // Les formulaires de démarches gardent leurs adresses (hors back-office) et reviennent dans JNET.
        self::assertStringContainsString("url('personnel/mon-espace-rh/absences')", $this->src('views/personnel/rh_workspace.php'));
    }

    public function testEventAnswersComeBackToTheJnetListForOldAndNewForms(): void
    {
        $controller = (new \ReflectionClass(CommunityEventsController::class))->newInstanceWithoutConstructor();
        foreach (['jnet', 'bo', 'back-office/ma-situation/evenements'] as $returnTo) {
            $_POST = ['return_to' => $returnTo];
            self::assertSame(jnet_moi_url('evenements'), $controller->listRedirectUrl(new Request()), $returnTo);
        }
        $_POST = [];
        $_SERVER['HTTP_REFERER'] = 'https://exemple.test/jnet/moi/evenements?vue=calendrier';
        self::assertSame(jnet_moi_url('evenements'), $controller->listRedirectUrl(new Request()));
        $_SERVER['HTTP_REFERER'] = 'https://exemple.test/back-office/ma-situation/evenements';
        self::assertSame(jnet_moi_url('evenements'), $controller->listRedirectUrl(new Request()));
        unset($_SERVER['HTTP_REFERER']);
        self::assertSame(url('evenements'), $controller->listRedirectUrl(new Request()));
        $view = $this->src('views/community/events.php');
        self::assertStringContainsString('name="return_to" value="jnet"', $view);
        self::assertStringNotContainsString('name="return_to" value="bo"', $view);
    }

    public function testShellStylesAndScriptsAreLoadedWithoutTheBackOfficeLayout(): void
    {
        $person = $this->src('app/Controllers/Web/PersonnelController.php');
        self::assertStringContainsString("'jnetPageCssFirst' => JnetMemberShell::wants(\$request) ? ['tailwind.css'] : []", $person);
        self::assertStringContainsString("['personnel-file-hub.js', 'decorations-rack.js', 'alpine.min.js']", $person);
        self::assertStringContainsString("'personnel-file.css', 'personnel-file-refresh.css', 'decorations-kit.css', 'jnet_moi_fiche.css'", $person);
        self::assertStringContainsString("'back-office-corrections.css', 'jnet_moi_fiche.css'", $person);
        $shell = $this->src('app/Controllers/Web/JnetPortalController.php');
        self::assertStringContainsString("\$vars['jnetPageCssFirst']", $shell);
        self::assertStringContainsString("array_merge(\$cssFirst, ['jnet_portal.css', 'jnet_spaces.css'], \$cssAll)", $shell);
        self::assertStringContainsString("'jnetPageCssFirst' => ['tailwind.css']", $this->src('app/Controllers/Admin/Organization/MemberSituationController.php'));
        foreach (['jnet_moi_fiche.css', 'jnet_moi_evenements.css'] as $css) {
            self::assertFileExists(self::ROOT . '/public/assets/css/' . $css);
        }
        // Hors de /jnet/moi (portail, vue du personnel) : rien ne change.
        self::assertFalse(JnetMemberShell::wants(new Request()));
    }

    // —— Rendus réels ——

    /** @return array<string, mixed> */
    private function shellVars(array $more = []): array
    {
        return array_merge(['jnetMemberShell' => true, 'success' => null, 'error' => null, 'user' => ['id' => 7, 'display_name' => 'Jean Martin', 'callsign' => 'VIPER 1-1', 'tenant_name' => 'Escadron']], $more);
    }

    public function testCareerPagesRenderWithoutKickersAndWithMemberLinks(): void
    {
        $html = $this->render('admin.member_situation.carriere', $this->shellVars([
            'gradeHistory' => [['label' => 'Sergent', 'obtained_at' => '2026-03-01', 'ends_at' => null, 'obtained_via' => 'anciennete']],
            'timeline' => [['kind' => 'promotion', 'at' => '2026-03-01', 'title' => 'Promu Sergent', 'detail' => '']],
        ]));
        self::assertStringContainsString('Historique de grade', $html);
        self::assertStringContainsString('/jnet/moi/avancement', $html);
        self::assertStringNotContainsString('kicker', $html);
        self::assertStringNotContainsString('ma-situation', $html);

        $html = $this->render('admin.member_situation.decorations', $this->shellVars(['awards' => [['definition_name' => 'Croix de la valeur', 'awarded_at' => '2025-11-11', 'authority' => 'Cdt']]]));
        self::assertStringContainsString('Croix de la valeur', $html);
        self::assertStringContainsString('/jnet/moi/qualifications', $html);
        self::assertStringNotContainsString('kicker', $html);

        $html = $this->render('admin.member_situation.dotation', $this->shellVars(['assignments' => [['definition_name' => 'Radio', 'category' => 'Liaison', 'status' => 'returned', 'serial_number' => 'A1', 'assigned_at' => '2026-01-01']]]));
        self::assertStringContainsString('Catégorie', $html);
        self::assertStringContainsString('/jnet/moi/coffre', $html);
        self::assertStringNotContainsString('kicker', $html);

        $html = $this->render('admin.member_situation.coffre', $this->shellVars(['vault' => ['items' => [], 'counts' => ['total' => 0], 'sections' => []]]));
        self::assertStringContainsString('Coffre encore vide', $html);
        self::assertStringNotContainsString('kicker', $html);
    }

    public function testQualificationsAndUnitePagesRender(): void
    {
        $html = $this->render('admin.member_situation.qualifications', $this->shellVars(['awards' => [[
            'id' => 12, 'definition_name' => 'Brevet JTAC', 'certificate_document_path' => 'x.pdf', 'can_regenerate_brevet' => true,
            'admin_status_normalized' => 'obtained', 'obtained_at' => '2026-01-12', 'seal_letters' => 'JT',
        ], [
            'id' => 13, 'definition_name' => 'Secours', 'can_generate_brevet' => true, 'admin_status_normalized' => 'obtained',
        ]]]));
        self::assertStringContainsString('/jnet/moi/qualifications/12/brevet', $html);
        self::assertStringContainsString('/jnet/moi/qualifications/13/generer-brevet', $html);
        self::assertStringContainsString('/jnet/moi/ma-fiche?onglet=formation', $html);
        self::assertStringNotContainsString('ma-situation', $html);

        $empty = $this->render('admin.member_situation.unite', $this->shellVars([]));
        self::assertStringContainsString('Pas encore d’unité', $empty);
        self::assertStringContainsString('/jnet/moi/ma-fiche?onglet=unite', $empty);
        self::assertStringNotContainsString('kicker', $empty);
    }

    public function testAdvancementPageAndPanelPostToTheMemberSpace(): void
    {
        $next = ['grade_label' => 'Sergent-chef', 'mode' => 'both', 'automatic' => true, 'seniority_eligible' => false, 'choice' => true, 'already_volunteered' => false, 'campaign_id' => 3, 'conditions' => []];
        $panel = ['current' => ['label' => 'Sergent'], 'next' => $next, 'offer' => ['campaign_id' => 3, 'title' => 'Campagne', 'grade_label' => 'Sergent-chef'], 'history' => [], 'opinions' => []];
        $html = $this->render('admin.member_situation.avancement', $this->shellVars([
            'panel' => $panel, 'mobilityRequests' => [], 'mobilityReady' => true, 'mobilityTypeLabels' => [], 'mobilityStatusLabels' => [],
            'assignmentUnits' => [], 'assignmentTargets' => ['postes' => [], 'aav' => [], 'offres' => []],
        ]));
        self::assertStringContainsString('action="' . jnet_moi_url('avancement/3/volontaire') . '"', $html);
        // Sans campagne ouverte : demande d'avancement au choix et demande d'affectation.
        $free = ['current' => ['label' => 'Sergent'], 'next' => array_merge($next, ['campaign_id' => null]), 'offer' => null, 'history' => [], 'opinions' => []];
        $html2 = $this->render('admin.member_situation.avancement', $this->shellVars([
            'panel' => $free, 'mobilityRequests' => [], 'mobilityReady' => true, 'mobilityTypeLabels' => [], 'mobilityStatusLabels' => [],
            'assignmentUnits' => [['id' => 4, 'name' => 'Compagnie', 'label' => 'Compagnie', 'assignable' => true]], 'assignmentTargets' => ['postes' => [], 'aav' => [], 'offres' => []],
        ]));
        self::assertStringContainsString('action="' . jnet_moi_url('avancement/demande') . '"', $html2);
        self::assertStringContainsString('action="' . jnet_moi_url('avancement/affectation') . '"', $html2);
        self::assertStringNotContainsString('ma-situation', $html);
        self::assertStringNotContainsString('mav-card__kicker', $html);

        $panelHtml = $this->render('partials.personnel.advancement_panel', ['advancementPanel' => $panel, 'advancementPanelIsSelf' => true]);
        self::assertStringContainsString(jnet_moi_url('avancement/3/volontaire'), $panelHtml);
        self::assertStringContainsString(jnet_moi_url('avancement'), $panelHtml);
        self::assertStringNotContainsString('ma-situation', $panelHtml);
    }

    public function testEventsPageRendersInTheJnetShellAndAnswersReturnToJnet(): void
    {
        $start = date('Y-m-d H:i:s', strtotime('+3 days 20:30'));
        $html = $this->render('admin.member_situation.evenements', $this->shellVars([
            'eventsInBackOffice' => true, 'boSkipSessionFlashes' => true, 'memberEventsVue' => 'a_venir', 'memberEventsPast' => [], 'memberEventsCalendar' => null,
            'events' => [['id' => 11, 'title' => 'OP SPECTRE', 'event_type' => 'operation', 'starts_at' => $start, 'ends_at' => $start, 'location' => 'Serveur', 'description' => '', 'rsvp_status' => null, 'status' => 'published']],
            'currentUserId' => 7, 'eventsQuota' => null, 'eventsCheckInFlags' => [], 'calendar_subscription_url' => '', 'canPublishOperationalBoard' => false,
            'eventsRsvpSummaries' => [], 'eventSlotsByEvent' => [], 'mySlotAssignmentByEvent' => [],
        ]));
        self::assertStringContainsString('OP SPECTRE', $html);
        self::assertStringContainsString('ath-main__body jnet-moi-events', $html);
        self::assertStringContainsString('/jnet/moi/evenements?vue=calendrier', $html);
        self::assertStringNotContainsString('Calendrier communautaire', $html);
        self::assertStringNotContainsString('ma-situation', $html);
    }

    public function testDemarchesPageRendersAndLinksBackToTheMemberSpace(): void
    {
        $html = $this->render('personnel.rh_workspace', $this->shellVars([
            'rhGreetingName' => 'VIPER 1-1', 'rhTrainingAllowed' => true, 'rhCharterReady' => false, 'rhCharterAccepted' => false,
            'rhSeniorityLines' => [], 'rhDossierCompleteness' => ['score' => 80, 'missing' => [], 'missing_labels' => [], 'sections_critiques' => [], 'details' => []],
            'rhWorkspaceCsrf' => 'tok', 'rhAbsencesSchemaReady' => true, 'rhPersonnelAbsences' => [], 'rhActiveAbsences' => [],
            'rhAbsenceReasonLabels' => ['vacances' => 'Vacances'], 'rhMobilitySchemaReady' => true, 'rhMyMobility' => [], 'rhMobilityTypeLabels' => [],
            'rhMobilityStatusLabels' => [], 'rhHrDocsSchemaReady' => true, 'rhMyHrDocs' => [], 'rhHrDocTypeLabels' => [],
            'rhElevationCatalog' => ['grades' => [], 'roles' => [], 'job_roles' => [], 'units' => [], 'permissions' => []],
            'rhElevationCooldownSeconds' => null, 'rhElevationHasRecipients' => true,
        ]));
        self::assertStringContainsString('Déclarer une absence', $html);
        self::assertStringContainsString('/personnel/mon-espace-rh/absences', $html);
        self::assertStringContainsString('/jnet/moi/ma-fiche', $html);
        self::assertStringContainsString('/jnet/moi/coffre', $html);
        self::assertStringNotContainsString('ma-situation', $html);
    }

    /** @return array<string, mixed> */
    private function ficheVars(array $more = []): array
    {
        $grade = ['id' => 2, 'label_short' => 'SGT', 'label_long' => 'Sergent', 'name' => 'Sergent'];
        $target = ['id' => 7, 'display_name' => 'Jean Martin', 'callsign' => 'VIPER 1-1', 'email' => 'jm@exemple.test', 'grade_id' => 2, 'status' => 'active', 'tenant_member_number' => '0042'];

        return $this->shellVars(array_merge([
            'targetUser' => $target, 'personnelProfile' => ['matricule_internal' => 'M-1', 'visibility_level' => 'normal', 'assignment_visibility' => 'normal'],
            'personnelExtras' => [], 'userProfile' => [], 'grade' => $grade, 'grades' => [$grade], 'assignments' => [], 'completeness' => ['score' => 80, 'sections_critiques' => [], 'details' => [], 'missing_labels' => []],
            'canEditProfile' => true, 'viewerIsPersonnelSubject' => true, 'personnelIsSelf' => true, 'personnelFilePage' => true, 'memberTrigram' => 'JMA',
            'personnelVisibility' => ['visibility_level' => 'normal', 'assignment_visibility' => 'normal'], 'displaySettings' => [],
            'advancementPanel' => ['current' => ['label' => 'Sergent'], 'next' => null, 'history' => [], 'offer' => null, 'opinions' => []], 'advancementPanelIsSelf' => true,
        ], $more));
    }

    public function testMyFileRendersInTheJnetShellWithMemberLinksAndNoSecondH1(): void
    {
        $html = $this->render('personnel.file', $this->ficheVars());
        self::assertStringContainsString('personnel-file-root', $html);
        self::assertStringContainsString('ath-main__body jnet-moi-fiche', $html);
        self::assertStringContainsString('href="' . jnet_moi_url('ma-fiche/modifier') . '"', $html);
        self::assertStringContainsString('href="' . jnet_moi_url('mes-demarches') . '"', $html);
        self::assertStringContainsString('<h2 class="personnel-file-hero__name"', $html);
        self::assertStringNotContainsString('<h1', $html);
        self::assertStringNotContainsString('ma-situation', $html);

        // Hors de la coque JNET (/personnel/{id}) : le titre de la fiche reste le titre de la page.
        $portal = $this->render('personnel.file', $this->ficheVars(['jnetMemberShell' => false]));
        self::assertStringContainsString('<h1 class="personnel-file-hero__name"', $portal);
        self::assertStringNotContainsString('ath-main__body', $portal);
    }

    public function testEditMyFileRendersInTheJnetShell(): void
    {
        $vars = $this->ficheVars([
            'currentGrade' => ['id' => 2, 'label_short' => 'SGT', 'name' => 'Sergent'], 'editMemberLocked' => true,
            'units' => [], 'tenantMemberNumber' => '0042', 'tenantMemberNumberLabel' => 'N° de membre', 'tenantMemberNumberEnabled' => false,
            'tenantMemberNumberMode' => 'free', 'tenantMemberNumberPreview' => null, 'canManageMemberNumber' => false,
            'personnelAssignments' => [], 'nicknames' => [], 'extraCallsigns' => [], 'medalRackItems' => [], 'decorationCatalog' => [],
            'medalRackSplit' => ['catalogIds' => [], 'customLines' => []], 'roleplayFollowupConfig' => ['enabled' => false, 'stages' => [], 'recruitment_tracks' => []],
            'visibilityLevelOptions' => ['normal' => 'Normal'], 'visibilityPreviewBaseUrl' => url('personnel/7'),
            'personnelVisibilityConsequence' => '', 'personnelAssignmentVisibilityConsequence' => '',
        ]);
        $html = $this->render('personnel.edit', $vars);
        self::assertStringContainsString('class="pd-page ath-main__body jnet-moi-fiche"', $html);
        self::assertStringContainsString('href="' . jnet_moi_url('ma-fiche') . '"', $html);
        self::assertStringContainsString('action="' . url('personnel/7/update') . '"', $html);
        self::assertStringNotContainsString('Éditer le dossier', $html);
        self::assertStringNotContainsString('pd-header__eyebrow', $html);

        $portal = $this->render('personnel.edit', array_merge($vars, ['jnetMemberShell' => false]));
        self::assertStringContainsString('Éditer le dossier', $portal);
        self::assertStringContainsString('class="pd-page"', $portal);
    }
}
