<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\AtakMapPhotoController;
use App\Controllers\Api\AtakPlanApiController;
use App\Controllers\Api\AtakTocApiController;
use App\Controllers\Api\AtakTocBaseController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\AtakPlanRepository;
use App\Services\AtakToc\PlanService;
use App\Services\AtakToc\TocAccess;
use App\Services\AtakToc\TocRoomService;
use App\Support\AtakMapPhotoStore;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Poste TOC web et plans : chaque route est rattachée et vérifie le droit, le jeton CSRF sur toute écriture,
 * aucune fuite entre communautés ou vers un membre sans partage ; entrées du menu seulement pour qui a le droit ;
 * tuiles du fond satellite servies sans traversée de dossier et avec un cache long.
 */
final class AtakTocApiAccessTest extends TestCase
{
    private PDO $pdo;

    private ?PDO $savedPdo = null;

    private bool $savedStarted = false;

    /** @var array<string, mixed> */
    private array $savedServer = [];

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite requis');
        }
        $this->pdo = AtakTocPlanServiceTest::open();
        // Journal d'audit du site (NOW() de MySQL rendu en sqlite).
        if ($this->pdo instanceof \Pdo\Sqlite) {
            $this->pdo->createFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        } elseif (method_exists($this->pdo, 'sqliteCreateFunction')) {
            $this->pdo->sqliteCreateFunction('NOW', static fn (): string => gmdate('Y-m-d H:i:s'), 0);
        }
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER, action TEXT, entity_type TEXT, entity_id INTEGER, old_value TEXT, new_value TEXT, ip TEXT, user_agent TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $db = new \ReflectionProperty(Database::class, 'pdo');
        $cur = $db->getValue();
        $this->savedPdo = $cur instanceof PDO ? $cur : null;
        $db->setValue(null, $this->pdo);
        $started = new \ReflectionProperty(Session::class, 'started');
        $this->savedStarted = (bool) $started->getValue();
        $started->setValue(null, true);
        $_SESSION = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD', 'HTTP_IF_NONE_MATCH'] as $k) {
            if (array_key_exists($k, $_SERVER)) {
                $this->savedServer[$k] = $_SERVER[$k];
            }
            unset($_SERVER[$k]);
        }
        AtakTocBaseController::$testBody = null;
    }

    protected function tearDown(): void
    {
        Gate::reset();
        AtakTocBaseController::$testBody = null;
        (new \ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->savedPdo);
        (new \ReflectionProperty(Session::class, 'started'))->setValue(null, $this->savedStarted);
        $_SESSION = [];
        foreach (['HTTP_X_CSRF_TOKEN', 'REQUEST_METHOD', 'HTTP_IF_NONE_MATCH'] as $k) {
            unset($_SERVER[$k]);
        }
        foreach ($this->savedServer as $k => $v) {
            $_SERVER[$k] = $v;
        }
    }

    /** @param list<string> $perms */
    private function signIn(int $tenant, int $user, array $perms, bool $csrf = true): void
    {
        $_SESSION = ['tenant_id' => $tenant, 'user_id' => $user, '_csrf_token' => 'jeton'];
        if ($csrf) {
            $_SERVER['HTTP_X_CSRF_TOKEN'] = 'jeton';
        } else {
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
        }
        $_SERVER['REQUEST_METHOD'] = 'POST';
        Gate::getInstance()->setPermissions($perms);
    }

    private function tocApi(): AtakTocApiController
    {
        $rooms = new TocRoomService($this->pdo, static fn (): ?int => 1, static fn (): ?string => 'pending');

        return new AtakTocApiController(new TocAccess(null, static fn (): array => []), $rooms);
    }

    private function planApi(): AtakPlanApiController
    {
        $svc = new PlanService(new AtakPlanRepository($this->pdo), static fn (): ?int => 1);

        return new AtakPlanApiController(new TocAccess(null, static fn (): array => []), $svc, static fn (): array => []);
    }

    /** @return array<string, mixed> */
    private static function json(\App\Core\Response $r): array
    {
        $d = json_decode($r->body(), true);

        return is_array($d) ? $d : [];
    }

    public function testRoomRequiresMemberPermissionAndCsrf(): void
    {
        self::assertSame(401, $this->tocApi()->state(new Request())->statusCode());
        $this->signIn(1, 7, []);
        self::assertSame(403, $this->tocApi()->state(new Request())->statusCode(), 'sans atak.toc.use');
        $this->signIn(1, 7, ['atak.toc.use'], false);
        AtakTocBaseController::$testBody = json_encode(['act' => 'log', 'args' => ['text' => 'Test'], 'side' => 'WEST']);
        self::assertSame(419, $this->tocApi()->action(new Request())->statusCode(), 'CSRF');
        $this->signIn(1, 7, ['atak.toc.use']);
        $ok = $this->tocApi()->action(new Request());
        self::assertSame(200, $ok->statusCode(), $ok->body());
        self::assertSame('Test', self::json($ok)['state']['log'][0]['text']);
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'atak_toc.room.log'")->fetchColumn(), 'action journalisée');
        $state = $this->tocApi()->state(new Request());
        self::assertNotNull($state->headerValue('ETag'));
        $_SERVER['HTTP_IF_NONE_MATCH'] = (string) $state->headerValue('ETag');
        self::assertSame(304, $this->tocApi()->state(new Request())->statusCode());
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        // Autre communauté : rien de la salle de la communauté 1.
        $this->signIn(2, 8, ['atak.toc.use']);
        self::assertSame([], self::json($this->tocApi()->state(new Request()))['state']['log']);
        // Remise à zéro : configuration du poste seulement.
        self::assertSame(403, $this->tocApi()->reset(new Request())->statusCode());
        $this->signIn(1, 7, ['atak.toc.manage']);
        self::assertSame(200, $this->tocApi()->reset(new Request())->statusCode());
    }

    public function testPlansAreScopedAndWritesChecked(): void
    {
        $this->signIn(1, 7, ['atak.toc.use']);
        AtakTocBaseController::$testBody = json_encode(['title' => 'Opération RAVEN', 'world' => 'altis', 'mapId' => 1]);
        $created = $this->planApi()->store(new Request());
        self::assertSame(201, $created->statusCode(), $created->body());
        $id = (int) self::json($created)['id'];
        AtakTocBaseController::$testBody = json_encode(['upserts' => [['uid' => 'e_obj', 'kind' => 'objective', 'geom' => 'area', 'pts' => [[1, 1], [50, 1], [50, 50]]]]]);
        $saved = $this->planApi()->elements(new Request(), ['id' => (string) $id]);
        self::assertArrayHasKey('e_obj', self::json($saved)['saved']);

        // Membre de la même communauté, plan privé : 404 (pas même son existence).
        $this->signIn(1, 8, ['atak.toc.use']);
        self::assertSame(404, $this->planApi()->show(new Request(), ['id' => (string) $id])->statusCode());
        self::assertSame(404, $this->planApi()->elements(new Request(), ['id' => (string) $id])->statusCode());
        // Autre communauté, même avec la configuration du poste : 404.
        $this->signIn(2, 7, ['atak.toc.manage']);
        self::assertSame(404, $this->planApi()->show(new Request(), ['id' => (string) $id])->statusCode());
        self::assertSame([], self::json($this->planApi()->index(new Request()))['plans']);
        // Plan partagé à la communauté : lisible et modifiable par un membre qui tient le poste.
        $this->signIn(1, 7, ['atak.toc.use']);
        AtakTocBaseController::$testBody = json_encode(['sharing' => 'community']);
        self::assertSame(200, $this->planApi()->update(new Request(), ['id' => (string) $id])->statusCode());
        $this->signIn(1, 8, ['atak.toc.use']);
        $show = self::json($this->planApi()->show(new Request(), ['id' => (string) $id]));
        self::assertTrue($show['can_edit']);
        self::assertFalse($show['can_admin']);
        self::assertNull($show['plan']['share_token'], 'jeton visible de l’auteur seulement');
        self::assertSame(403, $this->planApi()->destroy(new Request(), ['id' => (string) $id])->statusCode());
        self::assertSame(403, $this->planApi()->shareLink(new Request(), ['id' => (string) $id])->statusCode());
        // Sans jeton CSRF : écriture refusée.
        $this->signIn(1, 8, ['atak.toc.use'], false);
        AtakTocBaseController::$testBody = json_encode(['upserts' => []]);
        self::assertSame(419, $this->planApi()->elements(new Request(), ['id' => (string) $id])->statusCode());
        // Import JSON invalide refusé.
        $this->signIn(1, 8, ['atak.toc.use']);
        AtakTocBaseController::$testBody = json_encode(['import' => '{"format":"autre"}']);
        self::assertSame(422, $this->planApi()->store(new Request())->statusCode());
        // Modèle de la communauté : configuration du poste seulement.
        AtakTocBaseController::$testBody = json_encode(['title' => 'Modèle', 'is_template' => true]);
        self::assertSame(403, $this->planApi()->store(new Request())->statusCode());
    }

    public function testEveryRouteIsWiredBehindMemberMiddleware(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        foreach ([
            "'/atak/toc', [\$tocWeb, 'console']", "'/atak/toc/plans', [\$tocWeb, 'plans']", "'/atak/toc/plans/{id}', [\$tocWeb, 'editor']",
            "'/atak/toc/plans/lien/{token}', [\$tocWeb, 'sharedLink']", "'/atak/toc/api/state', [\$tocApi, 'state']", "'/atak/toc/api/action', [\$tocApi, 'action']",
            "'/atak/toc/api/plans/{id}/publish', [\$planApi, 'publish']", "'/atak/toc/api/plans/{id}/game-import', [\$planApi, 'gameImport']",
        ] as $needle) {
            self::assertStringContainsString($needle, $routes);
        }
        preg_match_all("#router->(get|post)\('/atak/toc[^\n]*#", $routes, $m);
        self::assertGreaterThanOrEqual(26, count($m[0]));
        foreach ($m[0] as $line) {
            self::assertStringContainsString('$mwAtakMemberOnly', $line, $line);
        }
        // Liens précis avant {id} (le premier qui correspond gagne).
        self::assertLessThan(strpos($routes, "'/atak/toc/plans/{id}', [\$tocWeb"), strpos($routes, "'/atak/toc/plans/lien/{token}'"));
        $web = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Web/AtakTocController.php');
        self::assertSame(3, substr_count($web, '$this->access->canUse()) {' . "\n" . '            return $this->denied();'));
        self::assertStringContainsString('Csrf::verify($request)', $web);
    }

    public function testPermissionsMenusAndStepsAreDeclared(): void
    {
        $root = dirname(__DIR__, 2);
        $slugs = array_column(\App\Authorization\TenantPermissionCatalog::definitions(), 'slug');
        self::assertContains('atak.toc.use', $slugs);
        self::assertContains('atak.toc.manage', $slugs);
        $matrix = (string) file_get_contents($root . '/app/Services/Rbac/RolePermissionMatrixCatalog.php');
        self::assertStringContainsString("'atak.toc.use', 'atak.toc.manage'", $matrix);
        $registry = require $root . '/database/steps/registry.php';
        $files = array_column($registry, 'file');
        self::assertContains('4370_atak_toc_plans.php', $files);
        self::assertContains('4380_atak_toc_permissions.php', $files);
        $nav = (string) file_get_contents($root . '/config/navigation.php');
        self::assertMatchesRegularExpression("#'path' => 'atak/toc'[^\n]*'any_permissions' => \['atak.toc.use', 'atak.toc.manage', 'admin.organization'\]#", $nav);
        $header = (string) file_get_contents($root . '/views/partials/athena_caverne_header.php');
        self::assertStringContainsString("if (\$canToc) {\n    \$navItems[] = ['key' => 'toc'", $header);
        $ow = (string) file_get_contents($root . '/views/atak-overwatch-beta.php');
        self::assertStringContainsString("<?php if (\$owCanToc): ?>", $ow);
        self::assertStringContainsString("can('atak.toc.use')", $ow);
        $mig = (string) file_get_contents($root . '/database/steps/4380_atak_toc_permissions.php');
        self::assertStringContainsString("'atak.toc.use'", $mig);
    }

    public function testTileServiceRefusesTraversalAndCachesLong(): void
    {
        $root = sys_get_temp_dir() . '/toc-tiles-' . bin2hex(random_bytes(4));
        mkdir($root . '/altis/sat/2/1', 0777, true);
        file_put_contents($root . '/altis/sat/meta.json', json_encode(['extent' => 32768, 'tileSize' => 256, 'minZoom' => 0, 'maxZoom' => 4]));
        file_put_contents($root . '/altis/sat/2/1/3.webp', 'RIFF0000WEBP');
        file_put_contents($root . '/secret.txt', 'non');
        try {
            $ctl = new AtakMapPhotoController(new AtakMapPhotoStore($root));
            $ok = $ctl->tile(new Request(), ['world' => 'altis', 'mode' => 'sat', 'z' => '2', 'x' => '1', 'file' => '3.webp']);
            self::assertSame(200, $ok->statusCode());
            self::assertStringContainsString('max-age=604800', (string) $ok->headerValue('Cache-Control'));
            self::assertNotNull($ok->headerValue('ETag'));
            foreach ([
                ['world' => '..', 'mode' => 'sat', 'z' => '2', 'x' => '1', 'file' => '3.webp'],
                ['world' => 'altis', 'mode' => '../..', 'z' => '2', 'x' => '1', 'file' => '3.webp'],
                ['world' => 'altis', 'mode' => 'sat', 'z' => '2', 'x' => '1', 'file' => '../../../secret.txt'],
                ['world' => 'altis', 'mode' => 'sat', 'z' => '2', 'x' => '..', 'file' => '3.webp'],
                ['world' => 'altis', 'mode' => 'sat', 'z' => '30', 'x' => '1', 'file' => '3.webp'],
                ['world' => 'altis', 'mode' => 'sat', 'z' => '2', 'x' => '9', 'file' => '3.webp'],
                ['world' => 'altis', 'mode' => 'sat', 'z' => '2', 'x' => '1', 'file' => '3.php'],
            ] as $params) {
                self::assertSame(404, $ctl->tile(new Request(), $params)->statusCode(), json_encode($params) ?: '');
            }
        } finally {
            @unlink($root . '/altis/sat/2/1/3.webp');
            @unlink($root . '/altis/sat/meta.json');
            @unlink($root . '/secret.txt');
            @rmdir($root . '/altis/sat/2/1');
            @rmdir($root . '/altis/sat/2');
            @rmdir($root . '/altis/sat');
            @rmdir($root . '/altis');
            @rmdir($root);
        }
    }

    public function testThemeTokensMatchTheGame(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak-toc.css');
        self::assertSame(1, preg_match('#/\* toc-tokens:start \*/(.*?)/\* toc-tokens:end \*/#s', $css, $m));
        preg_match_all('/--toc-([A-Za-z0-9]+): (#[0-9a-f]{6});/', $m[1], $pairs, PREG_SET_ORDER);
        $tokens = [];
        foreach ($pairs as $p) {
            $tokens[$p[1]] = $p[2];
        }
        self::assertCount(22, $tokens);
        self::assertSame('#34d399', $tokens['accent']);
        $sqf = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocTheme.sqf';
        if (is_file($sqf) && preg_match('/private _tokens = (\[.*?\]);\s*\n\s*\/\/ ---- FIN/s', (string) file_get_contents($sqf), $t) === 1) {
            foreach (json_decode($t[1], true) as [$k, $v]) {
                self::assertSame(strtolower($v), $tokens[$k] ?? null, 'jeton ' . $k . ' : php tools/toc/sync_toc_tokens.php');
            }
        }
        // Aucun texte sous 12 px dans la feuille du TOC.
        preg_match_all('/font(?:-size)?:[^;]*?\b(\d+(?:\.\d+)?)px/', $css, $sizes);
        foreach ($sizes[1] as $px) {
            self::assertGreaterThanOrEqual(12, (float) $px);
        }
    }
}
