<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelJobRoleRecoveryService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelJobRoleRecoveryServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec("CREATE TABLE tenants(id INTEGER PRIMARY KEY);INSERT INTO tenants VALUES(14),(15);
            CREATE TABLE users(id INTEGER PRIMARY KEY,tenant_id INTEGER);INSERT INTO users VALUES(1,14),(2,14),(3,15),(4,14);
            CREATE TABLE units(id INTEGER PRIMARY KEY,tenant_id INTEGER);INSERT INTO units VALUES(10,14),(20,15);
            CREATE TABLE personnel_job_role_categories(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,parent_id INTEGER,name TEXT,slug TEXT,sort_order INTEGER,UNIQUE(tenant_id,slug));
            CREATE TABLE personnel_job_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,category_id INTEGER,name TEXT,slug TEXT,description TEXT,sort_order INTEGER,is_system INTEGER,UNIQUE(tenant_id,slug));
            CREATE TABLE personnel_profile_job_roles(id INTEGER PRIMARY KEY AUTOINCREMENT,tenant_id INTEGER,user_id INTEGER,personnel_job_role_id INTEGER,is_primary INTEGER,sort_order INTEGER,role_detail TEXT,UNIQUE(tenant_id,user_id,personnel_job_role_id));
            CREATE TABLE access_control_v2_role_snapshot(snapshot_id INTEGER PRIMARY KEY,role_id INTEGER,tenant_id INTEGER,role_name TEXT,role_slug TEXT,migrated_at TEXT);
            INSERT INTO access_control_v2_role_snapshot VALUES(1,99,14,'CNOS','cnos','2026-10-01'),(2,100,14,'RFL','rfl','2026-10-01'),(3,101,15,'Autre communauté','other','2026-10-01'),(4,102,14,'Gestionnaire','community_owner','2026-10-01');
            CREATE TABLE access_control_v2_user_primary_role_snapshot(user_id INTEGER,role_id INTEGER);INSERT INTO access_control_v2_user_primary_role_snapshot VALUES(1,99),(3,99);
            CREATE TABLE access_control_v2_user_role_snapshot(user_id INTEGER,role_id INTEGER,source_kind TEXT);INSERT INTO access_control_v2_user_role_snapshot VALUES(2,100,'user_roles');
            CREATE TABLE roles(id INTEGER PRIMARY KEY,name TEXT);INSERT INTO roles VALUES(500,'Accès personnalisé');
            CREATE TABLE role_permissions(role_id INTEGER,permission_id INTEGER);INSERT INTO role_permissions VALUES(500,700);
            CREATE TABLE personnel_assignments(id INTEGER PRIMARY KEY,user_id INTEGER,unit_id INTEGER,role_name TEXT,is_primary INTEGER,ended_at TEXT,status TEXT);
            INSERT INTO personnel_assignments VALUES(1,4,10,'Responsable soutien',1,NULL,'active'),(2,4,10,'Ancien poste',0,'2000-01-01','inactive'),(3,4,20,'Unité étrangère',0,NULL,'active');");
    }

    public function testDryRunReadsEvidenceWithoutAnyWriteOrSchemaCreation(): void
    {
        $before=$this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $report=(new PersonnelJobRoleRecoveryService($this->pdo))->preview(14,['CNOS','RFL','INCONNU']);
        self::assertTrue($report['dry_run']);self::assertContains('INCONNU',$report['missing_labels']);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_job_roles')->fetchColumn());
        self::assertSame($before,$this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN));
        $foreign=array_values(array_filter($report['candidates'],static fn(array $c):bool=>$c['user_id']===3));
        self::assertFalse($foreign[0]['can_assign']);self::assertSame('user_not_in_current_tenant',$foreign[0]['skip_reason']);
    }

    public function testApplyRecoversKnownLabelsAndVerifiedHoldersWithoutPrivilegesAndIsIdempotent(): void
    {
        $service=new PersonnelJobRoleRecoveryService($this->pdo);
        $first=$service->apply(14,['CNOS','RFL'],12);
        self::assertSame(2,$first['restored_jobs']);self::assertSame(2,$first['restored_assignments']);
        self::assertSame([1,2],array_map('intval',$this->pdo->query('SELECT user_id FROM personnel_profile_job_roles ORDER BY user_id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame([['role_id'=>500,'permission_id'=>700]],$this->pdo->query('SELECT * FROM role_permissions')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn());
        $audit=(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_job_role_recovery_log')->fetchColumn();
        self::assertGreaterThan(0,$audit);
        $again=$service->apply(14,['CNOS','RFL'],12);
        self::assertSame(0,$again['restored_jobs']);self::assertSame(0,$again['restored_assignments']);
        self::assertSame($audit,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_job_role_recovery_log')->fetchColumn());
    }

    public function testCurrentJobsArePreservedInsteadOfReplacedByHistoricalAssignments(): void
    {
        $this->pdo->exec("INSERT INTO personnel_job_role_categories VALUES(10,14,NULL,'Organisation','organisation',0);INSERT INTO personnel_job_roles VALUES(10,14,10,'Travail actuel','current',NULL,0,0);INSERT INTO personnel_profile_job_roles VALUES(10,14,1,10,1,0,'Conserver');");
        $service=new PersonnelJobRoleRecoveryService($this->pdo);$report=$service->preview(14,['CNOS']);
        $user=array_values(array_filter($report['candidates'],static fn(array $c):bool=>$c['user_id']===1))[0];
        self::assertTrue($user['assignment_conflict']);self::assertFalse($user['can_assign']);
        $service->apply(14,['CNOS']);
        self::assertSame([10],array_map('intval',$this->pdo->query('SELECT personnel_job_role_id FROM personnel_profile_job_roles WHERE user_id=1')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame('Conserver',$this->pdo->query('SELECT role_detail FROM personnel_profile_job_roles WHERE user_id=1')->fetchColumn());
    }

    public function testOnlyCurrentTenantAssignmentsCanSupplyLabels(): void
    {
        $report=(new PersonnelJobRoleRecoveryService($this->pdo))->preview(14);
        $names=array_column($report['candidates'],'label');
        self::assertContains('Responsable soutien',$names);
        self::assertNotContains('Ancien poste',$names);self::assertNotContains('Unité étrangère',$names);self::assertNotContains('Gestionnaire',$names);self::assertNotContains('Autre communauté',$names);
    }

    public function testSeveralProvenJobsCanBeRecoveredForAnInitiallyUnassignedHolder(): void
    {
        $this->pdo->exec("INSERT INTO access_control_v2_user_role_snapshot VALUES(1,100,'user_roles')");
        $service=new PersonnelJobRoleRecoveryService($this->pdo);
        $report=$service->apply(14,['CNOS','RFL']);
        self::assertSame(3,$report['restored_assignments']);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles WHERE user_id=1')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles WHERE user_id=1 AND is_primary=1')->fetchColumn());
    }

    public function testMissingLabelsAreReportedAndNeverInvented(): void
    {
        $service=new PersonnelJobRoleRecoveryService($this->pdo);
        $report=$service->apply(14,['POSTE SANS TRACE']);
        self::assertSame(['POSTE SANS TRACE'],$report['missing_labels']);
        self::assertSame(0,$report['restored_jobs']);self::assertSame(0,$report['restored_assignments']);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM personnel_job_roles')->fetchColumn());
    }

}
