<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\System\PlanPreviewController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Middleware\PlanPreviewReadOnlyMiddleware;
use App\Repositories\CommunityEventRepository;
use App\Repositories\PlatformUsageRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Repositories\TenantRepository;
use App\Repositories\TenantUsageCounterRepository;
use App\Repositories\TrainingCourseRepository;
use App\Repositories\UserRepository;
use App\Services\Platform\EffectivePlan;
use App\Services\Platform\FeatureGateService;
use PHPUnit\Framework\TestCase;

/** Aperçu de la formule : actif/inactif, aucune élévation de droits, écriture bloquée, audit, isolation par communauté. */
final class PlanPreviewTest extends TestCase
{
    private const PLANS = ['free' => '{"events":false,"max_members":10}', 'pro' => '{"events":true,"max_members":0}'];

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

    private function gate(array $tenant, int $members = 5): FeatureGateService
    {
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('findById')->willReturn($tenant + ['id' => 10]);
        $repo = $this->createMock(SubscriptionPlanRepository::class);
        $repo->method('findBySlug')->willReturnCallback(static fn (string $s): ?array => isset(self::PLANS[$s]) ? ['slug' => $s, 'features_json' => self::PLANS[$s]] : null);
        $users = $this->createMock(UserRepository::class);
        $users->method('countActiveForTenant')->willReturn($members);

        return new FeatureGateService(
            $tenants,
            $repo,
            $users,
            (new \ReflectionClass(TenantUsageCounterRepository::class))->newInstanceWithoutConstructor(),
            $this->createMock(PlatformUsageRepository::class),
            $this->createMock(CommunityEventRepository::class),
            $this->createMock(TrainingCourseRepository::class),
        );
    }

    public function testWithoutPreviewBehaviourIsUnchanged(): void
    {
        $this->asPlatformAdmin();
        $tenant = ['id' => 10, 'plan_slug' => 'pro', 'subscription_status' => 'active'];
        self::assertFalse(EffectivePlan::isActive());
        self::assertSame('pro', EffectivePlan::slugFor($tenant));
        $gate = $this->gate($tenant);
        self::assertTrue($gate->allowsLimitedFeatureModule(10, 'events'));
        self::assertSame(0, $gate->maxMembers(10));
    }

    public function testPreviewSimulatesAnotherPlanInTheGate(): void
    {
        $this->asPlatformAdmin();
        $tenant = ['id' => 10, 'plan_slug' => 'pro', 'subscription_status' => 'active'];
        EffectivePlan::start('free', 'Gratuit', 10);
        self::assertTrue(EffectivePlan::isActive());
        self::assertSame('free', EffectivePlan::slugFor($tenant));
        $gate = $this->gate($tenant, 10);
        self::assertFalse($gate->allowsLimitedFeatureModule(10, 'events'));
        self::assertSame(10, $gate->maxMembers(10));
        self::assertFalse($gate->canAddMember(10));
        EffectivePlan::stop();
        self::assertTrue($this->gate($tenant)->allowsLimitedFeatureModule(10, 'events'));
    }

    public function testPreviewIgnoredForNonAdminAndGrantsNoRight(): void
    {
        Session::set('tenant_id', 10);
        Session::set(EffectivePlan::SESSION_SLUG, 'pro');
        Session::set(EffectivePlan::SESSION_TENANT, 10);
        Gate::getInstance()->setPermissions(['admin.organization']);
        $tenant = ['id' => 10, 'plan_slug' => 'free', 'subscription_status' => 'none'];
        self::assertFalse(EffectivePlan::isActive());
        self::assertSame('free', EffectivePlan::slugFor($tenant), 'session forgée : la formule réelle reste appliquée');
        self::assertFalse($this->gate($tenant)->allowsLimitedFeatureModule(10, 'events'));

        // Les droits ne dépendent jamais de l'aperçu, même pour un administrateur.
        $this->asPlatformAdmin();
        EffectivePlan::start('pro', 'Pro', 10);
        self::assertSame([], Gate::getInstance()->permissionSlugs());
        Gate::getInstance()->setPlatformAdmin(false);
        Gate::getInstance()->setPermissions(['admin.organization']);
        self::assertFalse(Gate::getInstance()->allows('admin.system'), 'l’aperçu n’ajoute aucun droit');
        self::assertFalse(EffectivePlan::isActive(), 'et ne sert plus sans le droit admin');
    }

    public function testPreviewIsIsolatedToItsCommunity(): void
    {
        $this->asPlatformAdmin(10);
        EffectivePlan::start('free', 'Gratuit', 10);
        self::assertSame('pro', EffectivePlan::slugFor(['id' => 11, 'plan_slug' => 'pro', 'subscription_status' => 'active']));
        Session::set('tenant_id', 11);
        self::assertFalse(EffectivePlan::isActive(), 'changement de communauté : aperçu terminé');
        self::assertSame('pro', EffectivePlan::slugFor(['id' => 10, 'plan_slug' => 'pro', 'subscription_status' => 'active']));
    }

    public function testWritesAreBlockedDuringPreviewButReadsAndExitPass(): void
    {
        $this->asPlatformAdmin();
        EffectivePlan::start('free', 'Gratuit', 10);
        $mw = new PlanPreviewReadOnlyMiddleware();
        $ok = static fn (): Response => Response::redirect('/ok');

        $res = $mw($this->request('POST', '/back-office/users/1/delete', 'document'), $ok);
        self::assertSame(302, $res->statusCode());
        self::assertNotSame('/ok', $res->headerValue('Location'));
        self::assertStringContainsString('Aperçu de la formule Gratuit', (string) Session::getFlash('error'));

        $res = $mw($this->request('POST', '/api/payments/checkout', 'empty'), $ok);
        self::assertSame(403, $res->statusCode());
        self::assertStringContainsString('plan_preview_read_only', $res->body());

        self::assertSame('/ok', $mw($this->request('GET', '/back-office/users', 'document'), $ok)->headerValue('Location'));
        self::assertSame('/ok', $mw($this->request('POST', '/admin/apercu-formule/quitter', 'document'), $ok)->headerValue('Location'));
        self::assertSame('/ok', $mw($this->request('POST', '/logout', 'document'), $ok)->headerValue('Location'));

        EffectivePlan::stop();
        self::assertSame('/ok', $mw($this->request('POST', '/back-office/users/1/delete', 'document'), $ok)->headerValue('Location'));
    }

    public function testStartRequiresCsrfAndIsAudited(): void
    {
        // session_start() remplace $_SESSION : la démarrer avant d'y poser l'identité.
        $token = \App\Core\Csrf::token();
        $this->asPlatformAdmin();
        $plans = $this->createMock(SubscriptionPlanRepository::class);
        $plans->method('findBySlug')->willReturnCallback(static fn (string $s): ?array => $s === 'free' ? ['slug' => 'free', 'name' => 'Gratuit'] : null);
        $ctl = new PlanPreviewController($plans);

        $_POST = ['plan_slug' => 'free', '_csrf_token' => 'faux'];
        $ctl->start($this->request('POST', '/admin/apercu-formule', 'document'));
        self::assertFalse(EffectivePlan::isActive(), 'sans jeton CSRF valide : rien');

        $_POST = ['plan_slug' => 'inconnue', '_csrf_token' => $token];
        $ctl->start($this->request('POST', '/admin/apercu-formule', 'document'));
        self::assertFalse(EffectivePlan::isActive());

        $_POST = ['plan_slug' => 'free', '_csrf_token' => $token];
        $ctl->start($this->request('POST', '/admin/apercu-formule', 'document'));
        self::assertTrue(EffectivePlan::isActive());
        $row = $this->pdo->query("SELECT * FROM audit_logs WHERE action = 'platform.plan_preview_started'")->fetch(\PDO::FETCH_ASSOC);
        self::assertSame(7, (int) $row['user_id']);
        self::assertSame(10, (int) $row['tenant_id']);
        self::assertStringContainsString('free', (string) $row['new_value']);

        $_POST = ['_csrf_token' => $token];
        $ctl->stop($this->request('POST', '/admin/apercu-formule/quitter', 'document'));
        self::assertFalse(EffectivePlan::isActive());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'platform.plan_preview_stopped'")->fetchColumn());
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
