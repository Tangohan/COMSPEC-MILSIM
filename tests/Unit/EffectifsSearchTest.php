<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\EffectifsDirectoryController;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\UnitCodeRepository;
use App\Repositories\UserRepository;
use App\Services\Effectifs\EffectifsSearchService;
use App\Services\Effectifs\UnitCodeService;
use PDO;
use PHPUnit\Framework\TestCase;

final class EffectifsSearchTest extends TestCase
{
    private PDO $pdo;
    private array $globals;
    private array $permissions;
    private bool $platformAdmin;

    protected function setUp(): void
    {
        UnitCodeService::forget();
        UnitCodeRepository::resetCache();
        $this->globals = [$_GET, $_SERVER, $_SESSION ?? []];
        $this->permissions = Gate::getInstance()->permissionSlugs();
        $this->platformAdmin = Gate::getInstance()->isPlatformAdmin();
        Gate::getInstance()->setPlatformAdmin(false);
        $this->pdo = UnitCodeServiceTest::schema();
        $this->pdo->exec("CREATE TABLE user_units (id INTEGER PRIMARY KEY, user_id INTEGER, unit_id INTEGER, ended_at TEXT NULL);
            INSERT INTO user_units (user_id, unit_id) VALUES (7, 3), (8, 3), (9, 20);
            CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT);
            CREATE TABLE personnel_profile_job_roles (id INTEGER PRIMARY KEY, tenant_id INTEGER, user_id INTEGER, personnel_job_role_id INTEGER);
            INSERT INTO personnel_job_roles VALUES (1, 14, 'Infirmier de combat'), (2, 15, 'Infirmier de bord');
            INSERT INTO personnel_profile_job_roles VALUES (1, 14, 7, 1);
            CREATE TABLE personnel_assignments (id INTEGER PRIMARY KEY, user_id INTEGER, unit_id INTEGER, role_name TEXT, status TEXT);
            INSERT INTO personnel_assignments VALUES (1, 7, 3, 'Chef de groupe', 'active'), (2, 9, 20, 'Chef de patrouille', 'active')");
        // Communauté 15 numérotée en premier : la 14 reçoit le n° 0002.
        $codes = new UnitCodeService(new UnitCodeRepository($this->pdo));
        $codes->sync(15);
        $codes->sync(14);
    }

    protected function tearDown(): void
    {
        [$_GET, $_SERVER, $_SESSION] = $this->globals;
        Gate::getInstance()->setPermissions($this->permissions);
        Gate::getInstance()->setPlatformAdmin($this->platformAdmin);
        UnitCodeService::forget();
        UnitCodeRepository::resetCache();
    }

    private function users(int $expectedTenant = 14): UserRepository
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('searchEffectifsMembers')->willReturnCallback(static function (int $tenantId, string $q, int $limit) use ($expectedTenant): array {
            self::assertSame($expectedTenant, $tenantId, 'La recherche des membres reste dans la communauté courante.');
            self::assertLessThanOrEqual(EffectifsSearchService::LIMIT, $limit);

            return stripos('Ryan Mercer Viper', $q) !== false
                ? [['id' => 7, 'display_name' => 'Ryan Mercer', 'callsign' => 'Viper', 'character_name' => '', 'status' => 'active']]
                : [];
        });
        $users->method('listEffectifsRosterByIds')->willReturn([
            ['id' => 7, 'unit_id' => 3, 'unit_name' => 'Groupe 1', 'grade_short' => 'SSG', 'job_role_display' => 'Infirmier de combat', 'assignment_role_name' => 'Chef de groupe'],
        ]);

        return $users;
    }

    private function search(): EffectifsSearchService
    {
        return new EffectifsSearchService($this->users(), new UnitCodeService(new UnitCodeRepository($this->pdo)), $this->pdo);
    }

    public function testUnitsAreFoundByCodePrefixAndOnlyInTheCurrentCommunity(): void
    {
        $result = $this->search()->search(14, 'B-A');
        $codes = array_column($result['units'], 'code');

        self::assertContains('SOAR-B-A', $codes);
        self::assertContains('SOAR-B-A-1', $codes);
        self::assertSame([], array_filter($codes, static fn (string $c): bool => !str_starts_with($c, 'SOAR')));

        $byName = $this->search()->search(14, 'escadron');
        self::assertSame([1], array_column($byName['units'], 'id'), 'L’escadron de l’autre communauté ne sort pas.');
        self::assertSame(2, $this->search()->search(14, 'groupe 1')['units'][0]['members'] ?? null);
    }

    public function testUnitsAreFoundByAdministrativeIdentifier(): void
    {
        $unit = $this->search()->search(14, '0002-0003')['units'];
        self::assertSame([3], array_column($unit, 'id'));
        self::assertSame('0002-0003', $unit[0]['admin_id']);

        self::assertSame([3], array_column($this->search()->search(14, '00020003')['units'], 'id'));
        // Numéro d'une autre communauté : rien.
        self::assertSame([], $this->search()->search(14, '0001-0001')['units']);
    }

    public function testMembersJobsAndRolesAreGroupedAndScoped(): void
    {
        $result = $this->search()->search(14, 'viper');
        self::assertSame('Ryan Mercer', $result['members'][0]['name']);
        self::assertSame('SOAR-B-A-1', $result['members'][0]['unit_code']);

        $jobs = $this->search()->search(14, 'infirmier')['jobs'];
        self::assertSame(['Infirmier de combat'], array_column($jobs, 'name'));
        self::assertSame(1, $jobs[0]['holders']);

        $roles = $this->search()->search(14, 'chef de')['jobs'];
        self::assertSame(['Chef de groupe'], array_column($roles, 'name'));
        self::assertSame('unit_role', $roles[0]['kind']);
    }

    public function testQueryIsBounded(): void
    {
        $short = $this->search()->search(14, 'v');
        self::assertSame([[], [], []], [$short['members'], $short['units'], $short['jobs']]);
        self::assertSame(EffectifsSearchService::MAX_LENGTH, mb_strlen($this->search()->search(14, str_repeat('x', 200))['query']));
        self::assertSame([], $this->search()->search(0, 'viper')['members']);
    }

    public function testEndpointRequiresSessionAndEffectifsAccess(): void
    {
        $controller = new EffectifsDirectoryController($this->users(), null, null, $this->search());
        $_GET = ['q' => 'viper'];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $_SESSION = [];
        self::assertSame(401, $controller->search(new Request())->statusCode());

        $_SESSION = ['tenant_id' => 14, 'user_id' => 3];
        Gate::getInstance()->setPermissions(['events.view']);
        $denied = $controller->search(new Request());
        self::assertSame(403, $denied->statusCode());
        self::assertStringNotContainsString('Ryan', $denied->body());

        Gate::getInstance()->setPermissions(['organization.effectifs.hub.view']);
        $ok = json_decode($controller->search(new Request())->body(), true);
        self::assertTrue($ok['ok']);
        self::assertSame('Ryan Mercer', $ok['members'][0]['name']);
        self::assertStringContainsString('/membres/7', $ok['members'][0]['url']);
    }
}
