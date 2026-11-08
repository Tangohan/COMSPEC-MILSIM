<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakMobileAppsController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AtakAdminControlRepository;
use App\Repositories\AtakMobileLinkRepository;
use App\Services\Atak\AtakMobileLinkService;
use App\Services\Atak\AtakMobileOverwatchAccess;
use App\Services\Personnel\MemberNameService;
use App\Services\Qr\QrPngGenerator;
use App\Services\Tactical\AirBriefService;
use App\Services\Tactical\NineLineBoardService;
use App\Support\AtakMobileReportCatalog;
use App\Support\ComspecApiKeyAuth;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * « Sur mon téléphone » : jeton du QR code (aléatoire, empreinte seule, usage unique, 5 minutes, débit), session mobile
 * (communauté, révocation), détection du pilote / de l'équipage, briefs air refusés sinon, WILCO depuis le téléphone,
 * fiches du compte rendu confiées à l'ATAK (contrôle, tableau SQF), câblage site et mod. Dépôt réel sur sqlite.
 */
final class AtakMobileLinkTest extends TestCase
{
    private const OWN = 7;

    private const FOREIGN = 3;

    private const PLAYER = 5;

    private PDO $pdo;

    private AtakMobileLinkService $svc;

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    /** @var array<string, mixed> */
    private array $savedPost = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = self::schema();
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $saved = $db->getValue();
        $this->savedPdo = $saved instanceof PDO ? $saved : null;
        $db->setValue(null, $this->pdo);
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedSessionStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $this->savedSession = ['set' => isset($_SESSION), 'value' => $_SESSION ?? null];
        $_SESSION = [];
        $this->savedPost = $_POST;
        MemberNameService::forgetSchema();
        $notices = new AtakAdminControlRepository($this->pdo);
        (new \ReflectionProperty(AtakAdminControlRepository::class, 'ready'))->setValue($notices, true);
        $this->svc = new AtakMobileLinkService(new AtakMobileLinkRepository($this->pdo), new QrPngGenerator(), $this->pdo, $notices);
    }

    protected function tearDown(): void
    {
        Session::actAs([]);
        ComspecApiKeyAuth::resetForTests();
        MemberNameService::forgetSchema();
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        $_POST = $this->savedPost;
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    public function testTokenIsRandomHashedAndCarriesAQrMatrix(): void
    {
        $a = AtakMobileLinkService::newToken();
        $b = AtakMobileLinkService::newToken();
        self::assertNotSame($a, $b);
        self::assertTrue(AtakMobileLinkService::tokenLooksValid($a));
        self::assertFalse(AtakMobileLinkService::tokenLooksValid('abc'));
        self::assertFalse(AtakMobileLinkService::tokenLooksValid(str_repeat('0', 20)), '0, 1, i, l, o exclus');

        $res = $this->issue('cr');
        self::assertTrue($res['ok']);
        $token = $this->tokenOf($res['url']);
        self::assertSame(300, $res['expires_in']);
        self::assertStringNotContainsString('https://', $res['short_url']);
        self::assertGreaterThanOrEqual(21, count($res['qr']));
        self::assertSame(count($res['qr']), strlen($res['qr'][0]), 'matrice carrée');
        self::assertSame(1, preg_match('/^[01]+$/', implode('', $res['qr'])));
        $stored = (string) $this->pdo->query('SELECT token_hash FROM atak_mobile_links')->fetchColumn();
        self::assertSame(hash('sha256', $token), $stored, 'seule l’empreinte du jeton est gardée');
    }

    public function testTokenServesOnceAndExpiresAfterFiveMinutes(): void
    {
        $now = time();
        $token = $this->tokenOf($this->issue('carte', $now)['url']);
        $link = $this->svc->redeem($token, '10.0.0.1', 'Firefox', $now + 10);
        self::assertNotNull($link);
        self::assertSame(['carte', self::OWN, self::PLAYER], [$link['app'], (int) $link['tenant_id'], (int) $link['user_id']]);
        self::assertNull($this->svc->redeem($token, '10.0.0.2', 'Firefox', $now + 20), 'usage unique');

        $late = $this->tokenOf($this->issue('cr', $now)['url']);
        self::assertNull($this->svc->redeem($late, '10.0.0.1', 'Firefox', $now + 301), 'expiré après 5 minutes');
        self::assertNull($this->svc->redeem('zzzzzzzzzzzzzzzzzzzz', '10.0.0.1', 'Firefox', $now), 'jeton inconnu');
    }

    public function testMobileSessionStaysInItsTenantAndCanBeRevoked(): void
    {
        $link = $this->openSession('cr');
        self::assertSame((int) $link['id'], (int) $this->svc->current()['id']);
        $repo = new AtakMobileLinkRepository($this->pdo);
        self::assertNull($repo->findActive((int) $link['id'], self::FOREIGN), 'pas de session dans une autre communauté');
        self::assertCount(1, $repo->listForUser(self::OWN, self::PLAYER));
        self::assertSame(0, $this->svc->revoke(self::FOREIGN, self::PLAYER), 'une autre communauté ne coupe rien');
        self::assertSame(1, $this->svc->revoke(self::OWN, self::PLAYER, (int) $link['id']));
        self::assertNull($this->svc->current(), 'session coupée depuis le profil');
        self::assertArrayNotHasKey(AtakMobileLinkService::SESSION_KEY, $_SESSION);
    }

    public function testIssueNeedsALinkedAccountAKnownAppAndIsRateLimited(): void
    {
        self::assertSame('not_linked', $this->svc->issue(self::OWN, 0, null, '', 1, 'cr', 'Tango')['error']);
        self::assertSame('bad_app', $this->svc->issue(self::OWN, self::PLAYER, null, '', 1, 'root', 'Tango')['error']);
        for ($i = 0; $i < AtakMobileLinkService::ISSUE_LIMIT; $i++) {
            self::assertTrue($this->issue('cr')['ok']);
        }
        self::assertSame('rate_limited', $this->issue('cr')['error']);
    }

    public function testPilotIsDetectedFromTheCrewAndOthersAreRefusedTheBriefs(): void
    {
        $link = $this->openSession('briefs');
        $ctl = $this->controller();
        self::assertSame([], $this->svc->aircrew($link));
        self::assertSame(403, $ctl->briefsData($this->req())->statusCode());
        self::assertSame(403, $ctl->briefs($this->req())->statusCode());

        $this->air('HOG 21', '[{"name":"Tango","seat":"driver","player":true}]');
        self::assertSame(['HOG 21'], $this->svc->aircrew($link), 'nom de jeu du joueur dans l’équipage');
        $this->air('VIPER 11', '[{"name":"Viper 1","seat":"gunner","player":true}]');
        self::assertSame(['HOG 21', 'VIPER 11'], $this->svc->aircrew($link), 'indicatif de membre reconnu aussi');
    }

    public function testWilcoFromThePhoneIsRecordedForTheJtac(): void
    {
        $this->air('HOG 21', '[{"name":"Tango","seat":"driver","player":true}]');
        $board = new NineLineBoardService($this->pdo, null, null, new AirBriefService($this->pdo));
        $row = $board->create(self::OWN, 9, 'HAMMER 11', 'cas', [1 => 'IP DELTA', 6 => '0234 5678'], ['HOG 21'], 1);
        $id = (int) $row['id'];
        $this->openSession('briefs');
        $ctl = $this->controller();

        $data = json_decode($ctl->briefsData($this->req())->body(), true);
        self::assertSame([$id], array_column($data['briefs'], 'id'));
        self::assertSame('IP DELTA', $data['briefs'][0]['lines'][0]);

        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'faux';
        self::assertSame(419, $ctl->briefRespond($this->req(['answer' => 'wilco']), ['id' => (string) $id])->statusCode(), 'CSRF exigé');

        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
        self::assertSame(200, $ctl->briefRespond($this->req(['answer' => 'wilco']), ['id' => (string) $id])->statusCode());
        self::assertSame('wilco', $this->pdo->query('SELECT status FROM atak_nine_line_recipients WHERE nine_line_id = ' . $id)->fetchColumn());
        self::assertSame(404, $ctl->briefRespond($this->req(['answer' => 'wilco']), ['id' => '999'])->statusCode(), 'seulement les briefs de mon appareil');
        self::assertSame(422, $ctl->briefRespond($this->req(['answer' => 'cleared hot']), ['id' => (string) $id])->statusCode());
    }

    public function testReportFromThePhoneIsCheckedAndHandedToTheAtak(): void
    {
        [$vals, $errors] = AtakMobileReportCatalog::clean('SPOTREP', ['what' => 'Véhicule léger', 'grid' => '0123 4567', 'count' => '3']);
        self::assertSame([], $errors);
        self::assertSame('ROUTINE', $vals['prio']);
        self::assertArrayNotHasKey('time', $vals, 'l’heure est mise par l’ATAK');
        self::assertSame(['what', 'grid'], AtakMobileReportCatalog::clean('SPOTREP', ['what' => 'Soucoupe', 'grid' => '12'])[1]);
        self::assertSame(['type'], AtakMobileReportCatalog::clean('ADMIN', [])[1]);
        self::assertSame('["MCR1","SALUTE",[["size","6 ""hommes"" / 2"]]]', AtakMobileReportCatalog::noticeBody('SALUTE', ['size' => '6 "hommes" | 2']));

        $this->openSession('cr');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
        $res = $this->controller()->crSubmit($this->req(['type' => 'CONTACT', 'f' => ['nature' => 'IED', 'grid' => '0123 4567']]));
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        $m = $this->pdo->query('SELECT kind, user_id, body, require_ack FROM atak_admin_messages WHERE id = ' . (int) $json['notice'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['mobile_report', self::PLAYER, 0], [$m['kind'], (int) $m['user_id'], (int) $m['require_ack']]);
        self::assertStringStartsWith('["MCR1","CONTACT",', $m['body']);
        self::assertSame(422, $this->controller()->crSubmit($this->req(['type' => 'CONTACT', 'f' => ['dist' => '< 100 M']]))->statusCode());
    }

    public function testMobilePagesAndGameSideAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (["'/m/cr'", "'/m/compte-rendu'", "'/m/carte'", "'/m/briefs'", "'/m/{token}'", "'/api/atak/mobile-link'", "'/account/security/devices/mobile/revoke'"] as $r) {
            self::assertStringContainsString($r, $routes);
        }
        self::assertLessThan(strpos($routes, "'/m/{token}'"), strpos($routes, "'/m/cr'"), 'pages avant le jeton');
        foreach (['home', 'cr', 'compte_rendu', 'carte', 'briefs', 'briefs_denied', 'expired'] as $v) {
            self::assertFileExists($root . '/views/m/' . $v . '.php');
        }
        self::assertStringContainsString("'file' => '3690_atak_mobile_links.php'", (string) file_get_contents($root . '/database/steps/registry.php'));

        $nat = $root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
        self::assertStringContainsString('class mobileQr {};', (string) file_get_contents($nat . '/config.cpp'));
        self::assertStringContainsString('"MOBILEQR"', (string) file_get_contents($nat . '/functions/ui/fn_navigate.sqf'));
        self::assertStringContainsString('case "MOBILEQR"', (string) file_get_contents($nat . '/functions/ui/fn_pageRender.sqf'));
        self::assertStringContainsString('case "qr":', (string) file_get_contents($nat . '/functions/ui/fn_formRender.sqf'));
        foreach (['reports/fn_pageReports.sqf' => '"cr"', 'debrief/fn_pageDebrief.sqf' => '"compte-rendu"', 'pages/fn_pageMap.sqf' => '"carte"', 'tactical/fn_pageJtac.sqf' => '"briefs"'] as $f => $app) {
            self::assertStringContainsString('["open", ' . $app . '] call comspec_atak_native_fnc_mobileQr', (string) file_get_contents($nat . '/functions/' . $f), $f);
        }
        self::assertStringContainsString('"MobileLink.Create"', (string) file_get_contents($nat . '/functions/ui/fn_mobileQr.sqf'));
        self::assertStringContainsString('["mobile_report", "mobile_debrief"]', (string) file_get_contents($nat . '/functions/shell/fn_adminNotices.sqf'));
        self::assertStringContainsString('case "mobile":', (string) file_get_contents($nat . '/functions/reports/fn_reportAction.sqf'));
        self::assertStringContainsString('case "mobile":', (string) file_get_contents($nat . '/functions/debrief/fn_debrief.sqf'));
        $dll = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertStringContainsString('function == "MobileLink.Create"', $dll);
        self::assertStringContainsString('"/api/atak/mobile-link"', $dll);
    }

    /**
     * Carte du téléphone : les lectures de la carte Overwatch (mêmes adresses) passent avec la session du téléphone,
     * comme le membre connecté à son Overwatch (sa communauté, son compte), et seulement elles ; refusées sans session.
     */
    public function testOverwatchReadsOpenWithThePhoneSessionOnlyAndNeverWidenRights(): void
    {
        $allowed = static fn (): bool => true;
        $bypass = new \ReflectionMethod(ComspecApiKeyAuth::class, 'authenticatedBrowserSessionMayAccessTactical');

        // Sans session du téléphone : rien n'est prêté, l'API tactique refuse (pas de communauté, pas de session).
        self::assertFalse(AtakMobileOverwatchAccess::apply('GET', '/api/units', $this->svc, $allowed));
        self::assertNull(Session::get('tenant_id'));
        self::assertFalse($bypass->invoke(null), 'sans session : refusé');

        $link = $this->openSession('carte');
        foreach (AtakMobileOverwatchAccess::READ_PATHS as $path) {
            Session::actAs([]);
            self::assertTrue(AtakMobileOverwatchAccess::apply('GET', $path, $this->svc, $allowed), $path);
            self::assertSame(self::OWN, Session::get('tenant_id'), 'communauté du lien, jamais celle demandée');
            self::assertSame(self::PLAYER, Session::get('user_id'), 'compte du lien (mêmes filtres que son Overwatch)');
            self::assertTrue($bypass->invoke(null), $path . ' : accepté avec la session du téléphone');
        }
        self::assertArrayNotHasKey('user_id', $_SESSION, 'rien n’est écrit dans la session du téléphone');
        self::assertArrayNotHasKey('tenant_id', $_SESSION);

        // Ni écriture, ni autre lecture que celles de la carte.
        foreach ([['POST', '/api/units'], ['POST', '/api/atak/markers'], ['GET', '/api/chat'], ['GET', '/api/atak/orders'], ['GET', '/api/atak/personnel'], ['GET', '/api/units/5']] as [$m, $path]) {
            Session::actAs([]);
            self::assertFalse(AtakMobileOverwatchAccess::apply($m, $path, $this->svc, $allowed), $m . ' ' . $path);
            self::assertNull(Session::get('user_id'), $m . ' ' . $path);
        }
        // Membre privé de la carte ATAK par la modération (comme à l'ouverture de l'Overwatch) : refusé.
        Session::actAs([]);
        self::assertFalse(AtakMobileOverwatchAccess::apply('GET', '/api/units', $this->svc, static fn (): bool => false));
        self::assertFalse(AtakMobileOverwatchAccess::apply('GET', '/api/units', $this->svc, static function (): bool {
            throw new \RuntimeException('base indisponible');
        }), 'contrôle impossible : refusé');
        // Navigateur déjà connecté au portail : sa propre session, rien de prêté.
        $_SESSION['user_id'] = 99;
        self::assertFalse(AtakMobileOverwatchAccess::apply('GET', '/api/units', $this->svc, $allowed));
        self::assertSame(99, Session::get('user_id'));
        unset($_SESSION['user_id']);
        // Session coupée depuis le profil (ou expirée) : refusée à la requête suivante.
        $this->svc->revoke(self::OWN, self::PLAYER, (int) $link['id']);
        self::assertFalse(AtakMobileOverwatchAccess::apply('GET', '/api/units', $this->svc, $allowed));
        self::assertNull(Session::get('tenant_id'));
    }

    /** Une seule carte : l'Overwatch et le téléphone chargent le même module, le même fond et les mêmes symboles. */
    public function testMobileMapReusesTheOverwatchMapModule(): void
    {
        $root = dirname(__DIR__, 2);
        $kit = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-mapkit.js');
        foreach (['window.OverwatchMapKit', 'function syncArmaMarkers', 'function shapeLayer', 'function syncAirAssets', 'function jtacGeoLayer', 'function unitIcon', "typeof pos === 'string'", 'row.geo_json'] as $needle) {
            self::assertStringContainsString($needle, $kit);
        }
        $ow = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        self::assertStringContainsString("window.OverwatchMapKit.create('ow-map'", $ow);
        self::assertStringContainsString('kit.syncArmaMarkers(armaMarkerLayers', $ow);
        self::assertStringContainsString('kit.jtacGeoLayer(rows)', $ow);
        self::assertStringNotContainsString("L.map('ow-map'", $ow, 'plus de seconde construction de la carte');
        $owView = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');
        self::assertLessThan(strpos($owView, '/assets/js/atak-overwatch-beta.js?v='), strpos($owView, '/assets/js/atak-overwatch-mapkit.js?v='));
        self::assertStringContainsString('AtakOverwatchMapConfig::fromMap', $owView);

        $m = (string) file_get_contents($root . '/public/assets/js/atak-m.js');
        self::assertStringContainsString('window.OverwatchMapKit.create(el, cfg, { mobile: true', $m);
        foreach (['/api/units', '/api/atak/markers', '/api/map-shapes', '/api/atak/air-assets', '/api/cas', '/api/nine-line'] as $path) {
            self::assertStringContainsString("read('" . $path . "'", $m);
            self::assertContains($path, AtakMobileOverwatchAccess::READ_PATHS);
        }
        self::assertStringNotContainsString('L.circleMarker([m.y, m.x]', $m, 'plus de ronds à la place des marqueurs');
        $foot = (string) file_get_contents($root . '/views/m/_foot.php');
        foreach (['arma-map-markers.js', 'nato-sidc-icons.js', 'milsymbol.js', 'atak-overwatch-mapkit.js', 'ATAK_MARKER_ICONS_CDN'] as $needle) {
            self::assertStringContainsString($needle, $foot);
        }
        self::assertStringContainsString("'briefs'", (string) file_get_contents($root . '/views/m/_head.php'));
        foreach (['carte', 'briefs'] as $v) {
            self::assertStringContainsString("require __DIR__ . '/_map.php'", (string) file_get_contents($root . '/views/m/' . $v . '.php'));
        }
        self::assertStringContainsString('AtakOverwatchMapConfig::fromMap', (string) file_get_contents($root . '/app/Controllers/Web/AtakMobileAppsController.php'));
        self::assertStringContainsString('AtakOverwatchMapConfig::fromMap', (string) file_get_contents($root . '/app/Controllers/Web/AtakController.php'));
        self::assertStringContainsString('AtakMobileOverwatchAccess::apply($method, $path)', (string) file_get_contents($root . '/app/Core/Application.php'));

        $cfg = AtakMobileAppsController::mapConfig(['slug' => 'altis', 'tile_pattern' => '', 'config' => ['worldSize' => 30720, 'offset_x' => 12.5, 'offset_y' => -4]]);
        self::assertSame(30720, $cfg['worldSize']);
        self::assertSame(12.5, $cfg['offsetX'], 'décalage du terrain repris (comme l’Overwatch)');
        self::assertSame(-4.0, $cfg['offsetY']);
    }

    /** Overwatch d'origine : fiches de marqueur et blocs masqués refermables (×, clic hors, Échap). */
    public function testOverwatchMarkerSheetsAndHiddenBlocksClose(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString('.ow-root [hidden]{display:none!important}', (string) file_get_contents($root . '/public/assets/css/atak-overwatch-beta.css'));
        $ow = (string) file_get_contents($root . '/public/assets/js/atak-overwatch-beta.js');
        self::assertStringContainsString('function closeArmaMarkerSheet()', $ow);
        self::assertStringContainsString("(activeTool === 'cursor' || !activeTool) && closeArmaMarkerSheet()", $ow);
        self::assertStringContainsString('map.closePopup();', $ow);
        self::assertStringContainsString('jtacGeoSignature(rows)', $ow, 'la fiche 9-LINE ouverte n’est pas refermée à chaque rafraîchissement');
    }

    /** @return array<string, mixed> */
    private function issue(string $app, ?int $now = null): array
    {
        return $this->svc->issue(self::OWN, self::PLAYER, '76561198000000001', 'Op Test@Altis#202611021900', 1, $app, 'Tango', 1000.0, 2000.0, null, $now);
    }

    /** @param array<string, mixed> $post */
    private function req(array $post = []): Request
    {
        $_SERVER['REQUEST_METHOD'] = $post === [] ? 'GET' : 'POST';
        $_POST = $post;

        return new Request();
    }

    private function tokenOf(string $url): string
    {
        return (string) substr($url, (int) strrpos($url, '/') + 1);
    }

    /** @return array<string, mixed> */
    private function openSession(string $app): array
    {
        $link = $this->svc->redeem($this->tokenOf($this->issue($app)['url']), '10.0.0.1', 'Firefox');
        self::assertNotNull($link);
        // Session::regenerate sans session PHP active (CLI) : la clé suffit.
        $_SESSION[AtakMobileLinkService::SESSION_KEY] = ['id' => (int) $link['id'], 'tenant' => (int) $link['tenant_id']];

        return $link;
    }

    private function controller(): AtakMobileAppsController
    {
        $limiter = new class () extends \App\Services\Security\FileRateLimiter {
            public function __construct()
            {
            }

            public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
            {
                return false;
            }

            public function hit(string $key, int $decaySeconds): int
            {
                return 1;
            }
        };

        return new AtakMobileAppsController($this->svc, null, $limiter, new AirBriefService($this->pdo));
    }

    /** Thème /9-line sur /m/* et fenêtres refermables (hidden doit l'emporter sur display:flex). */
    public function testMobilePagesUseNineLineThemeAndSheetsClose(): void
    {
        $root = dirname(__DIR__, 2);
        $css = (string) file_get_contents($root . '/public/assets/css/atak-m.css');
        self::assertStringContainsString('[hidden] { display: none !important; }', $css);
        self::assertStringContainsString('../fonts/nine-line/jetbrains-mono-latin.woff2', $css);
        self::assertStringContainsString('#34d399', $css);
        foreach (['received', 'read', 'wilco', 'unable'] as $st) {
            self::assertStringContainsString('.mm-ack.' . $st, $css);
        }
        $head = (string) file_get_contents($root . '/views/m/_head.php');
        self::assertStringContainsString('mm-lang', $head);
        self::assertStringNotContainsString('fonts.googleapis.com', $head);
        $js = (string) file_get_contents($root . '/public/assets/js/atak-m.js');
        self::assertStringContainsString("e.key === 'Escape'", $js);
        self::assertStringContainsString('requestFullscreen', $js);
        self::assertStringContainsString('data-map-full', (string) file_get_contents($root . '/views/m/_map.php'));
    }

    private function air(string $cs, string $crew): void
    {
        $this->pdo->prepare('INSERT INTO atak_air_assets (tenant_id, map_id, callsign, model, aircraft_type, pos_x, pos_y, alt, heading, status, pilot, crew, updated_at)
            VALUES (?, 1, ?, ?, ?, 1000, 2000, 300, 92, ?, ?, ?, ?)')
            ->execute([self::OWN, $cs, 'A-10C', 'plane', 'IN-FLIGHT', null, $crew, date('Y-m-d H:i:s')]);
    }

    private static function schema(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        $pdo->exec("CREATE TABLE atak_mobile_links (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
            steam_uid TEXT, mission_key TEXT, map_id INTEGER NOT NULL DEFAULT 1, app TEXT NOT NULL DEFAULT 'cr', player_name TEXT NOT NULL DEFAULT '',
            pos_x REAL, pos_y REAL, context_json TEXT, token_hash TEXT NOT NULL UNIQUE, token_expires_at TEXT NOT NULL, redeemed_at TEXT,
            session_expires_at TEXT, revoked_at TEXT, last_seen_at TEXT, ip TEXT, user_agent TEXT, created_at TEXT NOT NULL)");
        $pdo->exec("CREATE TABLE atak_admin_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER, steam_uid TEXT,
            terminal_uid TEXT, kind TEXT NOT NULL DEFAULT 'message', title TEXT NOT NULL, body TEXT NOT NULL, level TEXT NOT NULL DEFAULT 'info',
            require_ack INTEGER NOT NULL DEFAULT 1, expires_at TEXT, created_by_user_id INTEGER, created_at TEXT, delivered_at TEXT, acked_at TEXT, cancelled_at TEXT)");
        $pdo->exec("CREATE TABLE atak_nine_line (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, map_id INTEGER NOT NULL DEFAULT 1,
            mission_kind TEXT NOT NULL DEFAULT 'cas', mission_id TEXT, author TEXT NOT NULL, author_user_id INTEGER, assigned_aircraft TEXT,
            line1 TEXT, line2 TEXT, line3 TEXT, line4 TEXT, line5 TEXT, line6 TEXT, line7 TEXT, line8 TEXT, line9 TEXT, remarks TEXT,
            lines_checked TEXT, geo_json TEXT, status TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT)");
        $pdo->exec("CREATE TABLE atak_nine_line_recipients (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, nine_line_id INTEGER NOT NULL,
            callsign TEXT NOT NULL, label TEXT, crew_names TEXT, status TEXT NOT NULL DEFAULT 'sent', received_at TEXT, read_at TEXT, ack_at TEXT,
            ack_by TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT, UNIQUE (nine_line_id, callsign))");
        $pdo->exec('CREATE TABLE atak_brief_callsigns (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, scope TEXT NOT NULL,
            owner_key TEXT NOT NULL, callsign TEXT NOT NULL, updated_by_user_id INTEGER, updated_at TEXT, UNIQUE (tenant_id, scope, owner_key))');
        $pdo->exec('CREATE TABLE atak_maps (id INTEGER PRIMARY KEY, label TEXT)');
        $pdo->exec("INSERT INTO atak_maps (id, label) VALUES (1, 'Altis')");
        $pdo->exec('CREATE TABLE atak_air_assets (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, map_id INTEGER, callsign TEXT, model TEXT,
            aircraft_type TEXT, freq TEXT, laser TEXT, pos_x REAL, pos_y REAL, alt REAL, heading REAL, side TEXT, status TEXT, pilot TEXT, crew TEXT,
            fuel_pct INTEGER, updated_at TEXT)');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, display_name TEXT, callsign TEXT)');
        $pdo->exec('CREATE TABLE user_profiles (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)');
        $pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT,
            entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)');
        $pdo->exec("INSERT INTO users (id, tenant_id, display_name, callsign) VALUES (5, 7, 'paul_d', 'Viper 1')");

        return $pdo;
    }
}
