<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\PlanPreviewController;
use App\Core\Csrf;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\PlanPreviewReadOnlyMiddleware;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Services\Community\TenantTypeConfig;
use App\Services\Platform\EffectiveTenantType;
use PHPUnit\Framework\TestCase;

/** Aperçu de la vue (Complet, Bureau des effectifs, ATAK) : restreint seulement, lecture seule, audité, réservé à l'admin plateforme. */
final class ViewPreviewTest extends TestCase
{
    private array $serverBackup = [];
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $_SESSION = [];
        $_SERVER['HTTP_HOST'] = 'athena-comspec.eu';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        Gate::reset();
        Gate::getInstance()->setPermissions([]);
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->pdo->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
        $this->pdo->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER NULL, user_id INTEGER NULL, action TEXT, entity_type TEXT NULL, entity_id INTEGER NULL, old_value TEXT NULL, new_value TEXT NULL, ip TEXT NULL, user_agent TEXT NULL, created_at TEXT)');
        (new \ReflectionProperty(\App\Core\Database::class, 'pdo'))->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_SESSION = [];
        $_POST = [];
        Gate::reset();
        (new \ReflectionProperty(\App\Core\Database::class, 'pdo'))->setValue(null, null);
    }

    private function asPlatformAdmin(int $tenantId = 10): void
    {
        Gate::getInstance()->setPermissions([]);
        Gate::getInstance()->setPlatformAdmin(true);
        Session::set('user_id', 7);
        Session::set('tenant_id', $tenantId);
    }

    public function testNoPreviewKeepsRealType(): void
    {
        $this->asPlatformAdmin();
        self::assertSame('effectifs', EffectiveTenantType::resolve('effectifs'));
        self::assertNull(EffectiveTenantType::current());
    }

    public function testPreviewOnlyRestrictsNeverElevates(): void
    {
        $this->asPlatformAdmin();
        EffectiveTenantType::start('atak', 10);
        self::assertSame('atak', EffectiveTenantType::resolve('full'));
        self::assertSame('atak', EffectiveTenantType::resolve('effectifs'));
        self::assertSame('atak', EffectiveTenantType::resolve('atak'));

        EffectiveTenantType::start('full', 10);
        self::assertSame('effectifs', EffectiveTenantType::resolve('effectifs'), 'Complet n’agrandit pas une communauté Bureau');
        self::assertSame('atak', EffectiveTenantType::resolve('atak'));
        self::assertSame(['effectifs', 'atak'], EffectiveTenantType::previewable('effectifs'));
        self::assertSame(['full', 'effectifs', 'atak'], EffectiveTenantType::previewable('full'));
    }

    public function testMenuAndPagesFollowSimulatedView(): void
    {
        $this->asPlatformAdmin();
        self::assertTrue(TenantTypeConfig::uriAllowed(EffectiveTenantType::resolve('full'), 'formations'));
        EffectiveTenantType::start('effectifs', 10);
        $type = EffectiveTenantType::resolve('full');
        self::assertFalse(TenantTypeConfig::uriAllowed($type, 'formations'));
        self::assertTrue(TenantTypeConfig::uriAllowed($type, 'personnel'));
        EffectiveTenantType::start('atak', 10);
        self::assertFalse(TenantTypeConfig::uriAllowed(EffectiveTenantType::resolve('full'), 'personnel'));
    }

    public function testIgnoredWithoutPlatformAdminRightOrForAnotherCommunity(): void
    {
        $this->asPlatformAdmin();
        EffectiveTenantType::start('atak', 10);
        Gate::getInstance()->setPlatformAdmin(false);
        self::assertSame('full', EffectiveTenantType::resolve('full'), 'sans droit admin.system : aucun effet à l’affichage');
        self::assertNull(EffectiveTenantType::current());

        $this->asPlatformAdmin(11);
        self::assertSame('full', EffectiveTenantType::resolve('full'), 'autre communauté : aucun effet');
    }

    public function testWritesBlockedDuringViewPreview(): void
    {
        $this->asPlatformAdmin();
        EffectiveTenantType::start('atak', 10);
        $mw = new PlanPreviewReadOnlyMiddleware();
        $ok = static fn (): Response => Response::redirect('/ok');

        $res = $mw($this->request('POST', '/back-office/users/1/delete', 'document'), $ok);
        self::assertNotSame('/ok', $res->headerValue('Location'));
        self::assertStringContainsString('Aperçu de la vue', (string) Session::getFlash('error'));
        self::assertSame(403, $mw($this->request('POST', '/api/payments/checkout', 'empty'), $ok)->statusCode());
        self::assertSame('/ok', $mw($this->request('GET', '/back-office/users', 'document'), $ok)->headerValue('Location'));
        self::assertSame('/ok', $mw($this->request('POST', '/admin/apercu-formule/vue', 'document'), $ok)->headerValue('Location'));
        self::assertSame('/ok', $mw($this->request('POST', '/admin/apercu-formule/quitter', 'document'), $ok)->headerValue('Location'));

        EffectiveTenantType::stop();
        self::assertSame('/ok', $mw($this->request('POST', '/back-office/users/1/delete', 'document'), $ok)->headerValue('Location'));
    }

    public function testStartChecksCsrfRestrictsAndIsAudited(): void
    {
        $token = Csrf::token();
        $this->asPlatformAdmin();
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn(['id' => 10, 'tenant_type' => 'effectifs']);
        $ctl = new PlanPreviewController($this->createMock(SubscriptionPlanRepository::class), $tenants);

        $_POST = ['view_type' => 'atak', '_csrf_token' => 'faux'];
        $ctl->startView($this->request('POST', '/admin/apercu-formule/vue', 'document'));
        self::assertFalse(EffectiveTenantType::isActive(), 'sans jeton CSRF valide : rien');

        $_POST = ['view_type' => 'full', '_csrf_token' => $token];
        $ctl->startView($this->request('POST', '/admin/apercu-formule/vue', 'document'));
        self::assertFalse(EffectiveTenantType::isActive(), 'vue plus large que la communauté : refusée');

        $_POST = ['view_type' => 'atak', '_csrf_token' => $token];
        $ctl->startView($this->request('POST', '/admin/apercu-formule/vue', 'document'));
        self::assertTrue(EffectiveTenantType::isActive());
        $row = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'platform.view_preview_started'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(7, (int) $row['user_id']);
        self::assertSame(10, (int) $row['tenant_id']);
        self::assertStringContainsString('atak', (string) $row['new_value']);

        $_POST = ['_csrf_token' => $token];
        $ctl->stop($this->request('POST', '/admin/apercu-formule/quitter', 'document'));
        self::assertFalse(EffectiveTenantType::isActive());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'platform.view_preview_stopped'")->fetchColumn());
    }

    private function request(string $method, string $path, string $dest): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_SEC_FETCH_DEST'] = $dest;
        $_SERVER['HTTP_REFERER'] = 'https://athena-comspec.eu/back-office';

        return new Request();
    }
}
