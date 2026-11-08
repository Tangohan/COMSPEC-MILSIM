<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Web\AtakMobileAdminController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AtakMobileLinkRepository;
use App\Services\Atak\AtakGameAdminService;
use App\Services\Atak\AtakMobileAdminService;
use App\Services\Atak\AtakMobileLinkService;
use App\Services\Qr\QrPngGenerator;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Espace d'administration par QR code (/m/admin) : jeton court réservé à la gestion, session liée au navigateur qui a
 * scanné et aux droits du compte relus à chaque requête, communauté tirée du lien, tout journalisé.
 */
final class AtakMobileAdminTest extends TestCase
{
    private const OWN = 7;

    private const MANAGER = 5;

    private PDO $pdo;

    private ?PDO $savedPdo = null;

    private bool $savedSessionStarted = false;

    /** @var array{set: bool, value: mixed} */
    private array $savedSession = ['set' => false, 'value' => null];

    /** @var array<string, mixed> */
    private array $savedPost = [];

    /** @var AtakGameAdminService&MockObject */
    private AtakGameAdminService $game;

    private AtakMobileLinkService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $this->pdo->exec("CREATE TABLE atak_mobile_links (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NOT NULL, user_id INTEGER NOT NULL,
            steam_uid TEXT, mission_key TEXT, map_id INTEGER NOT NULL DEFAULT 1, app TEXT NOT NULL DEFAULT 'cr', player_name TEXT NOT NULL DEFAULT '',
            pos_x REAL, pos_y REAL, context_json TEXT, token_hash TEXT NOT NULL UNIQUE, token_expires_at TEXT NOT NULL, redeemed_at TEXT,
            session_expires_at TEXT, revoked_at TEXT, last_seen_at TEXT, ip TEXT, user_agent TEXT, created_at TEXT NOT NULL)");
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT,
            entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT)');
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
        $this->game = $this->createMock(AtakGameAdminService::class);
        $this->svc = new AtakMobileLinkService(new AtakMobileLinkRepository($this->pdo), new QrPngGenerator(), $this->pdo, null, $this->game);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedSessionStarted);
        if ($this->savedSession['set']) {
            $_SESSION = $this->savedSession['value'];
        } else {
            unset($_SESSION);
        }
        $_POST = $this->savedPost;
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    public function testOnlyAManagerGetsAnAdminQrAndTheTokenIsShort(): void
    {
        $this->game->method('role')->willReturn(null);
        $refused = $this->issue('admin');
        self::assertFalse($refused['ok']);
        self::assertSame('forbidden', $refused['error']);
        self::assertSame([], $this->pdo->query('SELECT id FROM atak_mobile_links')->fetchAll(), 'aucun jeton créé');
        self::assertStringContainsString('mobile_admin_link_refused', (string) $this->pdo->query('SELECT new_value FROM audit_logs')->fetchColumn());

        $ok = $this->managerService()->issue(self::OWN, self::MANAGER, null, null, 1, 'admin', 'Tango');
        self::assertTrue($ok['ok']);
        self::assertSame(120, $ok['expires_in'], 'jeton de deux minutes');
        self::assertSame('cr', ($this->managerService()->issue(self::OWN, self::MANAGER, null, null, 1, 'cr', 'Tango'))['app'], 'les autres apps ne changent pas');
    }

    public function testAdminSessionIsOneHourBoundToTheBrowserAndToCurrentRights(): void
    {
        $game = $this->createMock(AtakGameAdminService::class);
        $game->method('role')->willReturnOnConsecutiveCalls('manager', 'manager', null);
        $game->method('can')->willReturn(true);
        $svc = new AtakMobileLinkService(new AtakMobileLinkRepository($this->pdo), new QrPngGenerator(), $this->pdo, null, $game);
        $now = time();
        $res = $svc->issue(self::OWN, self::MANAGER, null, null, 1, 'admin', 'Tango', null, null, null, $now);
        self::assertTrue($res['ok']);
        $token = (string) substr($res['url'], (int) strrpos($res['url'], '/') + 1);
        $link = $svc->redeem($token, '10.0.0.1', 'Firefox Mobile', $now + 5);
        self::assertNotNull($link);
        self::assertNull($svc->redeem($token, '10.0.0.1', 'Firefox Mobile', $now + 6), 'usage unique');
        $expires = strtotime((string) $this->pdo->query('SELECT session_expires_at FROM atak_mobile_links')->fetchColumn() . ' UTC');
        self::assertSame($now + 5 + 3600, $expires, 'une heure, pas une partie entière');
        $_SESSION[AtakMobileLinkService::SESSION_KEY] = ['id' => (int) $link['id'], 'tenant' => self::OWN];

        self::assertNull($svc->currentAdmin('Safari autre navigateur'), 'le jeton ne sert pas dans un autre navigateur');
        $ctx = $svc->currentAdmin('Firefox Mobile');
        self::assertNotNull($ctx);
        self::assertSame(['manager', true], [$ctx['role'], $ctx['sessions']]);
        self::assertNull($svc->currentAdmin('Firefox Mobile'), 'droits retirés : plus rien, et la session est coupée');
        self::assertNull($svc->current(), 'session révoquée');
    }

    public function testAnOrdinaryLinkNeverOpensTheAdminSpace(): void
    {
        $this->game->method('role')->willReturn('manager');
        $now = time();
        $res = $this->svc->issue(self::OWN, self::MANAGER, null, null, 1, 'cr', 'Tango', null, null, null, $now);
        $token = (string) substr($res['url'], (int) strrpos($res['url'], '/') + 1);
        $link = $this->svc->redeem($token, '10.0.0.1', 'Firefox Mobile', $now);
        $_SESSION[AtakMobileLinkService::SESSION_KEY] = ['id' => (int) $link['id'], 'tenant' => self::OWN];
        self::assertNull($this->svc->currentAdmin('Firefox Mobile'));
    }

    public function testActionsUseTheLinkCommunityRecheckRightsAndAreAllLogged(): void
    {
        $this->game->method('role')->willReturn('manager');
        $this->game->expects(self::once())->method('act')
            ->with(self::OWN, 'manager', [self::MANAGER, 'Tango'], 'message', self::anything())
            ->willReturn(['ok' => true, 'message' => 'Message envoyé']);
        $svc = new AtakMobileAdminService($this->game, null, null, new \App\Services\Audit\AuditService($this->pdo));
        $link = ['id' => 11, 'tenant_id' => self::OWN, 'user_id' => self::MANAGER, 'player_name' => 'Tango'];

        $out = $svc->run($link, 'manager', false, 'message', ['terminal_id' => 12, 'title' => 'a', 'body' => 'b', 'tenant_id' => 99]);
        self::assertTrue($out['ok']);
        $out = $svc->run($link, 'manager', false, 'format_disk', []);
        self::assertFalse($out['ok']);
        $out = $svc->run($link, 'manager', false, 'session_create', ['mission_name' => 'x']);
        self::assertFalse($out['ok'], 'créer une session demande le droit de gestion');

        $lines = $this->pdo->query('SELECT tenant_id, user_id, entity_id, new_value FROM audit_logs ORDER BY id')->fetchAll();
        self::assertCount(3, $lines, 'faite, inconnue et refusée laissent toutes une ligne');
        self::assertSame([self::OWN, self::MANAGER, 11], [(int) $lines[0]['tenant_id'], (int) $lines[0]['user_id'], (int) $lines[0]['entity_id']]);
        self::assertStringContainsString('message : faite · ATAK #12', (string) $lines[0]['new_value']);
        self::assertStringContainsString('refusée', (string) $lines[1]['new_value']);
        self::assertStringContainsString('droit manquant', (string) $lines[2]['new_value']);
    }

    public function testRightsRemovedBeforeTheActionStopItAndLogTheRefusal(): void
    {
        $this->game->method('role')->willReturn(null);
        $this->game->expects(self::never())->method('act');
        $svc = new AtakMobileAdminService($this->game, null, null, new \App\Services\Audit\AuditService($this->pdo));
        $link = ['id' => 11, 'tenant_id' => self::OWN, 'user_id' => self::MANAGER, 'player_name' => 'Tango'];
        $out = $svc->run($link, 'manager', true, 'block', ['terminal_id' => 1]);
        self::assertFalse($out['ok']);
        self::assertStringContainsString('droits retirés', (string) $this->pdo->query('SELECT new_value FROM audit_logs')->fetchColumn());
    }

    public function testControllerRefusesWithoutSessionAndWithoutCsrf(): void
    {
        $admin = new AtakMobileAdminService($this->game, null, null, new \App\Services\Audit\AuditService());
        $controller = new AtakMobileAdminController($this->svc, $this->game, $admin, $this->noLimit());
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'message'];
        self::assertSame(401, $controller->act(new Request())->statusCode(), 'pas de session d’administration');

        $this->game->method('role')->willReturn('manager');
        $this->game->method('can')->willReturn(false);
        $this->game->method('act')->willReturn(['ok' => false, 'message' => 'Refusé par le site.']);
        $now = time();
        $res = $this->svc->issue(self::OWN, self::MANAGER, null, null, 1, 'admin', 'Tango', null, null, null, $now);
        $token = (string) substr($res['url'], (int) strrpos($res['url'], '/') + 1);
        $link = $this->svc->redeem($token, '10.0.0.1', 'Firefox Mobile', $now);
        $_SESSION[AtakMobileLinkService::SESSION_KEY] = ['id' => (int) $link['id'], 'tenant' => self::OWN];
        $_SERVER['HTTP_USER_AGENT'] = 'Firefox Mobile';
        self::assertSame(419, $controller->act(new Request())->statusCode(), 'CSRF obligatoire');
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();
        self::assertSame(422, $controller->act(new Request())->statusCode(), 'avec CSRF : le service et le site tranchent');
    }

    public function testRoutesViewsAndAssetsAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (["'/m/admin'", "'/m/admin/joueurs'", "'/m/admin/joueurs/{id}'", "'/m/admin/sessions'", "'/m/admin/cr'", "'/m/admin/action'", "'/m/admin/quitter'"] as $r) {
            self::assertStringContainsString($r, $routes);
            self::assertLessThan(strpos($routes, "'/m/{token}'"), strpos($routes, $r), 'avant le jeton');
        }
        foreach (['_admin_head', '_admin_foot', 'admin_expired', 'admin_home', 'admin_players', 'admin_player', 'admin_sessions', 'admin_cr'] as $v) {
            self::assertStringContainsString('declare(strict_types=1);', (string) file_get_contents($root . '/views/m/' . $v . '.php'), $v);
        }
        self::assertStringNotContainsString('email', strtolower((string) file_get_contents($root . '/views/m/admin_players.php') . (string) file_get_contents($root . '/views/m/admin_player.php')), 'aucune adresse e-mail');
        self::assertStringContainsString('X-CSRF-Token', (string) file_get_contents($root . '/public/assets/js/atak-m-admin.js'));
        self::assertArrayHasKey('admin', AtakMobileLinkService::APPS);
    }

    /** @return array<string, mixed> */
    private function issue(string $app): array
    {
        return $this->svc->issue(self::OWN, self::MANAGER, null, null, 1, $app, 'Tango');
    }

    private function managerService(): AtakMobileLinkService
    {
        $game = $this->createMock(AtakGameAdminService::class);
        $game->method('role')->willReturn('manager');

        return new AtakMobileLinkService(new AtakMobileLinkRepository($this->pdo), new QrPngGenerator(), $this->pdo, null, $game);
    }

    private function noLimit(): \App\Services\Security\FileRateLimiter
    {
        return new class () extends \App\Services\Security\FileRateLimiter {
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
    }
}
