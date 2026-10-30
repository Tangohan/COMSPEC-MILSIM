<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\EffectifsWorkspaceController;
use App\Core\Database;
use App\Core\Gate;
use App\Core\Request;
use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Personnel\PersonnelJobRoleHistoryService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class PersonnelJobRoleHistoryTest extends TestCase
{
    private PDO $pdo;
    private mixed $previousPdo;
    private array $globals;
    private array $permissions;
    private bool $admin;
    private EffectifsWorkspaceController $controller;
    private PersonnelJobRoleHistoryService $history;

    protected function setUp(): void
    {
        $this->globals=[$_GET,$_POST,$_SERVER,$_SESSION??[]];
        $gate=Gate::getInstance();
        $this->permissions=$gate->permissionSlugs();$this->admin=$gate->isPlatformAdmin();
        $gate->setPlatformAdmin(false);$gate->setPermissions(['personnel.assignments.manage']);
        $_SESSION=['tenant_id'=>14,'user_id'=>7,'_csrf_token'=>str_repeat('a',64)];
        $_GET=$_POST=[];$_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_X_EFFECTIFS_FRAGMENT']='jobs';
        $this->previousPdo=(new ReflectionProperty(Database::class,'pdo'))->getValue();
        $this->pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        (new ReflectionProperty(Database::class,'pdo'))->setValue(null,$this->pdo);
        $this->pdo->exec("CREATE TABLE tenants(id INTEGER PRIMARY KEY); INSERT INTO tenants VALUES(14),(15);
            CREATE TABLE users(id INTEGER PRIMARY KEY,tenant_id INTEGER,display_name TEXT,status TEXT); INSERT INTO users VALUES(7,14,'Robin','active');
            CREATE TABLE personnel_job_role_categories(id INTEGER PRIMARY KEY,tenant_id INTEGER,parent_id INTEGER,name TEXT,slug TEXT,sort_order INTEGER);
            INSERT INTO personnel_job_role_categories VALUES(1,14,NULL,'Opérations','operations',0),(2,15,NULL,'Autre','other',0);
            CREATE TABLE personnel_job_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,category_id INTEGER,name TEXT,slug TEXT,description TEXT,sort_order INTEGER,is_system INTEGER);
            INSERT INTO personnel_job_roles VALUES(1,14,1,'Parent','parent','Conservé',0,0);
            CREATE TABLE personnel_job_role_structure(tenant_id INTEGER,job_role_id INTEGER,parent_job_role_id INTEGER,linked_unit_id INTEGER,grade_id INTEGER,specialty_id INTEGER,code TEXT,kind TEXT,updated_at TEXT,UNIQUE(tenant_id,job_role_id));
            CREATE TABLE units(id INTEGER PRIMARY KEY,tenant_id INTEGER,name TEXT); INSERT INTO units VALUES(10,14,'État-major');
            CREATE TABLE personnel_profile_job_roles(tenant_id INTEGER,user_id INTEGER,personnel_job_role_id INTEGER);
            CREATE TABLE audit_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,action TEXT,entity_type TEXT,entity_id INTEGER,old_value TEXT,new_value TEXT,created_at TEXT);");
        $repo=$this->getMockBuilder(PersonnelJobRoleRepository::class)->disableOriginalConstructor()->onlyMethods(['tablesExist','jobRolesHaveMosColumns'])->getMock();
        $repo->method('tablesExist')->willReturn(true);$repo->method('jobRolesHaveMosColumns')->willReturn(false);
        (new ReflectionProperty(PersonnelJobRoleRepository::class,'pdo'))->setValue($repo,$this->pdo);
        $this->controller=(new ReflectionClass(EffectifsWorkspaceController::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($this->controller,'personnelJobRoleRepository'))->setValue($this->controller,$repo);
        $this->history=new PersonnelJobRoleHistoryService($this->pdo);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Database::class,'pdo'))->setValue(null,$this->previousPdo);
        [$_GET,$_POST,$_SERVER,$_SESSION]=$this->globals;
        Gate::getInstance()->setPermissions($this->permissions);Gate::getInstance()->setPlatformAdmin($this->admin);
    }

    private function write(string $method,array $input): array
    {
        $_POST=$input+['_csrf_token'=>str_repeat('a',64)];
        return json_decode($this->controller->$method(new Request())->body(),true,32,JSON_THROW_ON_ERROR);
    }

    private function create(): int
    {
        $result=$this->write('createJobRole',['name'=>'Radio','description'=>'Responsable radio','category_id'=>'1',
            'structure_enabled'=>'1','parent_job_role_id'=>'1','linked_unit_id'=>'10','code'=>'RAD','kind'=>'function','_workspace_category'=>'1']);
        self::assertTrue($result['ok'],$result['message']);
        self::assertStringContainsString('categorie_emplois=1',$result['redirect']);
        return (int)$this->pdo->query("SELECT id FROM personnel_job_roles WHERE slug='radio'")->fetchColumn();
    }

    public function testCreateEditArchiveAndRestorePreserveDefinitionsAndAuditBeforeAfter(): void
    {
        $id=$this->create();
        $edited=$this->write('saveJobRole',['id'=>(string)$id,'name'=>'RFL','description'=>'Responsable local','category_id'=>'1',
            'structure_enabled'=>'1','parent_job_role_id'=>'1','linked_unit_id'=>'10','code'=>'RFL','kind'=>'function']);
        self::assertTrue($edited['ok']);
        $events=$this->history->recent(14);
        self::assertSame('Robin',$events[0]['actor_name']);
        self::assertSame('Radio',json_decode($events[0]['old_value'],true)['name']);
        self::assertSame('RFL',json_decode($events[0]['new_value'],true)['name']);
        self::assertTrue($this->write('deleteJobRole',['id'=>(string)$id])['ok']);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_job_roles WHERE id='.$id)->fetchColumn());
        $archives=$this->history->archives(14);self::assertCount(1,$archives);
        self::assertSame('RFL',json_decode($archives[0]['old_value'],true)['structure']['code']);
        self::assertTrue($this->write('restoreJobRole',['archive_id'=>(string)$archives[0]['id']])['ok']);
        $restored=$this->pdo->query("SELECT * FROM personnel_job_roles WHERE slug='radio'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame('RFL',$restored['name']);self::assertSame('Responsable local',$restored['description']);
        $metadata=$this->pdo->query('SELECT * FROM personnel_job_role_structure WHERE job_role_id='.(int)$restored['id'])->fetch(PDO::FETCH_ASSOC);
        self::assertSame('RFL',$metadata['code']);self::assertSame(1,(int)$metadata['parent_job_role_id']);self::assertSame(10,(int)$metadata['linked_unit_id']);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles')->fetchColumn());
        self::assertCount(0,$this->history->archives(14));
        self::assertFalse($this->write('restoreJobRole',['archive_id'=>(string)$archives[0]['id']])['ok']);
        self::assertCount(4,$this->history->recent(14));
    }

    public function testArchiveFailureRollsBackTheRoleAndItsStructure(): void
    {
        $id=$this->create();$this->pdo->exec('DROP TABLE audit_logs');
        $result=$this->write('deleteJobRole',['id'=>(string)$id]);
        self::assertFalse($result['ok']);
        self::assertSame('Radio',$this->pdo->query('SELECT name FROM personnel_job_roles WHERE id='.$id)->fetchColumn());
        self::assertSame('RAD',$this->pdo->query('SELECT code FROM personnel_job_role_structure WHERE job_role_id='.$id)->fetchColumn());
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testArchivesAreTenantScopedAndUnavailableReferencesNeverSilentlyDisappear(): void
    {
        $id=$this->create();self::assertTrue($this->write('deleteJobRole',['id'=>(string)$id])['ok']);
        $archive=$this->history->archives(14)[0];
        $_SESSION['tenant_id']=15;
        self::assertCount(0,$this->history->archives(15));self::assertCount(0,$this->history->recent(15));
        self::assertFalse($this->write('restoreJobRole',['archive_id'=>(string)$archive['id']])['ok']);
        $_SESSION['tenant_id']=14;$this->pdo->exec('DELETE FROM units WHERE id=10');
        $result=$this->write('restoreJobRole',['archive_id'=>(string)$archive['id']]);
        self::assertFalse($result['ok']);self::assertStringContainsString('rattachement',$result['message']);
        self::assertCount(1,$this->history->archives(14));
    }

    public function testAssignedJobsAndParentsCannotBeArchivedAndRestoreCannotOverwriteAnotherJob(): void
    {
        $id=$this->create();
        self::assertFalse($this->write('deleteJobRole',['id'=>'1'])['ok']);
        $this->pdo->exec('INSERT INTO personnel_profile_job_roles VALUES(14,7,'.$id.')');
        self::assertFalse($this->write('deleteJobRole',['id'=>(string)$id])['ok']);
        $this->pdo->exec('DELETE FROM personnel_profile_job_roles');
        self::assertTrue($this->write('deleteJobRole',['id'=>(string)$id])['ok']);
        $archive=$this->history->archives(14)[0];
        $this->pdo->exec("INSERT INTO personnel_job_roles VALUES(100,14,1,'Nouveau','radio','À garder',0,0)");
        self::assertFalse($this->write('restoreJobRole',['archive_id'=>(string)$archive['id']])['ok']);
        self::assertSame('À garder',$this->pdo->query('SELECT description FROM personnel_job_roles WHERE id=100')->fetchColumn());
    }

    public function testLegacyEnglishLabelAndImportedUnitAreAlsoRecoverable(): void
    {
        $id=$this->create();
        $this->pdo->exec("ALTER TABLE personnel_job_roles ADD COLUMN label_en TEXT;
            ALTER TABLE personnel_job_roles ADD COLUMN source_unit_id INTEGER;
            UPDATE personnel_job_roles SET label_en='Radio operator',source_unit_id=10 WHERE id=".$id);
        self::assertTrue($this->write('deleteJobRole',['id'=>(string)$id])['ok']);
        $archive=$this->history->archives(14)[0];
        self::assertTrue($this->write('restoreJobRole',['archive_id'=>(string)$archive['id']])['ok']);
        $restored=$this->pdo->query("SELECT label_en,source_unit_id FROM personnel_job_roles WHERE slug='radio'")->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['label_en'=>'Radio operator','source_unit_id'=>10],$restored);
    }

    public function testHistoryAndArchivePaginationCanReachOlderChangesWithoutDuplicates(): void
    {
        for($id=1;$id<=55;$id++) $this->history->record(14,7,'job_role',$id,'archived',['name'=>'Emploi '.$id,'slug'=>'job-'.$id,'category_id'=>1],[]);
        $first=$this->history->recent(14);self::assertCount(51,$first);
        $next=$this->history->recent(14,(int)$first[49]['id']);self::assertCount(5,$next);
        self::assertSame([5,4,3,2,1],array_map('intval',array_column($next,'id')));
        $older=$this->history->archives(14,(int)$first[49]['id']);self::assertCount(5,$older);
        self::assertSame(array_column($next,'id'),array_column($older,'id'));
    }

    public function testRestoreRequiresBothManagementPermissionAndCsrf(): void
    {
        Gate::getInstance()->setPermissions(['organization.effectifs.hub.view']);
        self::assertFalse($this->write('restoreJobRole',['archive_id'=>'1'])['ok']);
        Gate::getInstance()->setPermissions(['personnel.assignments.manage']);
        self::assertFalse($this->write('restoreJobRole',['archive_id'=>'1','_csrf_token'=>'wrong'])['ok']);
    }
}
