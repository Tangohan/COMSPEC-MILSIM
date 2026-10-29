<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelAssignmentRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\PersonnelProfileRepository;
use App\Repositories\UserRepository;
use App\Services\Personnel\PersonnelStructureChangeNotificationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class PersonnelStructureSnapshotTest extends TestCase
{
    public function testEmptyPivotDoesNotReviveRetainedHistoricalProfileFields(): void
    {
        $roles=$this->createMock(PersonnelJobRoleRepository::class);
        $roles->method('tablesExist')->willReturn(true);
        $roles->method('pivotTableExists')->willReturn(true);
        $roles->method('listPivotAssignmentsForUsers')->with(14,[1])->willReturn([]);
        $roles->expects(self::never())->method('findRoleById');
        $snapshot=$this->service($roles)->snapshot(14,1);
        self::assertNull($snapshot['job_role_id']);
        self::assertSame('',$snapshot['job_role_label']);
    }

    public function testCurrentPivotLabelAndDetailAreAuthoritative(): void
    {
        $roles=$this->createMock(PersonnelJobRoleRepository::class);
        $roles->method('tablesExist')->willReturn(true);
        $roles->method('pivotTableExists')->willReturn(true);
        $roles->method('listPivotAssignmentsForUsers')->willReturn([1=>[['personnel_job_role_id'=>22,'role_name'=>'RFL','role_detail'=>'Section A']]]);
        $roles->expects(self::never())->method('findRoleById');
        $snapshot=$this->service($roles)->snapshot(14,1);
        self::assertSame(22,$snapshot['job_role_id']);
        self::assertSame('RFL — Section A',$snapshot['job_role_label']);
    }

    public function testLegacyFallbackRemainsAvailableUntilThePivotSchemaExists(): void
    {
        $roles=$this->createMock(PersonnelJobRoleRepository::class);
        $roles->method('tablesExist')->willReturn(true);
        $roles->method('pivotTableExists')->willReturn(false);
        $roles->expects(self::never())->method('listPivotAssignmentsForUsers');
        $roles->method('findRoleById')->with(11,14)->willReturn(['name'=>'CNOS']);
        $snapshot=$this->service($roles)->snapshot(14,1);
        self::assertSame(11,$snapshot['job_role_id']);
        self::assertSame('CNOS',$snapshot['job_role_label']);
    }

    private function service(PersonnelJobRoleRepository $roles): PersonnelStructureChangeNotificationService
    {
        $users=$this->createMock(UserRepository::class);
        $users->method('findById')->with(1,14)->willReturn(['id'=>1]);
        $assignments=$this->createMock(PersonnelAssignmentRepository::class);
        $assignments->method('getPrimaryAssignment')->willReturn(null);
        $profiles=$this->createMock(PersonnelProfileRepository::class);
        $profiles->method('getByUserId')->willReturn(['personnel_job_role_id'=>11,'primary_role'=>'CNOS historique']);
        // Snapshot has no email side effects. Inject only its data sources, avoiding mail/DB setup.
        $service=(new ReflectionClass(PersonnelStructureChangeNotificationService::class))->newInstanceWithoutConstructor();
        foreach (['userRepository'=>$users,'personnelAssignmentRepository'=>$assignments,'personnelProfileRepository'=>$profiles,'personnelJobRoleRepository'=>$roles] as $property=>$dependency) {
            (new ReflectionProperty($service,$property))->setValue($service,$dependency);
        }
        return $service;
    }
}
