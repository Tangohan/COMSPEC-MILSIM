<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Services\Personnel\PersonnelJobRoleStructureService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelJobRoleStructureMariaDbTest extends TestCase
{
    public function testReplayAndCurrentReadsPreserveWorkAndRejectStaleHierarchyReferences(): void
    {
        $dsn = getenv('RH_STRUCTURE_TEST_DSN');
        if (!$dsn) self::markTestSkipped('Set RH_STRUCTURE_TEST_DSN to an isolated rh_structure_test database.');
        $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        self::assertSame('rh_structure_test', $pdo->query('SELECT DATABASE()')->fetchColumn());
        foreach (['personnel_job_role_structure', 'personnel_profile_job_roles', 'personnel_job_roles', 'units', 'tenants'] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
        $pdo->exec("CREATE TABLE tenants (id INT PRIMARY KEY) ENGINE=InnoDB;
            INSERT INTO tenants VALUES (14),(15);
            CREATE TABLE units (id INT PRIMARY KEY, tenant_id INT, name VARCHAR(120)) ENGINE=InnoDB;
            INSERT INTO units VALUES (100,14,'État-major');
            CREATE TABLE personnel_job_roles (id INT PRIMARY KEY, tenant_id INT, category_id INT, name VARCHAR(120), source_unit_id INT) ENGINE=InnoDB;
            INSERT INTO personnel_job_roles VALUES (11,14,1,'CNOS',100),(12,14,1,'RFL',NULL),(21,15,2,'Autre communauté',NULL);
            CREATE TABLE personnel_profile_job_roles (tenant_id INT,user_id INT,personnel_job_role_id INT) ENGINE=InnoDB;
            INSERT INTO personnel_profile_job_roles VALUES (14,55,11);");
        $migration = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/20261029223000_personnel_job_role_structure.sql');
        $pdo->exec($migration);
        $service = new PersonnelJobRoleStructureService($pdo);
        $service->save(14, 11, ['parent_job_role_id'=>12, 'linked_unit_id'=>100, 'code'=>'CNOS', 'kind'=>'function']);
        $before = $service->listMetadata(14);
        $pdo->exec($migration);
        self::assertSame($before, $service->listMetadata(14));
        self::assertSame(3, (int) $pdo->query('SELECT COUNT(*) FROM personnel_job_roles')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles')->fetchColumn());
        self::assertSame(100, (int) $pdo->query('SELECT source_unit_id FROM personnel_job_roles WHERE id=11')->fetchColumn());

        $second = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $second->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $other = new PersonnelJobRoleStructureService($second);
        self::assertTrue($other->schemaReady());
        $second->beginTransaction();
        // Establish a snapshot before the first connection changes the hierarchy.
        $second->query('SELECT COUNT(*) FROM personnel_job_role_structure')->fetchColumn();
        $service->save(14, 11, ['kind'=>'function']);
        $service->save(14, 12, ['parent_job_role_id'=>11]);
        try {
            $other->save(14, 11, ['parent_job_role_id'=>12]);
            self::fail('A stale snapshot allowed an opposite hierarchy cycle.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('boucle', $exception->getMessage());
            self::assertTrue($second->inTransaction());
        } finally {
            $second->rollBack();
        }

        $second->beginTransaction();
        $second->query('SELECT COUNT(*) FROM personnel_job_roles')->fetchColumn();
        $pdo->exec('DELETE FROM personnel_job_role_structure WHERE tenant_id=14 AND job_role_id=12');
        $pdo->exec('DELETE FROM personnel_job_roles WHERE id=12');
        try {
            $other->save(14, 11, ['parent_job_role_id'=>12]);
            self::fail('A parent deleted since the caller snapshot was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('parent', $exception->getMessage());
        } finally {
            $second->rollBack();
        }
        self::assertNull($service->listMetadata(14)[11]['parent_job_role_id']);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM personnel_profile_job_roles')->fetchColumn());
    }
}
