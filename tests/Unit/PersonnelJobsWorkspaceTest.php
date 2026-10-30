<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\EffectifsWorkspaceController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelJobRoleAssignmentsSettings;
use App\Services\Personnel\PersonnelStructureChangeNotificationService;
use App\Services\Personnel\UnitJobRoleSyncService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class PersonnelJobsWorkspaceTest extends TestCase
{
    private PDO $pdo;
    private mixed $previousPdo;
    private array $globals;
    private array $permissions;
    private bool $platformAdmin;

    protected function setUp(): void
    {
        $this->globals = [$_GET, $_POST, $_SERVER, $_SESSION ?? []];
        $this->permissions = Gate::getInstance()->permissionSlugs();
        $this->platformAdmin = Gate::getInstance()->isPlatformAdmin();
        Gate::getInstance()->setPermissions(['personnel.assignments.manage']);
        $_GET = $_POST = [];
        $_SESSION = ['tenant_id' => 14, 'user_id' => 7, '_csrf_token' => str_repeat('a', 64)];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_EFFECTIFS_FRAGMENT'] = 'jobs';
        $this->previousPdo = (new ReflectionProperty(Database::class, 'pdo'))->getValue();
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec("CREATE TABLE tenants(id INTEGER PRIMARY KEY, settings TEXT); INSERT INTO tenants VALUES(14,'{}');
            CREATE TABLE audit_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,action TEXT,entity_type TEXT,entity_id INTEGER,old_value TEXT,new_value TEXT,created_at TEXT)");
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $this->pdo);
    }

    protected function tearDown(): void
    {
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

    public function testWritesRequireManagementPermissionAndCsrfBeforeAnyRepositoryAccess(): void
    {
        $controller = $this->controller();
        Gate::getInstance()->setPermissions(['organization.effectifs.hub.view']);
        $denied = $controller->createJobRole($this->post(['name' => 'CNOS']));
        self::assertFalse(json_decode($denied->body(), true)['ok']);
        self::assertStringContainsString('habilité', $denied->body());
        Gate::getInstance()->setPermissions(['personnel.assignments.manage']);
        $expired = $controller->createJobRole($this->post(['_csrf_token' => 'invalid']));
        self::assertStringContainsString('Session expir', $expired->body());
        self::assertSame('private, no-store', $expired->headerValue('Cache-Control'));
    }

    public function testForeignCategoryIsRejectedWithoutCreatingAnEmployment(): void
    {
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->expects(self::once())->method('findCategoryById')->with(99, 14)->willReturn(null);
        $roles->expects(self::never())->method('createRole');
        $response = $this->controller(['personnelJobRoleRepository' => $roles])->createJobRole($this->post([
            'name' => 'CNOS', 'category_id' => '99',
        ]));
        self::assertFalse(json_decode($response->body(), true)['ok']);
        self::assertStringContainsString('communaut', $response->body());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testCategoryCannotBeMovedUnderItsDescendantOrAForeignParent(): void
    {
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->method('findCategoryById')->willReturnCallback(static function (int $id, int $tenant): ?array {
            self::assertSame(14, $tenant);
            return match ($id) {
                1 => ['id' => 1, 'parent_id' => null, 'slug' => 'operations'],
                2 => ['id' => 2, 'parent_id' => 1, 'slug' => 'operations-sub'],
                default => null,
            };
        });
        $roles->expects(self::never())->method('updateCategory');
        $controller = $this->controller(['personnelJobRoleRepository' => $roles]);
        foreach (['2', '99'] as $parent) {
            $response = $controller->saveJobCategory($this->post([
                'category_edit_id' => '1', 'category_name' => 'Opérations', 'parent_id' => $parent,
            ]));
            self::assertFalse(json_decode($response->body(), true)['ok']);
            self::assertFalse($this->pdo->inTransaction());
        }
    }

    public function testInvalidJobNameReturnsJsonAndNeverPersists(): void
    {
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->expects(self::never())->method('createRole');
        $response = $this->controller(['personnelJobRoleRepository' => $roles])->createJobRole($this->post(['name' => ['wrong']]));
        $body = json_decode($response->body(), true);
        self::assertFalse($body['ok']);
        self::assertStringContainsString('120', $body['message']);
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testMalformedRecoveryFilterCannotTriggerDefaultRestoration(): void
    {
        $response = $this->controller()->recoverJobRoles($this->post(['labels' => ['CNOS']]));
        self::assertFalse(json_decode($response->body(), true)['ok']);
        self::assertStringContainsString('libell', $response->body());
        $parse = new ReflectionMethod(EffectifsWorkspaceController::class, 'jobRecoveryLabels');
        self::assertSame(['CNOS', 'RFL'], $parse->invoke($this->controller(), $this->post(['labels' => " CNOS;RFL\nCNOS "])));
    }

    public function testChoosingOrganisationDoesNotSilentlyUseFirstCategory(): void
    {
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->expects(self::once())->method('findCategoryIdBySlug')->with(14, 'organisation')->willReturn(12);
        $roles->expects(self::never())->method('listCategories');
        $roles->expects(self::never())->method('createCategory');
        $controller = $this->controller(['personnelJobRoleRepository' => $roles]);
        self::assertSame(12, (new ReflectionMethod($controller, 'resolveJobCategoryId'))->invoke($controller, 14, 0));
    }

    public function testAssignmentsLeaveJobsUntouchedByDefaultAndOptOutPreservesOtherSettings(): void
    {
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->expects(self::never())->method('tablesExist');
        $roles->expects(self::never())->method('ensureUserHasJobRole');
        (new UnitJobRoleSyncService($this->pdo, $roles))->applyAssignments(14, 1, [['unit_id' => 10, 'role_name' => 'CNOS']]);
        self::assertFalse(PersonnelJobRoleAssignmentsSettings::resolve([])['sync_jobs_from_assignments']);
        $settings = ['another_module' => ['enabled' => true], 'personnel_job_roles_assignments' => ['max_roles_per_member' => 8]];
        $settings = PersonnelJobRoleAssignmentsSettings::mergePatch($settings, ['sync_jobs_from_assignments' => true]);
        self::assertTrue(PersonnelJobRoleAssignmentsSettings::resolve($settings)['sync_jobs_from_assignments']);
        self::assertSame(8, PersonnelJobRoleAssignmentsSettings::resolve($settings)['max_roles_per_member']);
        self::assertSame(['enabled' => true], $settings['another_module']);
    }

    public function testEditingDossierJobsDoesNotRewriteAnOrbatAssignment(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->with(5, 14)->willReturn(['id' => 5]);
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->method('tablesExist')->willReturn(true);
        $roles->method('pivotTableExists')->willReturn(true);
        $roles->method('findRoleById')->with(10, 14)->willReturn(['id' => 10, 'name' => 'CNOS']);
        $roles->method('listPivotAssignmentsForUsers')->willReturn([5 => [['personnel_job_role_id' => 10, 'role_detail' => 'À conserver']]]);
        $roles->expects(self::once())->method('replaceUserPivotJobRoles')->with(14, 5, [
            ['personnel_job_role_id' => 10, 'role_detail' => 'À conserver', 'is_primary' => true],
        ])->willReturn(['primary_role_display' => 'CNOS']);
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('getSettings')->willReturn([]);
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->expects(self::never())->method('syncPrimaryAssignmentFromDossier');
        $assignments->expects(self::never())->method('replaceActiveAssignmentsFromDossier');
        $notifications = (new ReflectionClass(PersonnelStructureChangeNotificationService::class))->newInstanceWithoutConstructor();
        $profiles = $this->createMock(PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn([]);
        foreach (['userRepository' => $users, 'personnelAssignmentRepository' => $assignments, 'personnelProfileRepository' => $profiles, 'personnelJobRoleRepository' => $roles] as $property => $dependency) {
            (new ReflectionProperty($notifications, $property))->setValue($notifications, $dependency);
        }
        $controller = $this->controller([
            'userRepository' => $users, 'personnelJobRoleRepository' => $roles,
            'tenantRepository' => $tenants, 'personnelAssignmentRepository' => $assignments,
            'structureChangeNotification' => $notifications,
        ]);
        $controller->updateMemberJobRoles($this->post(['job_role_ids' => ['10'], 'primary_job_role_id' => '10']), ['id' => 5]);
        self::assertStringContainsString('mises à jour', (string) ($_SESSION['_flash']['success'] ?? ''));
    }

    public function testExplicitAssignmentSyncAddsOnlyTheCurrentTenantsJobs(): void
    {
        $this->pdo->exec("UPDATE tenants SET settings = '{\"personnel_job_roles_assignments\":{\"sync_jobs_from_assignments\":true}}' WHERE id=14;
            CREATE TABLE units(id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT);
            INSERT INTO units VALUES(10,14,'État-major'),(20,15,'Autre communauté');
            CREATE TABLE personnel_job_role_categories(id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT);
            INSERT INTO personnel_job_role_categories VALUES(1,14,'organisation');
            CREATE TABLE personnel_job_roles(id INTEGER PRIMARY KEY, tenant_id INTEGER, slug TEXT);
            INSERT INTO personnel_job_roles VALUES(10,14,'unit-10');");
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->method('tablesExist')->willReturn(true);
        $roles->method('pivotTableExists')->willReturn(true);
        $roles->expects(self::once())->method('findOrCreateImportedRoleByLabel')->with(14, 'CNOS')->willReturn(11);
        $roles->expects(self::once())->method('ensureUserHasJobRole')->with(14, 5, 11);
        $cache = new ReflectionProperty(UnitJobRoleSyncService::class, 'sourceUnitColumn');
        $previousCache = $cache->getValue();
        $cache->setValue(null, false);
        try {
            (new UnitJobRoleSyncService($this->pdo, $roles))->applyAssignments(14, 5, [
                ['unit_id' => 10, 'role_name' => 'CNOS'],
                ['unit_id' => 20, 'role_name' => 'Ne pas attribuer'],
            ]);
        } finally {
            $cache->setValue(null, $previousCache);
        }
    }

    public function testUnavailableReferenceRemainsSelectedUntilExplicitlyReplacedOrCleared(): void
    {
        $body = Response::view('admin.effectifs_workspace.fonctions', [
            'canManageAssignments' => true,
            'selectedJobRole' => ['id' => 1, 'name' => 'CNOS', 'structure' => ['grade_id' => 88]],
            'jobStructureOptions' => ['ready' => true, 'roles' => [], 'units' => [], 'grades' => [], 'specialties' => [], 'kinds' => ['employment' => 'Emploi']],
            'csrfToken' => str_repeat('a', 64),
        ])->body();
        self::assertStringContainsString('<option value="88" selected>Référence indisponible (#88)</option>', $body);
        self::assertStringContainsString('détacher explicitement', $body);
    }

    public function testUnifiedCategoryFragmentLoadsJobsWithoutGradesHoldersOrAnyHrWorkspaceService(): void
    {
        $roles=$this->createMock(PersonnelJobRoleRepository::class);
        $roles->expects(self::once())->method('listCategoriesWithJobCounts')->with(14)->willReturn([
            ['id'=>1,'name'=>'Opérations','parent_id'=>null,'job_count'=>2],
        ]);
        $roles->expects(self::once())->method('listRolesWithCategory')->with(14)->willReturn([]);
        $roles->expects(self::never())->method('listHoldersByJobRoleIds');
        $_GET=['vue'=>'categories','nouvelle_categorie'=>'1','parent'=>'1'];
        $_SERVER['REQUEST_METHOD']='GET';
        // No other controller dependency is initialised: any unrelated service access fails.
        $response=$this->controller(['personnelJobRoleRepository'=>$roles])->fonctions(new Request());
        self::assertSame(200,$response->statusCode());
        self::assertSame('private, no-store',$response->headerValue('Cache-Control'));
        self::assertStringContainsString('Créer la sous-catégorie',$response->body());
    }

    public function testSubcategoryIsReallyCreatedMovedAndRenamedWithoutAllowingACycle(): void
    {
        $this->pdo->exec("CREATE TABLE personnel_job_role_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,parent_id INTEGER,name TEXT,slug TEXT,sort_order INTEGER);
            INSERT INTO personnel_job_role_categories VALUES(1,14,NULL,'Opérations','operations',0),(2,14,1,'Transmissions','transmissions',0);");
        $roles=$this->getMockBuilder(PersonnelJobRoleRepository::class)->disableOriginalConstructor()->onlyMethods(['tablesExist'])->getMock();
        $roles->method('tablesExist')->willReturn(true);
        (new ReflectionProperty(PersonnelJobRoleRepository::class,'pdo'))->setValue($roles,$this->pdo);
        $controller=$this->controller(['personnelJobRoleRepository'=>$roles]);
        $created=$controller->saveJobCategory($this->post(['category_name'=>'Radio','parent_id'=>'2']));
        self::assertTrue(json_decode($created->body(),true)['ok']);
        $id=(int)$this->pdo->query("SELECT id FROM personnel_job_role_categories WHERE name='Radio'")->fetchColumn();
        self::assertGreaterThan(2,$id);
        self::assertSame(2,(int)$this->pdo->query('SELECT parent_id FROM personnel_job_role_categories WHERE id='.$id)->fetchColumn());
        self::assertStringContainsString('categorie_emplois='.$id,json_decode($created->body(),true)['redirect']);
        $cycle=$controller->saveJobCategory($this->post(['category_edit_id'=>'1','category_name'=>'Opérations','parent_id'=>(string)$id]));
        self::assertFalse(json_decode($cycle->body(),true)['ok']);
        $saved=$controller->saveJobCategory($this->post(['category_edit_id'=>(string)$id,'category_name'=>'Systèmes radio','parent_id'=>'1']));
        self::assertTrue(json_decode($saved->body(),true)['ok']);
        self::assertSame(['name'=>'Systèmes radio','parent_id'=>1],$this->pdo->query('SELECT name,parent_id FROM personnel_job_role_categories WHERE id='.$id)->fetch(PDO::FETCH_ASSOC));
    }
}
