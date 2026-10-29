<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Personnel\UnitJobRoleSyncService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelJobRoleConsolidationTest extends TestCase
{
    public function testReplayAddsMissingLegacyJobsAndNeverDropsCurrentOrHistoricalWork(): void
    {
        $dsn=getenv('RH_PRESERVATION_TEST_DSN');
        if (!$dsn) self::markTestSkipped('Set RH_PRESERVATION_TEST_DSN to an isolated rh_preservation_test database.');
        $pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        self::assertSame('rh_preservation_test',$pdo->query('SELECT DATABASE()')->fetchColumn());
        foreach (['personnel_job_role_legacy_imports','personnel_profile_job_roles','personnel_job_roles','personnel_job_role_categories','personnel_profiles','users'] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
        $pdo->exec("CREATE TABLE users(id INT PRIMARY KEY,tenant_id INT);INSERT INTO users VALUES(1,14),(2,14);
            CREATE TABLE personnel_profiles(user_id INT PRIMARY KEY,primary_role VARCHAR(120),secondary_role VARCHAR(120),personnel_job_role_id INT,role_sub_label VARCHAR(150));
            INSERT INTO personnel_profiles VALUES(1,'CNOS','RFL',NULL,NULL),(2,'CNOS','Responsable soutien',5,'Détail conservé');
            CREATE TABLE personnel_job_role_categories(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT,parent_id INT,name VARCHAR(120),slug VARCHAR(80),sort_order INT,UNIQUE(tenant_id,slug)) DEFAULT CHARSET=utf8mb4;
            INSERT INTO personnel_job_role_categories VALUES(1,14,NULL,'Organisation','organisation',0);
            CREATE TABLE personnel_job_roles(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT,category_id INT,name VARCHAR(120),slug VARCHAR(80),description VARCHAR(500),sort_order INT,is_system INT,UNIQUE(tenant_id,slug)) DEFAULT CHARSET=utf8mb4;
            INSERT INTO personnel_job_roles VALUES(5,14,1,'CNOS','cnos',NULL,0,0);
            CREATE TABLE personnel_profile_job_roles(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT,user_id INT,personnel_job_role_id INT,is_primary INT,sort_order INT,role_detail VARCHAR(150),UNIQUE(tenant_id,user_id,personnel_job_role_id)) DEFAULT CHARSET=utf8mb4;
            INSERT INTO personnel_profile_job_roles VALUES(1,14,1,5,1,0,'Saisi par organisation');");
        $run=require dirname(__DIR__,2).'/bootstrap/personnel_role_consolidation_migration.php';
        ob_start();$run($pdo);$run($pdo);$output=ob_get_clean();
        self::assertStringNotContainsString('[ATTENTION]',$output);
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM personnel_job_roles')->fetchColumn());
        self::assertSame(4,(int)$pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles')->fetchColumn());
        self::assertSame('Saisi par organisation',$pdo->query('SELECT role_detail FROM personnel_profile_job_roles WHERE user_id=1 AND personnel_job_role_id=5')->fetchColumn());
        self::assertSame('Détail conservé',$pdo->query('SELECT role_detail FROM personnel_profile_job_roles WHERE user_id=2 AND personnel_job_role_id=5')->fetchColumn());
        self::assertSame('RFL',$pdo->query('SELECT secondary_role FROM personnel_profiles WHERE user_id=1')->fetchColumn());
        // A later explicit HR edit stays in place when the same historical payload is replayed.
        $pdo->exec("DELETE FROM personnel_profile_job_roles WHERE user_id=1 AND personnel_job_role_id IN (SELECT id FROM personnel_job_roles WHERE name='RFL')");
        ob_start();$run($pdo);ob_end_clean();
        self::assertSame(3,(int)$pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles')->fetchColumn());
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM personnel_job_role_legacy_imports')->fetchColumn());
        self::assertSame(4,(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='personnel_profiles' AND COLUMN_NAME IN ('primary_role','secondary_role','personnel_job_role_id','role_sub_label')")->fetchColumn());
    }

    public function testUnitSyncKeepsTheJobLabelChosenByTheOrganisation(): void
    {
        $dsn=getenv('RH_PRESERVATION_TEST_DSN');
        if (!$dsn) self::markTestSkipped('Set RH_PRESERVATION_TEST_DSN to an isolated rh_preservation_test database.');
        $pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        self::assertSame('rh_preservation_test',$pdo->query('SELECT DATABASE()')->fetchColumn());
        $pdo->exec("DROP TABLE IF EXISTS personnel_job_roles;DROP TABLE IF EXISTS personnel_job_role_categories;
            CREATE TABLE personnel_job_role_categories(id INT PRIMARY KEY,tenant_id INT,slug VARCHAR(80));INSERT INTO personnel_job_role_categories VALUES(10,14,'organisation');
            CREATE TABLE personnel_job_roles(id INT PRIMARY KEY,tenant_id INT,source_unit_id INT,name VARCHAR(120),slug VARCHAR(80));INSERT INTO personnel_job_roles VALUES(10,14,7,'CNOS personnalisé','unit-7');");
        $repo=$this->createMock(PersonnelJobRoleRepository::class);
        $repo->method('tablesExist')->willReturn(true);
        $repo->expects(self::never())->method('createRole');
        UnitJobRoleSyncService::resetColumnCache();
        try {
            $service=new UnitJobRoleSyncService($pdo,$repo);
            self::assertSame(10,$service->ensureForUnit(14,7,'Nom ORBAT modifié'));
            self::assertSame('CNOS personnalisé',$pdo->query('SELECT name FROM personnel_job_roles WHERE id=10')->fetchColumn());
        } finally {
            UnitJobRoleSyncService::resetColumnCache();
        }
    }

}
