<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\GradeRepository;
use App\Services\Personnel\PersonnelJobRoleStructureService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelJobRoleStructureServiceTest extends TestCase
{
    private PDO $pdo;
    private PersonnelJobRoleStructureService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INT, category_id INT, name TEXT, source_unit_id INT)');
        $this->pdo->exec('CREATE TABLE units (id INTEGER PRIMARY KEY, tenant_id INT, name TEXT)');
        $this->pdo->exec('CREATE TABLE military_specialties (id INTEGER PRIMARY KEY, code TEXT, label_fr TEXT, label_en TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_job_role_structure (tenant_id INT, job_role_id INT, parent_job_role_id INT, linked_unit_id INT, grade_id INT, specialty_id INT, code TEXT, kind TEXT, updated_at TEXT, PRIMARY KEY(tenant_id,job_role_id))');
        $this->pdo->exec('CREATE TABLE personnel_profile_job_roles (tenant_id INT, user_id INT, personnel_job_role_id INT)');
        $this->pdo->exec('CREATE TABLE personnel_job_role_permissions (personnel_job_role_id INT, permission_id INT)');
        $this->pdo->exec('INSERT INTO tenants VALUES (7),(8)');
        $this->pdo->exec("INSERT INTO personnel_job_roles VALUES (11,7,1,'CNOS',100),(12,7,1,'RFL',NULL),(13,7,1,'Adjoint',NULL),(21,8,2,'Autre communauté',200)");
        $this->pdo->exec("INSERT INTO units VALUES (100,7,'État-major'),(200,8,'Autre unité')");
        $this->pdo->exec("INSERT INTO military_specialties VALUES (31,'TRANS','Transmissions','Signals')");
        $this->pdo->exec('INSERT INTO personnel_profile_job_roles VALUES (7,55,11)');
        $this->pdo->exec('INSERT INTO personnel_job_role_permissions VALUES (11,901)');
        $grades = $this->createMock(GradeRepository::class);
        $grades->method('listForTenant')->willReturnCallback(static fn(int $tenantId):array=>$tenantId===7?[
            ['id'=>101,'code'=>'CNE','label_long'=>'Capitaine'],
            ['id'=>501,'code'=>'CNOS','label_long'=>'Grade personnalisé','system_owner_tenant_id'=>7],
        ]:[['id'=>601,'code'=>'OTHER','label_long'=>'Grade privé','system_owner_tenant_id'=>8]]);
        $this->service = new PersonnelJobRoleStructureService($this->pdo,$grades);
    }

    private function full(): array
    {
        return ['parent_job_role_id'=>12,'linked_unit_id'=>100,'grade_id'=>501,'specialty_id'=>31,'code'=>'CNOS','kind'=>'function'];
    }

    public function testOptionsAreScopedAndIncludeNativeApplicableCustomGrades(): void
    {
        $options=$this->service->options(7);
        self::assertTrue($options['ready']);
        self::assertSame([13,11,12],array_map('intval',array_column($options['roles'],'id')));
        self::assertSame([100],array_map('intval',array_column($options['units'],'id')));
        self::assertSame([101,501],array_column($options['grades'],'id'));
        self::assertSame([31],array_map('intval',array_column($options['specialties'],'id')));
        self::assertSame(['employment'=>'Emploi','function'=>'Fonction'],$options['kinds']);
    }

    public function testSavingHierarchyDoesNotChangeAssignmentsPermissionsOrGeneratedOrigin(): void
    {
        self::assertSame([], $this->service->validate(7,11,$this->full())['errors']);
        $this->service->save(7,11,$this->full());
        self::assertSame($this->full(),$this->service->listMetadata(7)[11]);
        self::assertSame([], $this->service->listMetadata(8));
        self::assertSame('100',(string)$this->pdo->query('SELECT source_unit_id FROM personnel_job_roles WHERE id=11')->fetchColumn());
        self::assertSame([['tenant_id'=>7,'user_id'=>55,'personnel_job_role_id'=>11]],$this->pdo->query('SELECT * FROM personnel_profile_job_roles')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame([['personnel_job_role_id'=>11,'permission_id'=>901]],$this->pdo->query('SELECT * FROM personnel_job_role_permissions')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function testForeignTenantParentUnitAndCustomGradeAreRejected(): void
    {
        $result=$this->service->validate(7,11,array_replace($this->full(),['parent_job_role_id'=>21,'linked_unit_id'=>200,'grade_id'=>601]));
        self::assertArrayHasKey('parent_job_role_id',$result['errors']);
        self::assertArrayHasKey('linked_unit_id',$result['errors']);
        self::assertArrayHasKey('grade_id',$result['errors']);
        self::assertArrayHasKey('job_role_id',$this->service->validate(8,11,[])['errors']);
        $this->expectException(\InvalidArgumentException::class);
        $this->service->save(8,11,[]);
    }

    public function testParentCannotBeSelfOrADescendant(): void
    {
        self::assertArrayHasKey('parent_job_role_id',$this->service->validate(7,11,['parent_job_role_id'=>11])['errors']);
        $this->service->save(7,12,['parent_job_role_id'=>11]);
        $this->service->save(7,13,['parent_job_role_id'=>12]);
        $result=$this->service->validate(7,11,['parent_job_role_id'=>13]);
        self::assertArrayHasKey('parent_job_role_id',$result['errors']);
        try {
            $this->service->save(7,11,['parent_job_role_id'=>13]);
            self::fail('A hierarchy cycle was saved.');
        } catch (\InvalidArgumentException) {
            self::assertArrayNotHasKey(11,$this->service->listMetadata(7));
        }
    }

    public function testInvalidFieldsAreReportedBeforeAnyWrite(): void
    {
        $result=$this->service->validate(7,0,['code'=>str_repeat('x',41),'kind'=>'admin','parent_job_role_id'=>'12oops','linked_unit_id'=>['100'],'grade_id'=>-1,'specialty_id'=>999]);
        foreach (['code','kind','parent_job_role_id','linked_unit_id','grade_id','specialty_id'] as $field) self::assertArrayHasKey($field,$result['errors']);
        self::assertArrayHasKey('code',$this->service->validate(7,11,['code'=>"CNOS\nADMIN"])['errors']);
        self::assertSame([], $this->service->listMetadata(7));
    }

    public function testMetadataCanBeDetachedWithoutDeletingTheJobOrItsHolders(): void
    {
        $this->service->save(7,11,$this->full());
        $this->service->save(7,11,['kind'=>'employment']);
        self::assertSame(['parent_job_role_id'=>null,'linked_unit_id'=>null,'grade_id'=>null,'specialty_id'=>null,'code'=>'','kind'=>'employment'],$this->service->validate(7,11,[])['metadata']);
        $saved=$this->service->listMetadata(7)[11];
        self::assertNull($saved['parent_job_role_id']);
        self::assertNull($saved['linked_unit_id']);
        self::assertSame(4,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_job_roles')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles')->fetchColumn());
    }

    public function testSaveParticipatesInCallerTransaction(): void
    {
        $this->pdo->beginTransaction();
        $this->service->save(7,11,$this->full());
        self::assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
        self::assertSame([], $this->service->listMetadata(7));
    }

    public function testFailedSaveDoesNotCommitTheOuterTransaction(): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->service->save(7,11,['linked_unit_id'=>200]);
            self::fail('Cross-tenant reference was accepted.');
        } catch (\InvalidArgumentException) {
            self::assertTrue($this->pdo->inTransaction());
        } finally {
            $this->pdo->rollBack();
        }
        self::assertSame([], $this->service->listMetadata(7));
    }

    public function testMissingMigrationIsExplicitAndDoesNotTouchExistingRoles(): void
    {
        $this->pdo->exec('DROP TABLE personnel_job_role_structure');
        $service=new PersonnelJobRoleStructureService($this->pdo);
        self::assertFalse($service->schemaReady());
        self::assertSame([], $service->listMetadata(7));
        self::assertArrayHasKey('schema',$service->validate(7,11,[])['errors']);
        self::assertSame(4,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_job_roles')->fetchColumn());
    }
}
