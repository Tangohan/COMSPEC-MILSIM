<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\EffectifsWorkspaceController;
use App\Core\Container;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\MemberGradeChangeRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\ElevationEligibility;
use App\Services\Moderation\ModerationService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Fiche membre : droits et cloison par communauté des nouvelles saisies
 * (sanctions, critères d’élévation) et historique des grades.
 */
final class MemberFileDataAccessTest extends TestCase
{
    private PDO $pdo;
    private mixed $previousPdo;
    private array $globals;
    private array $permissions;
    private bool $platformAdmin;
    private array $containerInstances;
    private mixed $sessionStarted;

    protected function setUp(): void
    {
        $this->globals = [$_GET, $_POST, $_SERVER, $_SESSION ?? []];
        $this->permissions = Gate::getInstance()->permissionSlugs();
        $this->platformAdmin = Gate::getInstance()->isPlatformAdmin();
        $_GET = $_POST = [];
        $this->sessionStarted = (new ReflectionProperty(Session::class, 'started'))->getValue();
        (new ReflectionProperty(Session::class, 'started'))->setValue(null, true);
        $_SESSION = ['tenant_id' => 14, 'user_id' => 7, '_csrf_token' => str_repeat('a', 64)];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, display_name TEXT);
            INSERT INTO users VALUES (7, \'Cpt. Reyes\'), (21, \'PFC Anderson\');
            CREATE TABLE audit_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,action TEXT,entity_type TEXT,entity_id INTEGER,old_value TEXT,new_value TEXT,ip TEXT,user_agent TEXT,created_at TEXT)');
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
        $this->containerInstances = (new ReflectionProperty(Container::class, 'instances'))->getValue();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Container::class, 'instances'))->setValue(null, $this->containerInstances);
        (new ReflectionProperty(Session::class, 'started'))->setValue(null, $this->sessionStarted);
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->previousPdo);
        [$_GET, $_POST, $_SERVER, $_SESSION] = $this->globals;
        Gate::getInstance()->setPermissions($this->permissions);
        Gate::getInstance()->setPlatformAdmin($this->platformAdmin);
    }

    private function controller(array $dependencies = []): EffectifsWorkspaceController
    {
        $controller = (new ReflectionClass(EffectifsWorkspaceController::class))->newInstanceWithoutConstructor();
        foreach ($dependencies as $property => $value) {
            (new ReflectionProperty($controller, $property))->setValue($controller, $value);
        }

        return $controller;
    }

    private function post(array $data): Request
    {
        $_POST = $data + ['_csrf_token' => $_SESSION['_csrf_token']];

        return new Request();
    }

    private function bind(string $id, object $instance): void
    {
        $prop = new ReflectionProperty(Container::class, 'instances');
        $instances = $prop->getValue();
        $instances[$id] = $instance;
        $prop->setValue(null, $instances);
    }

    private function usersFindingMember(?array $member): UserRepository
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturnCallback(
            static fn (int $id, ?int $tenantId = null): ?array => $member !== null && $id === (int) $member['id'] && $tenantId === 14 ? $member : null
        );

        return $users;
    }

    public function testWarningRequiresTheMemberRestrictionRight(): void
    {
        $service = $this->createMock(ModerationService::class);
        $service->expects(self::never())->method('applySanction');
        $this->bind(ModerationService::class, $service);
        Gate::getInstance()->setPermissions(['personnel.status.manage']);

        $res = $this->controller(['userRepository' => $this->usersFindingMember(['id' => 21])])
            ->declareSanction($this->post(['reason' => 'Retard']), ['id' => 21]);

        self::assertSame(302, $res->statusCode());
        self::assertStringContainsString('habilité', (string) ($_SESSION['_flash']['error'] ?? ''));
    }

    public function testWarningIsRecordedAtCommunityLevelForAMemberOfTheCommunity(): void
    {
        $service = $this->createMock(ModerationService::class);
        $service->expects(self::once())->method('applySanction')
            ->with(14, 7, 21, 'warn', 'Absence non excusée', null, [], 'tenant')
            ->willReturn(5);
        $this->bind(ModerationService::class, $service);
        Gate::getInstance()->setPermissions(['personnel.status.manage', 'admin.members.moderate']);

        $res = $this->controller(['userRepository' => $this->usersFindingMember(['id' => 21])])
            ->declareSanction($this->post(['reason' => '  Absence non excusée ']), ['id' => 21]);

        self::assertStringContainsString('membres/21#sanctions', (string) $res->headerValue('Location'));
        self::assertStringContainsString('Avertissement enregistré', (string) ($_SESSION['_flash']['success'] ?? ''));
    }

    public function testNoWarningForAMemberOfAnotherCommunityNorForOneself(): void
    {
        $service = $this->createMock(ModerationService::class);
        $service->expects(self::never())->method('applySanction');
        $this->bind(ModerationService::class, $service);
        Gate::getInstance()->setPermissions(['personnel.status.manage', 'admin.members.moderate']);

        $this->controller(['userRepository' => $this->usersFindingMember(null)])
            ->declareSanction($this->post(['reason' => 'x']), ['id' => 99]);
        $this->controller(['userRepository' => $this->usersFindingMember(['id' => 7])])
            ->declareSanction($this->post(['reason' => 'x']), ['id' => 7]);
        $this->controller(['userRepository' => $this->usersFindingMember(['id' => 21])])
            ->declareSanction($this->post(['reason' => '   ']), ['id' => 21]);
    }

    public function testLiftOnlyTouchesAMeasureOfThisMemberInThisCommunity(): void
    {
        $repo = $this->createMock(ModerationRepository::class);
        $repo->method('findActionById')->willReturnCallback(
            static fn (int $tenantId, int $actionId): ?array => $tenantId === 14 && $actionId === 3 ? ['id' => 3, 'target_user_id' => 30] : null
        );
        $service = $this->createMock(ModerationService::class);
        $service->expects(self::never())->method('revokeForScope');
        $this->bind(ModerationRepository::class, $repo);
        $this->bind(ModerationService::class, $service);
        Gate::getInstance()->setPermissions(['personnel.status.manage', 'admin.members.moderate']);

        $this->controller()->liftSanction($this->post([]), ['id' => 21, 'sanctionId' => 3]);
        $this->controller()->liftSanction($this->post([]), ['id' => 21, 'sanctionId' => 8]);
    }

    public function testLiftUsesTheCommunityScope(): void
    {
        $repo = $this->createMock(ModerationRepository::class);
        $repo->method('findActionById')->willReturn(['id' => 3, 'target_user_id' => 21]);
        $service = $this->createMock(ModerationService::class);
        $service->expects(self::once())->method('revokeForScope')->with(14, 3, 7, 'tenant')->willReturn(true);
        $this->bind(ModerationRepository::class, $repo);
        $this->bind(ModerationService::class, $service);
        Gate::getInstance()->setPermissions(['personnel.status.manage', 'admin.members.moderate']);

        $this->controller()->liftSanction($this->post([]), ['id' => 21, 'sanctionId' => 3]);
    }

    public function testElevationCriteriaAreReservedToGradeManagersAndKeepOtherSettings(): void
    {
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->expects(self::never())->method('replaceSettings');
        Gate::getInstance()->setPermissions(['personnel.status.manage']);
        $this->controller(['tenantRepository' => $tenants])
            ->saveElevationCriteria($this->post(['min_months_in_grade' => '6']), []);

        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('getSettings')->with(14)->willReturn(['grade_system_code' => 'us']);
        $tenants->expects(self::once())->method('replaceSettings')->with(14, self::callback(static function (array $s): bool {
            $c = $s[ElevationEligibility::SETTINGS_KEY] ?? [];

            return ($s['grade_system_code'] ?? '') === 'us'
                && $c['min_months_in_grade'] === 6
                && $c['min_presence_rate_90'] === 100
                // Aucune qualification de cette communauté dans la base de test : la qualification envoyée est refusée.
                && $c['required_qualification_ids'] === [];
        }));
        Gate::getInstance()->setPermissions(['personnel.grades.manage']);
        $this->controller(['tenantRepository' => $tenants])->saveElevationCriteria($this->post([
            'min_months_in_grade' => '6',
            'min_presence_rate_90' => '180',
            'required_qualification_ids' => ['55'],
        ]), []);
    }

    public function testGradeHistoryRecordsRealChangesPerCommunity(): void
    {
        $this->pdo->exec('CREATE TABLE member_grade_changes (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id INTEGER, user_id INTEGER,
            from_grade_id INTEGER, to_grade_id INTEGER, from_label TEXT, to_label TEXT, source TEXT, actor_user_id INTEGER, changed_at TEXT)');
        $repo = new MemberGradeChangeRepository($this->pdo);

        self::assertFalse($repo->record(14, 21, 3, 3, 'PFC', 'PFC', 7), 'grade inchangé : rien n’est noté');
        self::assertTrue($repo->record(14, 21, 2, 3, 'PVT', 'PFC', 7, null, '2026-08-12 10:00:00'));
        MemberGradeChangeRepository::withSource(
            MemberGradeChangeRepository::SOURCE_ELEVATION,
            static fn () => $repo->record(14, 21, 3, 4, 'PFC', 'SPC', 7, null, '2026-10-01 10:00:00')
        );
        self::assertTrue($repo->record(15, 21, null, 9, null, 'Autre', 7));

        $rows = $repo->listForUser(14, 21);
        self::assertCount(2, $rows, 'les changements d’une autre communauté ne sont pas mélangés');
        self::assertSame(['manual', 'elevation'], array_column($rows, 'source'));
        self::assertSame('Cpt. Reyes', $rows[0]['actor_display_name']);
        self::assertSame(MemberGradeChangeRepository::SOURCE_MANUAL, MemberGradeChangeRepository::currentSource());
    }
}
