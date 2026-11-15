<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Container;
use App\Core\Gate;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Support\PortalAccessChoice;
use PHPUnit\Framework\TestCase;

/**
 * « Ma situation » dans JNET, relecture : les liens du portail ne font plus passer un membre ordinaire par le back-office,
 * les messages de la coque, l'événement bloqué par la formule, les surtitres, le rail et le repli des images. Les vues
 * concernées sont RENDUES pour de vrai (sans base : le dépôt des communautés est remplacé).
 */
final class JnetMaSituationReviewFixesTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private ?bool $sessionStarted = null;

    /** @var array<string, mixed> */
    private array $savedSession = [];

    private array $server = [];

    private array $savedInstances = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->sessionStarted = $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = $_SESSION ?? [];
        $_SESSION = ['_csrf_token' => str_repeat('a', 64), 'tenant_id' => 3, 'user_id' => 7, 'callsign' => 'VIPER 1-1', 'display_name' => 'Jean Martin'];
        Session::actAs(['tenant_id' => 3, 'user_id' => 7, 'callsign' => 'VIPER 1-1', 'display_name' => 'Jean Martin']);
        $GLOBALS['__app_locale'] = 'fr';
        $_GET = $_POST = [];
        Gate::reset();
        $instances = new \ReflectionProperty(Container::class, 'instances');
        $this->savedInstances = $instances->getValue();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->savedInstances);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        $_SERVER = $this->server;
        $_GET = $_POST = [];
        Gate::reset();
        Session::actAs([]);
        unset($GLOBALS['__app_locale']);
    }

    private function src(string $relative): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $relative);
    }

    /** Le profil de la communauté est mémorisé pour tout le processus par navigation_current_tenant_type() : si un autre test l'a fixé avant, le rendu complet ne se juge pas ici. */
    private function skipUnlessFullProfile(): void
    {
        if (navigation_current_tenant_type() !== 'full') {
            self::markTestSkipped('Profil de communauté déjà mémorisé par un autre test.');
        }
    }

    /** Remplace le dépôt des communautés : la communauté 3 est du profil donné (full, effectifs, atak…). */
    private function tenantOfType(string $type): void
    {
        $repo = new class () extends TenantRepository {
            public string $type = 'full';

            public function __construct()
            {
            }

            public function findById(int $id): ?array
            {
                return ['id' => $id, 'name' => 'Communauté', 'slug' => 'communaute-test', 'tenant_type' => $this->type];
            }
        };
        $repo->type = $type;
        $instances = new \ReflectionProperty(Container::class, 'instances');
        $all = $instances->getValue();
        $all[TenantRepository::class] = $repo;
        $instances->setValue(null, $all);
    }

    private function renderPartial(string $relative, array $vars = []): string
    {
        ob_start();
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__file;
            })(self::ROOT . '/' . $relative, $vars);
        } finally {
            $out = (string) ob_get_clean();
        }

        return $out;
    }

    // —— Liens du portail ——

    public function testHeaderSendsAPlainMemberToJnetAndStaffToTheBackOffice(): void
    {
        $this->tenantOfType('full');
        $this->skipUnlessFullProfile();
        Gate::getInstance()->setPermissions([]);
        self::assertTrue(PortalAccessChoice::isPlainMember());
        $member = $this->renderPartial('views/partials/athena_caverne_header.php');
        self::assertStringNotContainsString('Fatal error', $member);
        self::assertStringNotContainsString('Warning:', $member);
        self::assertStringContainsString('Mon espace JNET', $member);
        self::assertStringNotContainsString('Mon back-office', $member);
        // Aucun lien « back-office » pour ce membre : ni barre haute, ni menu Espace, ni menu du profil.
        self::assertDoesNotMatchRegularExpression('#href="[^"]*/back-office/?"#', $member);
        self::assertMatchesRegularExpression('#href="[^"]*/jnet"#', $member);

        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization']);
        $staff = $this->renderPartial('views/partials/athena_caverne_header.php');
        self::assertStringNotContainsString('Mon espace JNET', $staff);
        self::assertMatchesRegularExpression('#href="[^"]*/back-office/?"#', $staff);
    }

    public function testHeaderHidesTheEntryWhenTheMemberHomeIsTheDashboard(): void
    {
        // Profil Effectifs : pas de JNET, l'arrivée du membre est le tableau de bord : un lien qui ne ferait que recharger n'a pas lieu d'être.
        $this->tenantOfType('effectifs');
        Gate::getInstance()->setPermissions([]);
        self::assertSame(url('dashboard'), PortalAccessChoice::plainMemberHome());
        $html = $this->renderPartial('views/partials/athena_caverne_header.php');
        self::assertStringNotContainsString('Mon espace JNET', $html);
        self::assertStringNotContainsString('Mon back-office', $html);
        self::assertDoesNotMatchRegularExpression('#href="[^"]*/back-office/?"#', $html);
    }

    public function testMenuEntryFollowsTheSameRule(): void
    {
        self::assertStringContainsString("'auth_only' => true, 'plain_member_home' => true", $this->src('config/navigation.php'));

        $this->tenantOfType('full');
        $this->skipUnlessFullProfile();
        $link = ['label' => 'Mon back-office', 'path' => 'back-office', 'auth_only' => true, 'plain_member_home' => true];
        Gate::getInstance()->setPermissions([]);
        $resolved = navigation_resolve_link($link);
        self::assertNotNull($resolved);
        self::assertSame('Mon espace JNET', $resolved['label']);
        self::assertSame(url('jnet'), $resolved['href']);

        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization']);
        $resolved = navigation_resolve_link($link);
        self::assertNotNull($resolved);
        self::assertSame('Mon back-office', $resolved['label']);
        self::assertStringContainsString('back-office', $resolved['href']);

        Gate::reset();
        Gate::getInstance()->setPermissions([]);
        $this->tenantOfType('effectifs');
        self::assertNull(navigation_resolve_link($link));
    }

    public function testActionCenterBackLinkGoesToJnetForAPlainMember(): void
    {
        $this->tenantOfType('full');
        $this->skipUnlessFullProfile();
        Gate::getInstance()->setPermissions([]);
        $html = $this->renderPartial('views/portal/action_center.php', ['action_center_digest' => ['sections' => []], 'mon_service' => []]);
        self::assertStringNotContainsString('Fatal error', $html);
        self::assertStringContainsString('Retour à mon espace JNET', $html);
        self::assertDoesNotMatchRegularExpression('#href="[^"]*/back-office/?"#', $html);

        Gate::reset();
        Gate::getInstance()->setPermissions(['admin.organization']);
        $html = $this->renderPartial('views/portal/action_center.php', ['action_center_digest' => ['sections' => []], 'mon_service' => []]);
        self::assertStringContainsString('Retour au back-office', $html);
        self::assertMatchesRegularExpression('#href="[^"]*/back-office/?"#', $html);

        Gate::reset();
        Gate::getInstance()->setPermissions([]);
        $this->tenantOfType('effectifs');
        $html = $this->renderPartial('views/portal/action_center.php', ['action_center_digest' => ['sections' => []], 'mon_service' => []]);
        self::assertStringNotContainsString('Retour au back-office', $html);
        self::assertStringNotContainsString('Retour à mon espace JNET', $html);
    }

    // —— Coque JNET ——

    private function shell(array $extra = []): string
    {
        return Response::view('jnet._page', array_merge([
            'title' => 'Dossier de carrière — Extranet d’unité',
            'content' => 'admin.member_situation.carriere',
            'jnetInnerView' => 'admin.member_situation.carriere',
            'jnetPageTitle' => 'Dossier de carrière',
            'jnetPageCss' => ['jnet_portal.css', 'jnet_spaces.css', 'jnet_member.css'],
            'activeNav' => 'hub',
            'jnetTenantName' => 'Communauté',
            'jnetCanTba' => false,
            'jnetUnreadMail' => 0,
            'jnetMemberShell' => true,
            'jnetMemberPage' => 'carriere',
            'jnetMemberRail' => [],
            'jnetShowExtranetTabs' => true,
            'timeline' => [],
            'gradeHistory' => [],
        ], $extra))->body();
    }

    public function testShellShowsEveryFlashTheBackOfficeShowedAndConsumesThem(): void
    {
        Session::flash('toast_error', 'Accès refusé à cette page.');
        Session::flash('toast_warning', 'Formule limitée.');
        Session::flash('warning', 'Avertissement de la page.');
        Session::flash('info', 'Information de la page.');
        Session::flash('success', 'Enregistré.');
        $html = $this->shell();
        foreach (['Accès refusé à cette page.', 'Formule limitée.', 'Avertissement de la page.', 'Information de la page.', 'Enregistré.'] as $text) {
            self::assertSame(1, substr_count($html, $text), $text);
        }
        self::assertMatchesRegularExpression('#jnet-flash jnet-flash--err" role="alert">Accès refusé#', $html);
        // Rien ne reste en session pour la page suivante.
        foreach (['toast_error', 'toast_warning', 'warning', 'info', 'success'] as $key) {
            self::assertArrayNotHasKey($key, $_SESSION['_flash'] ?? [], $key);
        }
    }

    public function testShellBreadcrumbNamesTheMemberSpace(): void
    {
        $html = $this->shell();
        self::assertMatchesRegularExpression('#<main[^>]*>\s*<nav class="ja-crumbs".*?Mon extranet.*?jnet/espaces">Mon espace</a>.*?Dossier de carrière</span>.*?</nav>\s*<header class="jp-head">\s*<h1#s', $html);
    }

    public function testShellLoadsTheImageFallbackScript(): void
    {
        $ctl = $this->src('app/Controllers/Web/JnetPortalController.php');
        self::assertMatchesRegularExpression("#\\\$js\[\] = 'img-fallback\.js'#", $ctl);
        self::assertFileExists(self::ROOT . '/public/assets/js/img-fallback.js');
        $html = $this->shell(['jnetPageJs' => ['img-fallback.js']]);
        self::assertStringContainsString('assets/js/img-fallback.js', $html);
    }

    // —— Page Événements bloquée par la formule ——

    public function testBlockedEventsPageRedirectsToThePlansPage(): void
    {
        $src = $this->src('app/Controllers/Admin/Organization/MemberSituationController.php');
        self::assertMatchesRegularExpression("#if \\(\\\$payload\['blocked'\]\\) \\{[^}]*Response::redirect\\(url\('platform/upgrade'\\) \. '\?from=events'\\)#s", $src);
        self::assertStringNotContainsString("'content' => 'platform.upgrade'", $src);
    }

    // —— Feuilles de style ——

    public function testNoKickerAboveATitleAndTheRailKeepsItsPillsOnOneLine(): void
    {
        $fiche = $this->src('public/assets/css/jnet_moi_fiche.css');
        self::assertMatchesRegularExpression('#\.pd-orbat-hero__kicker[^{]*\{\s*display:\s*none#', $fiche);

        $liaison = $this->src('public/assets/css/jnet_moi_premiere_liaison.css');
        // « sur votre PC », « en jeu » passent sous le titre de l'étape, en sous-titre.
        self::assertMatchesRegularExpression('#\.jp \.fl-step__kicker\s*\{[^}]*order:\s*2#', $liaison);
        self::assertMatchesRegularExpression('#\.jp \.fl-step__head > div\s*\{[^}]*flex-direction:\s*column#', $liaison);

        $member = $this->src('public/assets/css/jnet_member.css');
        self::assertMatchesRegularExpression('#\.jp \.jm-rail__item\s*\{[^}]*white-space:\s*nowrap#', $member);
    }

    public function testNewTextsAreTranslated(): void
    {
        $dict = require self::ROOT . '/lang/en/site_auto.php';
        foreach (['Mon espace JNET', 'Fiche, unité et démarches', 'Retour à mon espace JNET'] as $fr) {
            self::assertArrayHasKey($fr, $dict, $fr);
        }
    }
}
