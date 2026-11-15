<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\NonDefaultTenantMiddleware;
use App\Middleware\OrganizationAdminMiddleware;
use App\Services\Community\TenantTypeConfig;
use App\Services\Jnet\JnetMemberHub;
use App\Support\BackOfficeLegacyRedirects;
use App\Support\JnetMemberBridge;
use App\Support\JnetMemberPaths;
use App\Support\JnetMemberShell;
use App\Support\PortalAccessChoice;
use PHPUnit\Framework\TestCase;
use Tests\Support\BackOfficeRouteMap as Map;

/**
 * Pages « Ma situation » du membre dans JNET : la fondation (table des adresses, routes /jnet/moi/*, redirections 301 des
 * anciennes adresses, coque, hub, menus, sortie des membres du back-office). Le détail de chaque page est testé par son groupe.
 */
final class JnetMaSituationFoundationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_GET = $_POST = [];
        Gate::reset();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = [];
        Gate::reset();
        Session::actAs([]);
    }

    private static function read(string $file): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $file);
    }

    /** @return list<string> */
    private static function middlewares(array $route): array
    {
        return array_map(static fn (mixed $m): string => is_string($m) ? $m : 'closure', $route['middleware']);
    }

    // —— Table de correspondance ——

    public function testTableCoversTheSeventeenPagesTheThreeDownloadsAndTheTwentyPosts(): void
    {
        self::assertCount(17, JnetMemberPaths::PAGES);
        self::assertCount(3, JnetMemberPaths::DOWNLOADS);
        self::assertCount(20, JnetMemberPaths::POSTS);
        foreach (['ma-fiche', 'ma-fiche/modifier', 'mes-demarches', 'unite', 'evenements', 'avancement', 'carriere', 'decorations',
            'qualifications', 'coffre', 'dotation', 'carnet-de-vol', 'mes-papiers', 'liaison-atak', 'appareils', 'premiere-liaison', 'musique'] as $slug) {
            self::assertArrayHasKey($slug, JnetMemberPaths::PAGES, $slug);
        }
        // Chaque page appartient à une rubrique du hub.
        $sections = array_column(JnetMemberHub::sections(['tenantType' => 'full', 'pilotLogbook' => true]), 'key');
        foreach (JnetMemberPaths::PAGES as $slug => $page) {
            self::assertContains($page['section'], $sections, $slug);
            self::assertTrue(class_exists($page['owner']), $slug . ' : ' . $page['owner']);
        }
    }

    public function testPathHelpers(): void
    {
        self::assertSame('jnet/moi/qualifications', JnetMemberPaths::path('qualifications'));
        self::assertSame('back-office/ma-situation/qualifications', JnetMemberPaths::legacyPath('qualifications'));
        self::assertSame('jnet/moi', JnetMemberPaths::path());
        self::assertSame('back-office/ma-situation/unite', JnetMemberPaths::toLegacy('/jnet/moi/unite'));
        self::assertNull(JnetMemberPaths::toLegacy('jnet/espaces'));
        self::assertNull(JnetMemberPaths::toLegacy('jnet/moiré'));
        self::assertTrue(JnetMemberPaths::isMemberPath('/jnet/moi/appareils'));
        self::assertFalse(JnetMemberPaths::isMemberPath('/jnet/moi-meme'));
        self::assertFalse(JnetMemberPaths::isMemberPath('/back-office/ma-situation/appareils'));
        self::assertSame('qualifications/12/brevet', JnetMemberPaths::slugOf('/jnet/moi/qualifications/12/brevet'));
        self::assertSame('qualifications', JnetMemberPaths::pageOf('qualifications/12/brevet'));
        self::assertSame('ma-fiche/modifier', JnetMemberPaths::pageOf('ma-fiche/modifier'));
        self::assertNull(JnetMemberPaths::pageOf('inconnue'));
        self::assertStringEndsWith('/jnet/moi/avancement?x=1#mav-aff-title', jnet_moi_url('avancement', ['x' => 1], 'mav-aff-title'));
        self::assertStringEndsWith('/jnet/moi/unite', jnet_moi_url('unite'));
    }

    // —— Routes ——

    public function testEveryPageAndDownloadIsDeclaredWithTheMemberMiddlewares(): void
    {
        $expected = [AuthMiddleware::class, NonDefaultTenantMiddleware::class];
        foreach (array_merge(array_keys(JnetMemberPaths::PAGES), JnetMemberPaths::DOWNLOADS) as $slug) {
            $route = Map::match('GET', Map::samplePath('/' . JnetMemberPaths::path($slug)));
            self::assertNotNull($route, $slug);
            self::assertSame('/' . JnetMemberPaths::path($slug), $route['path'], $slug);
            self::assertSame($expected, self::middlewares($route), $slug);
            self::assertInstanceOf(\Closure::class, $route['handler'], $slug . ' passe par JnetMemberBridge');
        }
    }

    public function testEveryPostIsDeclaredOnTheNewAndOnTheOldAddressWithTheSameController(): void
    {
        foreach (JnetMemberPaths::POSTS as $slug) {
            $new = Map::match('POST', Map::samplePath('/' . JnetMemberPaths::path($slug)));
            $old = Map::match('POST', Map::samplePath('/' . JnetMemberPaths::legacyPath($slug)));
            self::assertNotNull($new, 'POST /' . JnetMemberPaths::path($slug));
            self::assertNotNull($old, 'POST /' . JnetMemberPaths::legacyPath($slug) . ' : les anciens formulaires doivent rester valides');
            self::assertSame([AuthMiddleware::class, NonDefaultTenantMiddleware::class], self::middlewares($new), $slug);
            self::assertSame([AuthMiddleware::class, OrganizationAdminMiddleware::class], self::middlewares($old), $slug);
            // L'ancienne adresse n'est jamais une redirection : un POST redirigé perdrait son corps.
            self::assertTrue(is_array($old['handler']), $slug . ' : l\'ancien POST est servi par son contrôleur');
        }
    }

    public function testTheNewRoutesFormOneContiguousBlock(): void
    {
        $lines = explode("\n", str_replace("\r\n", "\n", self::read('routes/web.php')));
        $idx = [];
        foreach ($lines as $i => $line) {
            if (preg_match("#^\s*\\\$router->(get|post)\('/jnet/moi/#", $line) === 1) {
                $idx[] = $i;
            }
        }
        self::assertCount(40, $idx, '20 lectures + 20 envois');
        self::assertSame(count($idx) - 1, end($idx) - $idx[0], 'les routes /jnet/moi/* sont contiguës');
    }

    public function testMemberRoutesNeverTakeAnIdentifierFromTheRequest(): void
    {
        // L'identifiant du membre vient de la session : aucune route /jnet/moi n'a de jeton {userId} ou {id} de membre.
        foreach (Map::routes() as $route) {
            if (str_starts_with($route['path'], '/jnet/moi/')) {
                self::assertDoesNotMatchRegularExpression('/\{(userId|memberId|personnelId)\}/', $route['path'], $route['path']);
            }
        }
    }

    // —— Redirections ——

    /** @return array<string, array{0:string}> */
    public static function pages(): array
    {
        $rows = [];
        foreach (array_keys(JnetMemberPaths::PAGES) as $slug) {
            $rows[$slug] = [$slug];
        }

        return $rows;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function testOldAddressRedirectsToTheNewOneInOneHopKeepingTheQuery(string $slug): void
    {
        $old = '/' . JnetMemberPaths::legacyPath($slug);
        $route = Map::match('GET', $old);
        self::assertNotNull($route, $old);
        self::assertSame($old, $route['path'], $old . ' : l\'ancienne route de page ne doit plus la servir');
        self::assertInstanceOf(\Closure::class, $route['handler']);
        $_GET = ['onglet' => 'suivi', 'mois' => '2026-12'];
        $_SERVER['REQUEST_URI'] = $old . '?onglet=suivi&mois=2026-12';
        $response = ($route['handler'])(new Request(), []);
        self::assertSame(301, $response->statusCode(), $old);
        $location = (string) $response->headerValue('Location');
        self::assertStringEndsWith('/jnet/moi/' . $slug . '?onglet=suivi&mois=2026-12', $location, $old);
        // Un seul saut : la cible n'est elle-même l'objet d'aucune redirection.
        self::assertNull(BackOfficeLegacyRedirects::target(JnetMemberPaths::path($slug)));
        $target = Map::match('GET', '/' . JnetMemberPaths::path($slug));
        self::assertNotNull($target);
        self::assertSame('/' . JnetMemberPaths::path($slug), $target['path']);
    }

    public function testDownloadsAndAudioStreamRedirectWithTheirParametersAndQuery(): void
    {
        $cases = [
            ['/back-office/ma-situation/qualifications/12/brevet', '/jnet/moi/qualifications/12/brevet'],
            ['/back-office/ma-situation/mes-papiers/couverture/34/telecharger', '/jnet/moi/mes-papiers/couverture/34/telecharger'],
            ['/back-office/ma-situation/musique/ecouter/56', '/jnet/moi/musique/ecouter/56'],
        ];
        foreach ($cases as [$old, $new]) {
            $route = Map::match('GET', $old);
            self::assertNotNull($route, $old);
            self::assertInstanceOf(\Closure::class, $route['handler'], $old);
            preg_match($route['pattern'], $old, $m);
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $_GET = ['t' => 'abc'];
            $response = ($route['handler'])(new Request(), $params);
            self::assertSame(301, $response->statusCode(), $old);
            self::assertStringEndsWith($new . '?t=abc', (string) $response->headerValue('Location'), $old);
        }
    }

    public function testRedirectsAreRegisteredForGetOnlyAndPostKeepsItsController(): void
    {
        foreach (Map::routes() as $route) {
            if ($route['method'] === 'POST' && str_starts_with($route['path'], '/back-office/ma-situation/')) {
                self::assertNotInstanceOf(\Closure::class, $route['handler'], $route['path'] . ' : jamais de redirection sur un POST');
            }
        }
        // Plus aucune page de lecture n'est servie à l'ancienne adresse.
        foreach (Map::routes() as $route) {
            if ($route['method'] === 'GET' && str_starts_with($route['path'], '/back-office/ma-situation/')) {
                self::assertInstanceOf(\Closure::class, $route['handler'], $route['path']);
            }
        }
        self::assertSame(
            array_keys(JnetMemberPaths::legacyRedirects()),
            array_values(array_intersect(array_keys(BackOfficeLegacyRedirects::map()), array_keys(JnetMemberPaths::legacyRedirects())))
        );
    }

    public function testOldPostHandlersStillAnswerFromTheOldAddress(): void
    {
        // Les anciens formulaires en cache, le mod et les e-mails continuent de fonctionner.
        self::assertNotNull(Map::match('POST', '/back-office/ma-situation/avancement/demande'));
        self::assertNotNull(Map::match('POST', '/back-office/ma-situation/qualifications/7/generer-brevet'));
        self::assertNotNull(Map::match('POST', '/back-office/ma-situation/musique/playlists/7/deplacer'));
        self::assertStringContainsString("str_starts_with(\$path, '/back-office/ma-situation')", self::read('app/Middleware/OrganizationAdminMiddleware.php'));
    }

    // —— Passerelle et coque ——

    public function testRequestCanBePresentedUnderAnotherPathKeepingTheQuery(): void
    {
        $_SERVER['REQUEST_URI'] = '/jnet/moi/unite?vue=a_venir';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['vue' => 'a_venir'];
        $request = new Request();
        $legacy = $request->withPath('/back-office/ma-situation/unite');
        self::assertSame('/back-office/ma-situation/unite', $legacy->path());
        self::assertSame('/back-office/ma-situation/unite?vue=a_venir', $legacy->uri());
        self::assertSame('a_venir', $legacy->query('vue'));
        self::assertSame('/jnet/moi/unite', $request->path(), 'la requête d\'origine ne change pas');
    }

    public function testShellDecidesByPathAndBridgeByTheControllerConstant(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/jnet/moi/unite';
        self::assertTrue(JnetMemberShell::wants(new Request()));
        foreach (['/back-office/ma-situation/unite', '/jnet/espaces', '/atak/premiere-liaison', '/personnel/12'] as $uri) {
            $_SERVER['REQUEST_URI'] = $uri;
            self::assertFalse(JnetMemberShell::wants(new Request()), $uri);
        }
        self::assertTrue(JnetMemberBridge::ready(\App\Controllers\Admin\Organization\MemberSituationController::class));
        self::assertFalse(JnetMemberBridge::ready(\stdClass::class));
        self::assertFalse(JnetMemberBridge::ready('Classe\\Inconnue'));
    }

    public function testBridgeGivesAPendingControllerTheOldPathAndAMigratedOneTheNewPath(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/jnet/moi/avancement?x=1';
        $_GET = ['x' => '1'];
        $request = new Request();
        // Contrôleur pas encore migré : il voit l'ancienne adresse, comme avant.
        $pending = JnetMemberBridge::run($request, ['id' => '4'], JnetBridgeProbePending::class, 'show', JnetBridgeProbePending::class);
        self::assertSame('/back-office/ma-situation/avancement|4|1', $pending->body());
        // Contrôleur migré (JNET_MOI_READY) : il voit la vraie adresse.
        $ready = JnetMemberBridge::run($request, [], JnetBridgeProbeReady::class, 'show', JnetBridgeProbeReady::class);
        self::assertSame('/jnet/moi/avancement||1', $ready->body());
        // Le contrôleur de la route peut être migré sans l'être celui qui rend la page (et inversement) : la constante décisive est celle du propriétaire.
        $mixed = JnetMemberBridge::run($request, [], JnetBridgeProbeReady::class, 'show', JnetBridgeProbePending::class);
        self::assertSame('/back-office/ma-situation/avancement||1', $mixed->body());
        // Un autre chemin que /jnet/moi n'est jamais réécrit.
        $_SERVER['REQUEST_URI'] = '/atak/premiere-liaison';
        self::assertSame('/atak/premiere-liaison||1', JnetMemberBridge::run(new Request(), [], JnetBridgeProbePending::class, 'show', JnetBridgeProbePending::class)->body());
    }

    public function testMemberSituationControllerRendersThroughTheShellAndBuildsNewAddresses(): void
    {
        $src = self::read('app/Controllers/Admin/Organization/MemberSituationController.php');
        self::assertStringNotContainsString("Response::view('layout.main'", $src);
        // Dix pages rendues par la coque ; la page Événements d'une formule sans ce module renvoie à la page de formules du portail.
        self::assertGreaterThanOrEqual(9, substr_count($src, 'JnetMemberShell::view($request,'));
        self::assertStringContainsString("Response::redirect(url('platform/upgrade') . '?from=events')", $src);
        self::assertStringNotContainsString("url('back-office/ma-situation", $src);
        self::assertStringContainsString("jnet_moi_url('qualifications/' . \$awardId . '/brevet')", $src);
    }

    public function testShellPageRendersWithItsRailTheThemeAndASingleFlash(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        Session::actAs(['tenant_id' => 3, 'user_id' => 7, 'callsign' => 'VIPER 1-1', 'display_name' => 'Jean Martin']);
        // Le bouton de déconnexion de la coque porte un jeton CSRF : la session est tenue pour démarrée (sans quoi PHP tenterait
        // d'en ouvrir une après l'écho du lanceur de tests et l'avertissement entrerait dans la page rendue).
        $started = new \ReflectionProperty(Session::class, 'started');
        $wasStarted = $started->getValue();
        $started->setValue(null, true);
        $_SESSION = $_SESSION ?? [];
        $ctx = ['tenantType' => 'full', 'pilotLogbook' => true, 'unreadMail' => 2, 'badges' => ['qualifications_expiring' => 3]];
        $html = Response::view('jnet._page', [
            'title' => 'Dossier de carrière — Extranet d’unité',
            'content' => 'admin.member_situation.carriere',
            'jnetInnerView' => 'admin.member_situation.carriere',
            'jnetPageTitle' => 'Dossier de carrière',
            'jnetPageLead' => 'Timeline unique.',
            'jnetPageCss' => ['jnet_portal.css', 'jnet_spaces.css', 'jnet_member.css', 'back-office-member-situation.css'],
            'activeNav' => 'hub',
            'jnetTenantName' => 'Communauté',
            'jnetCanTba' => false,
            'jnetUnreadMail' => 2,
            'jnetMemberShell' => true,
            'jnetMemberPage' => 'carriere',
            'jnetMemberRail' => JnetMemberHub::sections($ctx),
            'jnetShowExtranetTabs' => true,
            'timeline' => [],
            'gradeHistory' => [],
            'success' => 'Message déjà lu par le contrôleur',
        ])->body();
        self::assertStringContainsString('data-bo-theme="dark"', $html);
        self::assertStringContainsString('class="jm-rail"', $html);
        self::assertStringContainsString('aria-current="page">Dossier de carrière', $html);
        self::assertStringContainsString('jnet_member.css', $html);
        self::assertStringContainsString('Mon extranet', $html);
        // Le message du contrôleur est affiché une seule fois.
        self::assertSame(1, substr_count($html, 'Message déjà lu par le contrôleur'));
        // Pastille des qualifications à renouveler reprise du menu.
        self::assertMatchesRegularExpression('#Mes qualifications<em>3</em>#', $html);
        // Fil d'Ariane (Mon extranet, Mon espace, la page), puis le titre : pas de surtitre au-dessus du titre de la page.
        self::assertMatchesRegularExpression('#<main[^>]*>\s*<nav class="ja-crumbs".*?</nav>\s*<header class="jp-head">\s*<h1#s', $html);
        self::assertMatchesRegularExpression('#<a href="[^"]*jnet/espaces">Mon espace</a>#', $html);
        self::assertStringNotContainsString('<pre', $html);
        self::assertStringNotContainsString('Warning:', $html);
        self::assertStringNotContainsString('Fatal error', $html);

        // Profil sans extranet : seulement le rail « Mon espace » et l'accueil Athena.
        $bare = Response::view('jnet._page', [
            'content' => 'admin.member_situation.carriere',
            'jnetInnerView' => 'admin.member_situation.carriere',
            'jnetPageTitle' => 'Dossier de carrière',
            'jnetPageCss' => ['jnet_member.css'],
            'activeNav' => 'hub',
            'jnetMemberShell' => true,
            'jnetMemberPage' => 'carriere',
            'jnetMemberRail' => JnetMemberHub::sections(['tenantType' => 'effectifs']),
            'jnetShowExtranetTabs' => false,
        ])->body();
        self::assertStringNotContainsString('Espace commun', $bare);
        self::assertStringContainsString('class="jm-rail"', $bare);
        self::assertStringContainsString('Accueil Athena', $bare);
        $started->setValue(null, $wasStarted);
    }

    // —— Hub, menus, liens ——

    public function testHubLinksPointToTheMemberSpaceAndRespectTheProfile(): void
    {
        $hrefs = [];
        foreach (JnetMemberHub::sections(['tenantType' => 'full', 'pilotLogbook' => true, 'badges' => ['events_rsvp_pending' => 2]]) as $section) {
            foreach ($section['items'] as $item) {
                $hrefs[$item['label']] = $item;
            }
        }
        foreach (['Fiche de mon unité', 'Événements', 'Ma fiche', 'Mes qualifications', 'Mon avancement', 'Dossier de carrière', 'Décorations',
            'Ma dotation', 'Ma liaison ATAK', 'Première liaison', 'Mes appareils', 'Musique', 'Mon carnet de vol', 'Mon coffre', 'Mes papiers', 'Mes démarches'] as $label) {
            self::assertArrayHasKey($label, $hrefs, $label);
            self::assertStringContainsString('/jnet/moi/', $hrefs[$label]['href'], $label);
            self::assertStringNotContainsString('back-office', $hrefs[$label]['href'], $label);
            self::assertNotSame('', $hrefs[$label]['page'], $label);
        }
        self::assertSame(2, $hrefs['Événements']['badge']);
        self::assertStringEndsWith('/jnet/moi/avancement#mav-aff-title', url(JnetMemberHub::REQUEST_PATH));

        // Profil « Carte ATAK » : pas de fiche ni de qualifications, la ligne ATAK reste.
        $atak = [];
        foreach (JnetMemberHub::sections(['tenantType' => 'atak', 'pilotLogbook' => true]) as $s) {
            foreach ($s['items'] as $i) {
                $atak[] = $i['label'];
            }
        }
        self::assertContains('Ma liaison ATAK', $atak);
        self::assertNotContains('Ma fiche', $atak);
        self::assertNotContains('Événements', $atak);
        // Profil « Effectifs » : fiche et ATAK, mais pas les opérations (événements).
        $effectifs = [];
        foreach (JnetMemberHub::sections(['tenantType' => 'effectifs', 'pilotLogbook' => true]) as $s) {
            foreach ($s['items'] as $i) {
                $effectifs[] = $i['label'];
            }
        }
        self::assertContains('Ma fiche', $effectifs);
        self::assertContains('Mes appareils', $effectifs);
        self::assertNotContains('Événements', $effectifs);
    }

    public function testCommunityProfilesFollowTheNewAddressesLikeTheOldOnes(): void
    {
        foreach (array_keys(JnetMemberPaths::PAGES) as $slug) {
            $old = JnetMemberPaths::legacyPath($slug);
            $new = JnetMemberPaths::path($slug);
            foreach ([TenantTypeConfig::TYPE_FULL, TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_ATAK] as $type) {
                self::assertSame(TenantTypeConfig::moduleForUri($old), TenantTypeConfig::moduleForUri($new), $type . ' ' . $slug);
                self::assertSame(TenantTypeConfig::uriAllowed($type, $old), TenantTypeConfig::uriAllowed($type, $new), $type . ' ' . $slug);
            }
        }
        // Le préfixe « jnet », toujours ouvert, ne fait pas échapper la ligne ATAK au profil « Effectifs ».
        self::assertSame('atak', TenantTypeConfig::moduleForUri('jnet/moi/liaison-atak'));
        self::assertTrue(TenantTypeConfig::uriAllowed(TenantTypeConfig::TYPE_ATAK, 'jnet/moi/liaison-atak'));
        self::assertFalse(TenantTypeConfig::uriAllowed(TenantTypeConfig::TYPE_EFFECTIFS, 'jnet/moi/evenements'));
        self::assertFalse(TenantTypeConfig::uriAllowed(TenantTypeConfig::TYPE_ATAK, 'jnet/moi/mes-demarches'));
        self::assertFalse(TenantTypeConfig::uriAllowed(TenantTypeConfig::TYPE_ATAK, 'jnet/moi/ma-fiche'));
        // Les profils sans extranet (JNET masqué) gardent pourtant leurs pages personnelles.
        self::assertTrue(TenantTypeConfig::backOfficePathHidden(TenantTypeConfig::TYPE_EFFECTIFS, 'jnet'));
        self::assertFalse(TenantTypeConfig::backOfficePathHidden(TenantTypeConfig::TYPE_EFFECTIFS, 'jnet/moi/unite'));
        self::assertFalse(TenantTypeConfig::backOfficePathHidden(TenantTypeConfig::TYPE_ATAK, 'jnet/moi/liaison-atak'));
        // Les règles de menu ne masquent pas ces liens aux membres (règle par défaut de « jnet » = droits d'administration).
        self::assertNull(back_office_nav_rule_for_path('jnet/moi/unite'));
        self::assertNull(back_office_nav_rule_for_path('back-office/ma-situation/unite'));
        self::assertNotNull(back_office_nav_rule_for_path('jnet/systeme'));
    }

    public function testMenusAndEntryPointsNoLongerSendMembersToTheOldAddresses(): void
    {
        foreach ([
            'views/partials/ath_sidebar_nav.php', 'views/partials/back_office_topbar.php', 'views/partials/back_office_sidebar.php',
            'config/navigation.php', 'views/admin/organization/operator_overview.php', 'app/Services/Portal/BackOfficeSearchService.php',
            'app/Services/Portal/PortalSearchService.php', 'app/Controllers/Web/HomeController.php', 'app/Controllers/Web/HubController.php',
            'app/Support/DashboardRhParcours.php', 'views/partials/dashboard_aside.php', 'views/partials/dashboard_command_center.php',
            'views/partials/dashboard_member_rh.php', 'views/partials/dashboard_rh_parcours.php', 'views/member_integration/index.php',
            'views/training/index.php', 'views/training/my-training.php', 'views/personnel/tutorials.php', 'views/academy/offer_actions.php',
            'views/academy/offer_dashboard.php', 'app/Services/Jnet/JnetMemberHub.php', 'app/Services/Academy/BuiltIn/AthenaCourseChaptersPortal.php',
        ] as $file) {
            self::assertStringNotContainsString('back-office/ma-situation', self::read($file), $file);
        }
        $nav = self::read('views/partials/ath_sidebar_nav.php');
        self::assertStringContainsString("url('jnet/moi/ma-fiche')", $nav);
        self::assertStringContainsString("url('jnet/moi/qualifications')", $nav);
        $topbar = self::read('views/partials/back_office_topbar.php');
        self::assertStringContainsString("url('jnet/moi/evenements')", $topbar);
    }

    public function testMenuOfADelegatedCadreLinksToTheMemberSpaceOnly(): void
    {
        $menu = self::read('tests/fixtures/audit-backoffice/menu-rh.txt');
        self::assertStringContainsString('Ma fiche | /jnet/moi/ma-fiche', $menu);
        self::assertStringNotContainsString('ma-situation', $menu);
        self::assertStringNotContainsString('ma-situation', self::read('tests/fixtures/audit-backoffice/menu-membre.txt'));
    }

    // —— Sécurité ——

    public function testCsrfSafetyNetCoversTheMemberSpace(): void
    {
        $src = self::read('app/Middleware/CsrfPostMiddleware.php');
        self::assertStringContainsString("'/jnet/moi/'", $src);
        self::assertStringContainsString("'/back-office/'", $src);
    }

    // —— Le membre ne passe plus par le back-office ——

    public function testPlainMemberHasNoBackOfficeRoleButStaffAndCadresKeepIt(): void
    {
        Session::actAs(['tenant_id' => 0, 'user_id' => 0, 'display_name' => 'Test']);
        $gate = Gate::getInstance();
        $gate->setPermissions([]);
        self::assertTrue(PortalAccessChoice::isPlainMember());
        foreach ([['admin.organization'], ['admin.access'], ['personnel.profile.update'], ['organization.recruitment.manage'],
            ['admin.members.moderate'], ['training.manage'], ['atak.command.alert']] as $perms) {
            $gate->setPermissions($perms);
            self::assertFalse(PortalAccessChoice::isPlainMember(), implode(',', $perms));
        }
        $gate->setPermissions([]);
        $gate->setPlatformAdmin(true);
        self::assertFalse(PortalAccessChoice::isPlainMember(), 'administrateur de la plateforme');
    }

    public function testBackOfficeHomeSendsAPlainMemberToJnetWithoutLoopingBack(): void
    {
        $ctl = self::read('app/Controllers/Admin/Organization/OrganizationDashboardController.php');
        self::assertMatchesRegularExpression('/if \(!\$canAdminister\) \{.*?isPlainMember\(\).*?plainMemberHome\(\).*?operatorOverview/s', $ctl);
        $access = self::read('app/Support/PortalAccessChoice.php');
        self::assertStringContainsString('return self::memberLandingUrl() ?? url(\'dashboard\');', $access);
        // La cible n'est jamais le back-office : ni boucle, ni retour vers une page qui y renvoie.
        self::assertStringNotContainsString("url('back-office')", substr($access, (int) strpos($access, 'function plainMemberHome'), 200));
        // JNET et le tableau de bord ne renvoient pas un membre vers le back-office.
        self::assertStringNotContainsString("url('back-office')", self::read('app/Controllers/Web/HomeController.php'));
        self::assertSame(
            \App\Support\PortalAccessChoice::class,
            (new \ReflectionMethod(PortalAccessChoice::class, 'plainMemberHome'))->class
        );
    }

    public function testStaffKeepsAGestionLinkAndTheirOwnJnetView(): void
    {
        $page = self::read('views/jnet/_page.php');
        self::assertStringContainsString("url('back-office')", $page);
        self::assertStringContainsString('jnetCanTba', $page);
    }
}

/** Contrôleur de test, pas encore migré (sans JNET_MOI_READY) : renvoie le chemin qu'il voit. */
final class JnetBridgeProbePending
{
    public function show(Request $request, array $params = []): Response
    {
        return (new Response())->setBody($request->path() . '|' . ($params['id'] ?? '') . '|' . $request->query('x', ''));
    }
}

/** Contrôleur de test migré. */
final class JnetBridgeProbeReady
{
    public const JNET_MOI_READY = true;

    public function show(Request $request, array $params = []): Response
    {
        return (new Response())->setBody($request->path() . '|' . ($params['id'] ?? '') . '|' . $request->query('x', ''));
    }
}
