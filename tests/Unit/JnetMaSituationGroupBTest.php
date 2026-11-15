<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\MemberCoverExportController;
use App\Controllers\Admin\MemberIdentityPapersController;
use App\Controllers\Admin\Organization\MemberSituationController;
use App\Controllers\Admin\Organization\PilotLogbookController;
use App\Controllers\Web\AtakFirstLinkController;
use App\Controllers\Web\MusicLibraryController;
use App\Core\Response;
use App\Core\Session;
use App\Services\Jnet\JnetMemberHub;
use App\Services\Personnel\PilotLogbookService;
use App\Services\Sse\SseIdGameCoverService;
use App\Support\JnetMemberBridge;
use App\Support\JnetMemberPaths;
use App\Support\PilotSyllabusCatalog;
use PHPUnit\Framework\TestCase;

/**
 * « Ma situation » dans JNET, groupe B : carnet de vol, mes papiers (et couverture), liaison ATAK, appareils, première liaison,
 * musique. Les contrôleurs rendent dans la coque JNET, tous les liens, formulaires et retours visent /jnet/moi/*, et chaque vue
 * est RENDUE pour de vrai (données fictives, sans base) : une classe appelée sans « use » ferait ici une erreur, pas en production.
 */
final class JnetMaSituationGroupBTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        $_SESSION = $this->savedSession;
        $_SERVER = $this->server;
        unset($GLOBALS['__app_locale']);
    }

    private function src(string $relative): string
    {
        return (string) file_get_contents(self::ROOT . '/' . $relative);
    }

    // —— Contrôleurs ——

    public function testGroupControllersRenderInTheJnetShellAndAreDeclaredReady(): void
    {
        foreach ([
            PilotLogbookController::class, MemberIdentityPapersController::class, MemberCoverExportController::class,
            MusicLibraryController::class, AtakFirstLinkController::class, MemberSituationController::class,
        ] as $class) {
            self::assertTrue(JnetMemberBridge::ready($class), $class . ' doit déclarer JNET_MOI_READY.');
        }
        foreach (['liaison-atak', 'appareils', 'premiere-liaison', 'musique', 'carnet-de-vol', 'mes-papiers'] as $slug) {
            self::assertTrue(JnetMemberBridge::ready(JnetMemberPaths::PAGES[$slug]['owner']), $slug);
        }
        foreach ([
            'app/Controllers/Admin/Organization/PilotLogbookController.php',
            'app/Controllers/Admin/MemberIdentityPapersController.php',
            'app/Controllers/Web/MusicLibraryController.php',
            'app/Controllers/Web/AtakFirstLinkController.php',
        ] as $file) {
            self::assertStringContainsString('JnetMemberShell::view($request', $this->src($file), $file);
        }
    }

    public function testNoGroupSourceBuildsTheOldAddressAnymore(): void
    {
        foreach ([
            'app/Controllers/Admin/Organization/PilotLogbookController.php',
            'app/Controllers/Admin/MemberIdentityPapersController.php',
            'app/Controllers/Admin/MemberCoverExportController.php',
            'app/Controllers/Web/MusicLibraryController.php',
            'app/Controllers/Admin/MusicAdminController.php',
            'app/Controllers/Web/AtakFirstLinkController.php',
            'views/admin/member_situation/pilot_logbook.php',
            'views/admin/member_situation/mes_papiers.php',
            'views/admin/member_situation/liaison_atak.php',
            'views/admin/member_situation/appareils.php',
            'views/admin/member_situation/musique.php',
            'views/atak/first_link.php',
            'views/partials/atak_device_cards.php',
            'views/admin/music/index.php',
            'views/admin/airspace/index.php',
            'views/admin/airspace/_hero.php',
        ] as $file) {
            $code = preg_replace('#/\*.*?\*/|^\s*//.*$|^\s*\*.*$#ms', '', $this->src($file));
            self::assertStringNotContainsString('back-office/ma-situation', (string) $code, $file);
        }
    }

    public function testConstantsAndRedirectsPointToTheMemberSpace(): void
    {
        self::assertSame('jnet/moi/musique', (new \ReflectionClassConstant(MusicLibraryController::class, 'BASE'))->getValue());
        self::assertSame('jnet/moi/carnet-de-vol', (new \ReflectionClassConstant(PilotLogbookController::class, 'MINE'))->getValue());
        $cover = $this->src('app/Controllers/Admin/MemberCoverExportController.php');
        self::assertSame(2, substr_count($cover, "jnet_moi_url('mes-papiers', [], 'couverture')"));
        self::assertStringContainsString("jnet_moi_url('musique')", $this->src('app/Controllers/Admin/MusicAdminController.php'));
        // La page de formules s'ouvre dans le portail, pas dans une coque qui n'en charge pas le style.
        self::assertStringContainsString("url('platform/upgrade') . '?from=' . PilotQualificationService::FEATURE", $this->src('app/Controllers/Admin/Organization/PilotLogbookController.php'));
        self::assertStringContainsString("JnetMemberShell::wants(\$request)", $this->src('app/Controllers/Web/AtakFirstLinkController.php'));
    }

    public function testTheInstructorLogbookViewStaysInTheBackOffice(): void
    {
        $src = $this->src('app/Controllers/Admin/Organization/PilotLogbookController.php');
        self::assertStringContainsString("'back-office/referentiels/qualifications/cursus-pilote", $src);
        $request = new \App\Core\Request();
        self::assertFalse(\App\Support\JnetMemberShell::wants($request));
    }

    public function testViewFormsAndLinksUseTheMemberSpace(): void
    {
        $logbook = $this->src('views/admin/member_situation/pilot_logbook.php');
        self::assertStringContainsString("\$mineBase = 'jnet/moi/carnet-de-vol';", $logbook);
        self::assertStringContainsString("jnet_moi_url('qualifications')", $logbook);
        self::assertStringContainsString("jnet_moi_url('mes-papiers/couverture')", $this->src('views/admin/member_situation/mes_papiers.php'));
        self::assertStringContainsString("jnet_moi_url('musique')", $this->src('views/admin/member_situation/musique.php'));
        self::assertStringContainsString("jnet_moi_url('appareils/retirer')", $this->src('views/partials/atak_device_cards.php'));
        self::assertStringContainsString("jnet_moi_url('premiere-liaison')", $this->src('views/partials/atak_device_cards.php'));
        self::assertStringContainsString("jnet_moi_url('carnet-de-vol')", $this->src('views/admin/airspace/index.php'));
        self::assertStringContainsString("jnet_moi_url('carnet-de-vol')", $this->src('views/admin/airspace/_hero.php'));
    }

    public function testNoEyebrowAndNoEmojiInThePagesOfTheGroup(): void
    {
        foreach ([
            'views/admin/member_situation/musique.php', 'views/admin/member_situation/liaison_atak.php',
            'views/partials/atak_device_cards.php', 'views/admin/member_situation/mes_papiers.php',
        ] as $file) {
            $src = $this->src($file);
            self::assertStringNotContainsString('msc-kicker', $src, $file);
            self::assertStringNotContainsString('bo-member-situation__kicker', $src, $file);
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{23E9}-\x{23FA}]/u', $src), $file . ' : emoji');
        }
        // Dans la coque JNET, le titre est celui de la coque : le hero de la première liaison n'a ni second h1 ni surtitre.
        $fl = $this->src('views/atak/first_link.php');
        self::assertStringContainsString('$inJnet', $fl);
        self::assertStringContainsString("if (!\$inJnet): ?><p class=\"fl-kicker\">", $fl);
    }

    public function testPageStylesheetsExistAndTheRouteTableMatches(): void
    {
        foreach (['jnet_moi_musique.css', 'jnet_moi_mes_papiers.css', 'jnet_moi_premiere_liaison.css'] as $css) {
            self::assertFileExists(self::ROOT . '/public/assets/css/' . $css);
            self::assertGreaterThan(200, strlen($this->src('public/assets/css/' . $css)), $css);
        }
        self::assertStringContainsString('jnet_moi_musique.css', $this->src('app/Controllers/Web/MusicLibraryController.php'));
        self::assertStringContainsString('jnet_moi_mes_papiers.css', $this->src('app/Controllers/Admin/MemberIdentityPapersController.php'));
        self::assertStringContainsString('jnet_moi_premiere_liaison.css', $this->src('app/Controllers/Web/AtakFirstLinkController.php'));
    }

    // —— Rendu réel des vues dans la coque ——

    /**
     * @param array<string, mixed> $vars données que le contrôleur donne à la vue
     */
    private function page(string $slug, string $view, string $title, array $vars, string $type = 'full'): string
    {
        $ctx = ['tenantType' => $type, 'pilotLogbook' => true, 'unreadMail' => 0, 'badges' => []];
        $css = array_merge(['jnet_portal.css', 'jnet_spaces.css', 'jnet_member.css'], (array) ($vars['backOfficePageCss'] ?? []));
        $html = Response::view('jnet._page', array_merge($vars, [
            'title' => $title . ' — Extranet d’unité',
            'content' => $view,
            'jnetInnerView' => $view,
            'jnetPageTitle' => $title,
            'jnetPageLead' => (string) ($vars['boPageSubtitle'] ?? ''),
            'jnetPageCss' => $css,
            'activeNav' => 'hub',
            'jnetTenantName' => 'Communauté',
            'jnetCanTba' => false,
            'jnetUnreadMail' => 0,
            'jnetMemberShell' => true,
            'jnetMemberPage' => JnetMemberPaths::pageOf($slug) ?? '',
            'jnetMemberRail' => JnetMemberHub::sections($ctx),
            'jnetShowExtranetTabs' => $type === 'full',
        ]))->body();
        self::assertStringContainsString('class="jm-rail"', $html, $slug);
        self::assertStringNotContainsString('Warning:', $html, $slug);
        self::assertStringNotContainsString('Fatal error', $html, $slug);
        self::assertStringNotContainsString('Notice:', $html, $slug);
        self::assertStringNotContainsString('back-office/ma-situation', $html, $slug);
        self::assertStringNotContainsString('Écran indisponible', $html, $slug);

        return $html;
    }

    /** @param list<array<string, mixed>> $entries */
    private function logbookVars(array $entries, bool $schemaReady = true): array
    {
        return [
            'boPageTitle' => 'Mon carnet de vol', 'boPageSubtitle' => 'Vos vols.',
            'backOfficePageCss' => ['referentiel-qualifications.css', 'referentiel-pilot-syllabus.css'],
            'schemaReady' => $schemaReady, 'mine' => true, 'memberId' => 7, 'entries' => $entries,
            'summary' => PilotLogbookService::summary($entries, []),
            'canReview' => [PilotSyllabusCatalog::TRACK_HELI => false, PilotSyllabusCatalog::TRACK_PLANE => false],
            'knownFamilies' => PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_HELI) + PilotSyllabusCatalog::knownFamilies(PilotSyllabusCatalog::TRACK_PLANE),
            'crewRoles' => PilotSyllabusCatalog::crewRoles(),
        ];
    }

    public function testPilotLogbookRendersWithFlightsAndEmpty(): void
    {
        $entries = [
            ['id' => 3, 'flown_on' => '2026-11-10', 'vehicle_label' => 'UH-60 Black Hawk', 'vehicle_class' => 'heli', 'vehicle_kind' => 'heli', 'mission_key' => 'OP', 'crew_role' => 'pilot', 'seconds' => 5400, 'night_seconds' => 1800, 'ifr_seconds' => 0, 'formation_seconds' => 0, 'landings' => 2, 'status' => 'verified', 'source' => 'mod', 'remarks' => 'Nuit', 'reviewer_name' => '', 'reviewer_callsign' => '', 'review_note' => ''],
            ['id' => 2, 'flown_on' => '2026-11-02', 'vehicle_label' => 'C-130', 'vehicle_class' => 'plane', 'vehicle_kind' => 'plane', 'mission_key' => '', 'crew_role' => '', 'seconds' => 3600, 'night_seconds' => 0, 'ifr_seconds' => 0, 'formation_seconds' => 0, 'landings' => 1, 'status' => 'declared', 'source' => 'pilot', 'remarks' => '', 'reviewer_name' => '', 'reviewer_callsign' => '', 'review_note' => ''],
        ];
        $html = $this->page('carnet-de-vol', 'admin.member_situation.pilot_logbook', 'Mon carnet de vol', $this->logbookVars($entries));
        self::assertStringContainsString('action="' . url('jnet/moi/carnet-de-vol/ajouter') . '"', $html);
        self::assertStringContainsString('action="' . url('jnet/moi/carnet-de-vol/3/modifier') . '"', $html);
        self::assertStringContainsString('action="' . url('jnet/moi/carnet-de-vol/2/supprimer') . '"', $html);
        self::assertStringContainsString('href="' . url('jnet/moi/qualifications') . '"', $html);
        self::assertStringContainsString('name="_csrf_token"', $html);

        $empty = $this->page('carnet-de-vol', 'admin.member_situation.pilot_logbook', 'Mon carnet de vol', $this->logbookVars([]));
        self::assertStringContainsString('Aucun vol au carnet', $empty);
        $notReady = $this->page('carnet-de-vol', 'admin.member_situation.pilot_logbook', 'Mon carnet de vol', $this->logbookVars([], false));
        self::assertStringContainsString('pas encore prêt', $notReady);
        self::assertStringNotContainsString('/ajouter', $notReady);
    }

    /** @return array<string, mixed> */
    private function papersVars(bool $empty): array
    {
        $doc = [
            'id' => 1, 'ref' => 'DOC-1', 'identity_id' => 1, 'holder_name' => 'Jean DUPONT', 'doc_type' => 'id_card', 'doc_type_label' => 'Carte d’identité',
            'country_code' => 'AU', 'country_name' => 'Altis', 'doc_number' => 'AL123456', 'issued_on' => '2026-01-01', 'expires_on' => '2036-01-01',
            'expired' => false, 'authority' => 'Préfecture', 'workflow' => 'issued', 'workflow_label' => 'Émis', 'st_validity' => 'ok', 'st_report' => 'none',
            'identity' => ['sex' => 'M', 'birth_date' => '1990-04-02', 'birth_place' => 'Kavala', 'nationality' => 'AU'], 'mrz' => ['IDALT<<DUPONT', 'AL123456<<9'],
        ];
        $papers = [
            'identities' => $empty ? [] : [['id' => 1]], 'documents' => $empty ? [] : [$doc],
            'kits' => $empty ? [] : [['scope' => 'default', 'op_key' => null, 'context_id' => null, 'document_ids' => [1]]],
            'checks' => $empty ? [] : [['created_at' => '2026-11-01 10:00:00', 'verdict_label' => 'Conforme', 'controller_callsign' => 'SENTRY', 'grid' => '045-120']],
            'steam_linked' => !$empty,
        ];
        $briefs = $empty ? [] : [['title' => 'Couverture', 'scope' => 'operation', 'op_key' => 'SPECTRE', 'context_id' => null, 'biography' => 'Ingénieur', 'legend' => '', 'contacts' => '', 'instructions' => '', 'phone' => '', 'address' => '', 'updated_at' => '2026-11-02 12:00:00']];

        return [
            'boPageTitle' => 'Mes papiers', 'boPageSubtitle' => 'Papiers fictifs.',
            'backOfficePageCss' => ['back-office-member-situation.css', 'sse-papers.css', 'back-office-member-papers.css', 'jnet_moi_mes_papiers.css'],
            'papers' => $papers, 'briefs' => $briefs, 'gameCovers' => [],
            'coverStatus' => SseIdGameCoverService::status($papers['steam_linked'], $briefs, $papers['kits'], []),
            'maps' => [['id' => 1, 'label' => 'Altis']],
            'coverPanel' => ['history' => $empty ? [] : [['id' => 5, 'cover_name' => 'Marc ROUSSEAU', 'ref' => 'CVR-1A2B3C4D', 'country_code' => 'AU', 'created_at' => '2026-11-12 09:30:00', 'downloaded_at' => null]], 'remaining' => 4, 'limit' => 5, 'countries' => [['code' => 'AU', 'name' => 'Altis']]],
        ];
    }

    public function testMyPapersRendersWithTheCoverFormAndDownloadsOnTheMemberSpace(): void
    {
        $html = $this->page('mes-papiers', 'admin.member_situation.mes_papiers', 'Mes papiers', $this->papersVars(false), 'effectifs');
        self::assertStringContainsString('action="' . url('jnet/moi/mes-papiers/couverture') . '"', $html);
        self::assertStringContainsString('href="' . url('jnet/moi/mes-papiers/couverture') . '/5/telecharger"', $html);
        self::assertStringContainsString('AL123456', $html);

        $empty = $this->page('mes-papiers', 'admin.member_situation.mes_papiers', 'Mes papiers', $this->papersVars(true));
        self::assertStringContainsString('pas encore de papiers de jeu', $empty);
        self::assertStringContainsString('Aucun kit', $empty);
    }

    public function testAtakPagesRenderWithTheirActionsOnTheMemberSpace(): void
    {
        $terminal = ['id' => 1, 'terminal_uid' => 'T-1', 'status' => 'active', 'terminal_label' => 'Samsung S7', 'last_seen_at' => gmdate('Y-m-d H:i:s')];
        $link = $this->page('liaison-atak', 'admin.member_situation.liaison_atak', 'Ma liaison ATAK', [
            'boPageTitle' => 'Ma liaison ATAK', 'boPageSubtitle' => 'Le chemin.', 'backOfficePageCss' => ['back-office-member-situation.css', 'back-office-atak-devices.css'],
            'user' => ['id' => 7, 'callsign' => 'VIPER 1-1'], 'terminals' => [$terminal], 'gamePhone' => null, 'linkEvents' => [],
        ]);
        self::assertStringContainsString('href="' . url('jnet/moi/appareils') . '"', $link);
        self::assertStringContainsString('href="' . url('jnet/moi/premiere-liaison') . '"', $link);
        self::assertStringNotContainsString('bo-member-situation__kicker', $link);

        $none = $this->page('liaison-atak', 'admin.member_situation.liaison_atak', 'Ma liaison ATAK', [
            'boPageTitle' => 'Ma liaison ATAK', 'backOfficePageCss' => ['back-office-member-situation.css'], 'user' => ['id' => 7], 'terminals' => [], 'gamePhone' => null, 'linkEvents' => [],
        ], 'atak');
        self::assertStringContainsString('Aucune liaison active', $none);
        self::assertStringContainsString('href="' . url('jnet/moi/premiere-liaison') . '"', $none);

        $devices = $this->page('appareils', 'admin.member_situation.appareils', 'Mes appareils ATAK', [
            'boPageTitle' => 'Mes appareils ATAK', 'backOfficePageCss' => ['back-office-member-situation.css', 'back-office-atak-devices.css'],
            'user' => ['id' => 7, 'callsign' => 'VIPER 1-1'], 'devices' => [$terminal], 'gamePhone' => null, 'deviceLogDigests' => [], 'appActivity' => [],
        ]);
        self::assertStringContainsString('action="' . url('jnet/moi/appareils/retirer') . '"', $devices);
        self::assertStringContainsString('href="' . url('jnet/moi/liaison-atak') . '"', $devices);

        $noDevice = $this->page('appareils', 'admin.member_situation.appareils', 'Mes appareils ATAK', [
            'boPageTitle' => 'Mes appareils ATAK', 'backOfficePageCss' => ['back-office-member-situation.css'], 'user' => ['id' => 7], 'devices' => [], 'gamePhone' => null,
            'deviceLogDigests' => [], 'appActivity' => [],
        ]);
        self::assertStringContainsString('Aucun appareil associé', $noDevice);
        self::assertStringContainsString('href="' . url('jnet/moi/premiere-liaison') . '"', $noDevice);
    }

    /** @return array<string, mixed> */
    private function firstLinkVars(bool $ready): array
    {
        return [
            'boPageTitle' => 'Configurer ATAK', 'boPageSubtitle' => 'Première liaison.', 'backOfficePageCss' => ['jnet_moi_premiere_liaison.css'],
            'firstLink' => [
                'display_name' => 'Jean Martin', 'callsign' => 'VIPER 1-1', 'steam_linked' => $ready, 'has_identity' => true, 'account_ready' => $ready,
                'has_mod' => true, 'has_access_key' => true, 'mod_page_url' => url('atak/mod'), 'mod_download_url' => 'steam://x', 'tuto_url' => url('atak/tuto'),
                'guide_url' => url('atak/mod/guide'), 'account_url' => url('account/preferences'), 'atak_url' => url('atak'), 'operateurs_url' => url('back-office/atak/operateurs'),
                'portal_url' => 'https://exemple.test', 'game_link_ready' => true, 'game_link_url' => url('atak/game-link'), 'lier_url' => url('lier'),
                'version_api_url' => url('api/atak/version'), 'atak_version' => '4.12.0', 'link_version' => '1.4', 'can_view_operators' => true,
            ],
            'steps' => [],
        ];
    }

    public function testFirstLinkRendersInTheShellWithoutASecondTitle(): void
    {
        $html = $this->page('premiere-liaison', 'atak.first_link', 'Configurer ATAK', $this->firstLinkVars(true));
        self::assertStringContainsString('<h2 class="fl-hero__title">', $html);
        self::assertStringNotContainsString('fl-kicker', $html);
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('data-me-link-url="' . url('api/atak/me-link-status') . '"', $html);
        $notReady = $this->page('premiere-liaison', 'atak.first_link', 'Configurer ATAK', $this->firstLinkVars(false), 'atak');
        self::assertStringContainsString('fl-checks', $notReady);

        // Hors de la coque (portail /atak/premiere-liaison), le titre et le surtitre d'origine restent.
        $portal = $this->renderBare('atak.first_link', $this->firstLinkVars(true));
        self::assertStringContainsString('<h1 class="fl-hero__title">', $portal);
        self::assertStringContainsString('fl-kicker', $portal);
    }

    /** @return array<string, mixed> */
    private function musicVars(string $view, bool $manager = false): array
    {
        $track = static fn (int $id, string $title, int $uploader, string $status = 'active'): array => [
            'id' => $id, 'title' => $title, 'artist' => 'Artiste', 'genre' => 'Rock', 'scope' => $id % 2 ? 'org' : 'community', 'uploader_name' => 'Sgt. Viper',
            'uploader_user_id' => $uploader, 'tenant_id' => 3, 'tenant_name' => 'Communauté', 'duration_seconds' => 215, 'play_count' => 4, 'status' => $status,
        ];
        $playlists = [
            ['id' => 1, 'name' => 'Playlist globale', 'visibility' => 'global', 'owner_name' => 'Admin', 'track_count' => 12, 'description' => '', 'owner_user_id' => 1, 'tenant_id' => 1],
            ['id' => 2, 'name' => 'Insertion de nuit', 'visibility' => 'private', 'owner_name' => 'Moi', 'track_count' => 4, 'description' => 'Pour la nuit', 'owner_user_id' => 7, 'tenant_id' => 3],
        ];
        $playlist = match ($view) {
            'global' => $playlists[0],
            'liste' => $playlists[1],
            default => null,
        };

        return [
            'boPageTitle' => 'Musique', 'boPageSubtitle' => 'Vos morceaux.', 'backOfficePageCss' => ['back-office-music.css', 'jnet_moi_musique.css'],
            'view' => $view === 'liste' ? 'playlists' : $view, 'q' => '', 'playlist' => $playlist, 'playlists' => $playlists, 'editablePlaylists' => [$playlists[1]],
            'globalPlaylist' => $playlists[0], 'canEditPlaylist' => $view === 'liste', 'isPlatformAdmin' => false, 'isOrgManager' => $manager, 'userId' => 7, 'tenantId' => 3,
            'tenantName' => 'Communauté', 'usageBytes' => 31457280, 'quotaBytes' => 209715200, 'maxBytes' => 20971520,
            'tracks' => $view === 'playlists' ? [] : [$track(1, 'Fortunate Son', 7), $track(2, 'Valkyries', 9), $track(3, 'Masqué', 7, 'hidden')],
            'canEditTrack' => static fn (array $t): bool => (int) $t['uploader_user_id'] === 7,
            'canModerateTrack' => static fn (array $t): bool => $manager,
        ];
    }

    public function testMusicRendersEveryViewWithItsFormsOnTheMemberSpace(): void
    {
        $base = url('jnet/moi/musique');
        foreach (['global', 'community', 'org', 'playlists', 'mine', 'moderation', 'liste'] as $view) {
            $html = $this->page('musique', 'admin.member_situation.musique', 'Musique', $this->musicVars($view, $view === 'moderation'));
            self::assertStringContainsString('data-base="' . $base . '"', $html, $view);
            self::assertStringContainsString('action="' . $base . '/envoyer"', $html, $view);
            self::assertStringNotContainsString('msc-kicker', $html, $view);
            self::assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{23E9}-\x{23FA}]/u', $html), $view . ' : emoji');
        }
        $mine = $this->page('musique', 'admin.member_situation.musique', 'Musique', $this->musicVars('mine'));
        self::assertStringContainsString('data-src="' . $base . '/ecouter/1"', $mine);
        self::assertStringContainsString('action="' . $base . '/morceaux/1/modifier"', $mine);
        self::assertStringContainsString('action="' . $base . '/playlists/2/ajouter"', $mine);
        $list = $this->page('musique', 'admin.member_situation.musique', 'Musique', $this->musicVars('liste'));
        self::assertStringContainsString('action="' . $base . '/playlists/2/modifier"', $list);
        self::assertStringContainsString('action="' . $base . '/playlists/2/supprimer"', $list);
        self::assertStringContainsString('action="' . $base . '/playlists/2/retirer"', $list);
        $playlists = $this->page('musique', 'admin.member_situation.musique', 'Musique', $this->musicVars('playlists'));
        self::assertStringContainsString('action="' . $base . '/playlists/creer"', $playlists);
        $moderation = $this->page('musique', 'admin.member_situation.musique', 'Musique', $this->musicVars('moderation', true));
        self::assertStringContainsString('action="' . $base . '/morceaux/3/moderer"', $moderation);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function renderBare(string $view, array $vars): string
    {
        $file = base_path('views/' . str_replace('.', '/', $view) . '.php');
        $render = static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        };

        return $render($file, $vars);
    }
}
