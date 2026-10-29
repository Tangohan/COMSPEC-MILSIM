<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\Organization\PersonnelJobRoleAdminController;
use App\Core\Gate;
use App\Core\Request;
use App\Core\Session;
use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelStructureChangeNotificationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class PersonnelJobRoleAssignmentIndependenceTest extends TestCase
{
    private array $globals;
    private array $permissions;
    private bool $platformAdmin;

    protected function setUp(): void
    {
        $this->globals = [$_GET, $_POST, $_SERVER, $_SESSION ?? []];
        $this->permissions = Gate::getInstance()->permissionSlugs();
        $this->platformAdmin = Gate::getInstance()->isPlatformAdmin();
        Session::start();
        Gate::getInstance()->setPermissions(['personnel.assignments.manage']);
        $_GET = [];
        $_SESSION = ['tenant_id'=>14, 'user_id'=>7, '_csrf_token'=>str_repeat('a', 64)];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        [$_GET, $_POST, $_SERVER, $_SESSION] = $this->globals;
        Gate::getInstance()->setPermissions($this->permissions);
        Gate::getInstance()->setPlatformAdmin($this->platformAdmin);
    }

    private function controller(array $slots): PersonnelJobRoleAdminController
    {
        $roles = $this->createMock(PersonnelJobRoleRepository::class);
        $roles->method('tablesExist')->willReturn(true);
        $roles->method('pivotTableExists')->willReturn(true);
        $roles->method('personnelProfilesHaveJobRoleColumns')->willReturn(true);
        $roles->method('listPivotAssignmentsForUsers')->willReturn([5=>[
            ['personnel_job_role_id'=>10, 'role_name'=>'CNOS', 'role_detail'=>'Permanent', 'is_primary'=>1],
        ]]);
        $roles->expects(self::once())->method('replaceUserPivotJobRoles')->with(14, 5, $slots)
            ->willReturn(['primary_role_display'=>'CNOS', 'secondary_role_display'=>'RFL']);
        $assignments = $this->createMock(PersonnelAssignmentRepository::class);
        $assignments->method('getPrimaryAssignment')->with(5)->willReturn([
            'unit_id'=>100, 'unit_name'=>'État-major', 'role_name'=>'Commandant de groupe',
        ]);
        $assignments->expects(self::never())->method('syncPrimaryAssignmentFromDossier');
        $assignments->expects(self::never())->method('replaceActiveAssignmentsFromDossier');
        $profiles = $this->createMock(PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->with(5)->willReturn(['primary_unit_id'=>100]);
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->with(5, 14)->willReturn(['id'=>5, 'display_name'=>'Membre test']);
        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('getSettings')->with(14)->willReturn([
            'personnel_job_roles_assignments'=>['sync_jobs_from_assignments'=>true],
        ]);

        $notifications = (new ReflectionClass(PersonnelStructureChangeNotificationService::class))->newInstanceWithoutConstructor();
        foreach (['userRepository'=>$users, 'personnelAssignmentRepository'=>$assignments, 'personnelProfileRepository'=>$profiles, 'personnelJobRoleRepository'=>$roles] as $key=>$dependency) {
            (new ReflectionProperty($notifications, $key))->setValue($notifications, $dependency);
        }
        $controller = (new ReflectionClass(PersonnelJobRoleAdminController::class))->newInstanceWithoutConstructor();
        foreach (['jobRoleRepository'=>$roles, 'userRepository'=>$users, 'personnelProfileRepository'=>$profiles, 'personnelAssignmentRepository'=>$assignments, 'tenantRepository'=>$tenants, 'structureChangeNotification'=>$notifications] as $key=>$dependency) {
            (new ReflectionProperty($controller, $key))->setValue($controller, $dependency);
        }
        return $controller;
    }

    public function testAssignmentTabSavesJobsWithoutRewritingTheOrbatPost(): void
    {
        $slots = [
            ['personnel_job_role_id'=>10, 'role_detail'=>'Permanent', 'is_primary'=>true],
            ['personnel_job_role_id'=>11, 'role_detail'=>'Suppléant', 'is_primary'=>false],
        ];
        $_POST = ['_csrf_token'=>$_SESSION['_csrf_token'], 'user_id'=>'5', 'primary_slot'=>'0', 'slots'=>[
            ['role_id'=>'10', 'detail'=>'Permanent'], ['role_id'=>'11', 'detail'=>'Suppléant'],
        ]];
        $response = $this->controller($slots)->saveAssignment(new Request());
        self::assertStringContainsString('mis à jour', $_SESSION['_flash']['success']);
        self::assertStringContainsString('vue=attributions', $response->headerValue('Location'));
    }

    public function testRemovingTheLastJobLeavesTheExistingOrbatPostUntouched(): void
    {
        $controller = $this->controller([]);
        $result = (new ReflectionMethod($controller, 'commitJobRoleSlots'))->invoke($controller, 14, 5, 'Membre test', []);
        self::assertTrue($result['ok']);
        self::assertStringContainsString('Membre test', $result['message']);
    }
}
