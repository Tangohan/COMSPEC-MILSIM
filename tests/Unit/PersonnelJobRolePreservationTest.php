<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelJobRoleRepository;
use App\Services\Personnel\UnitJobRoleSyncService;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelJobRolePreservationTest extends TestCase
{
    public function testAutomaticCleanupNeverDeletesUnassignedCustomJobsCategoriesOrPermissions(): void
    {
        $pdo=new PDO('sqlite::memory:');
        $pdo->exec("CREATE TABLE personnel_job_roles(id INTEGER,tenant_id INTEGER,name TEXT,slug TEXT,is_system INTEGER);INSERT INTO personnel_job_roles VALUES(1,14,'CNOS','cnos',0),(2,14,'RFL','unit-99',1);CREATE TABLE personnel_job_role_categories(id INTEGER,name TEXT);INSERT INTO personnel_job_role_categories VALUES(1,'Forces spéciales');CREATE TABLE personnel_job_role_permissions(role_id INTEGER,permission_id INTEGER);INSERT INTO personnel_job_role_permissions VALUES(1,33);");
        $before=$pdo->query('SELECT * FROM personnel_job_roles')->fetchAll(PDO::FETCH_ASSOC);
        $repo=$this->createMock(PersonnelJobRoleRepository::class);
        $service=new UnitJobRoleSyncService($pdo,$repo);
        self::assertSame(0,$service->purgeUnusedCatalogJobs(14));
        self::assertSame(0,$service->purgeUnusedUnitDerivedJobs(14));
        self::assertSame(0,$service->purgeUnusedAutoCreatedJobs(14));
        self::assertSame($before,$pdo->query('SELECT * FROM personnel_job_roles')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM personnel_job_role_categories')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM personnel_job_role_permissions')->fetchColumn());
        foreach (['unused_recreated_job_roles_purge_v1','unused_military_catalog_jobs_purge_v2','community_access_roles_purge_v1'] as $migration) {
            $run=require dirname(__DIR__,2).'/bootstrap/'.$migration.'_migration.php';
            ob_start();$run($pdo);ob_end_clean();
        }
        self::assertSame($before,$pdo->query('SELECT * FROM personnel_job_roles')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM personnel_job_role_permissions')->fetchColumn());
    }
}
