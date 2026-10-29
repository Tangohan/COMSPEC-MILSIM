<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\PersonnelJobRoleRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelJobRoleDeletionTest extends TestCase
{
    private PDO $pdo;
    private PersonnelJobRoleRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY)');
        $this->pdo->exec('CREATE TABLE personnel_job_role_categories (id INTEGER PRIMARY KEY, tenant_id INT, parent_id INT REFERENCES personnel_job_role_categories(id) ON DELETE CASCADE)');
        $this->pdo->exec('CREATE TABLE personnel_job_roles (id INTEGER PRIMARY KEY, tenant_id INT, category_id INT REFERENCES personnel_job_role_categories(id) ON DELETE CASCADE, is_system INT DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE personnel_profile_job_roles (tenant_id INT, user_id INT, personnel_job_role_id INT REFERENCES personnel_job_roles(id) ON DELETE CASCADE)');
        $this->pdo->exec('CREATE TABLE personnel_profiles (tenant_id INT, user_id INT, personnel_job_role_id INT)');
        $this->pdo->exec('CREATE TABLE orbat_billets (tenant_id INT, job_role_id INT)');
        $this->pdo->exec('CREATE TABLE personnel_temporary_assignments (tenant_id INT, job_role_id INT)');
        $this->pdo->exec('CREATE TABLE recruitment_openings (tenant_id INT, personnel_job_role_id INT REFERENCES personnel_job_roles(id) ON DELETE SET NULL)');
        $this->pdo->exec('CREATE TABLE personnel_job_role_structure (tenant_id INT, job_role_id INT, parent_job_role_id INT, code TEXT)');
        $this->pdo->exec('CREATE TABLE personnel_job_role_permissions (personnel_job_role_id INT REFERENCES personnel_job_roles(id) ON DELETE CASCADE, permission_id INT)');
        $this->pdo->exec('INSERT INTO tenants VALUES (14), (15)');
        $this->pdo->exec('INSERT INTO personnel_job_role_categories VALUES (1,14,NULL), (2,14,NULL), (3,15,NULL)');
        $this->pdo->exec('INSERT INTO personnel_job_roles VALUES (11,14,1,0), (12,14,1,1), (21,15,3,0)');
        $this->repository = (new \ReflectionClass(PersonnelJobRoleRepository::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(PersonnelJobRoleRepository::class, 'pdo'))->setValue($this->repository, $this->pdo);
    }

    public function testAssignedJobCannotCascadeThroughAHolder(): void
    {
        $this->pdo->exec('INSERT INTO personnel_profile_job_roles VALUES (14,55,11)');
        $this->pdo->exec("INSERT INTO personnel_job_role_structure VALUES (14,11,NULL,'CNOS')");
        $this->pdo->exec('INSERT INTO personnel_job_role_permissions VALUES (11,901)');

        self::assertFalse($this->repository->deleteRole(11,14));
        self::assertSame(1, $this->rowCount('personnel_profile_job_roles'));
        self::assertSame(1, $this->rowCount('personnel_job_role_structure'));
        self::assertSame(1, $this->rowCount('personnel_job_role_permissions'));
        self::assertSame(3, $this->rowCount('personnel_job_roles'));
        self::assertFalse($this->pdo->inTransaction());
    }

    public function testEachStructuralReferencePreventsDeletionWithoutRemovingItsData(): void
    {
        $references = [
            ['personnel_profiles', 'tenant_id,user_id,personnel_job_role_id', '14,55,11'],
            ['orbat_billets', 'tenant_id,job_role_id', '14,11'],
            ['personnel_temporary_assignments', 'tenant_id,job_role_id', '14,11'],
            ['recruitment_openings', 'tenant_id,personnel_job_role_id', '14,11'],
            ['personnel_job_role_structure', 'tenant_id,job_role_id,parent_job_role_id', '14,12,11'],
        ];
        foreach ($references as [$table,$columns,$values]) {
            $this->pdo->exec('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $values . ')');
            self::assertFalse($this->repository->deleteRole(11,14), $table);
            self::assertSame(1, $this->rowCount($table), $table);
            self::assertSame(3, $this->rowCount('personnel_job_roles'));
            $this->pdo->exec('DELETE FROM ' . $table);
        }
    }

    public function testWrongTenantSystemAndMalformedCrossTenantReferenceAreProtected(): void
    {
        self::assertFalse($this->repository->deleteRole(11,15));
        self::assertFalse($this->repository->deleteRole(12,14));
        self::assertFalse($this->repository->deleteRole(99,14));
        self::assertFalse($this->repository->deleteRole(11,99));
        self::assertFalse($this->repository->deleteRole(0,14));
        $this->pdo->exec('INSERT INTO personnel_profile_job_roles VALUES (15,75,11)');
        self::assertFalse($this->repository->deleteRole(11,14));
        self::assertSame(1, $this->rowCount('personnel_profile_job_roles'));
        self::assertSame(3, $this->rowCount('personnel_job_roles'));
    }

    public function testExplicitUnreferencedDeleteCleansOnlyOwnMetadata(): void
    {
        $this->pdo->exec("INSERT INTO personnel_job_role_structure VALUES (14,11,NULL,'CNOS'), (15,21,NULL,'RFL')");
        $this->pdo->exec('INSERT INTO personnel_job_role_permissions VALUES (11,901), (21,902)');
        self::assertTrue($this->repository->deleteRole(11,14));
        self::assertSame(2, $this->rowCount('personnel_job_roles'));
        self::assertSame([['tenant_id'=>15,'job_role_id'=>21,'parent_job_role_id'=>null,'code'=>'RFL']], $this->pdo->query('SELECT * FROM personnel_job_role_structure')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame([['personnel_job_role_id'=>21,'permission_id'=>902]], $this->pdo->query('SELECT * FROM personnel_job_role_permissions')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame(3, $this->rowCount('personnel_job_role_categories'));
    }

    public function testOuterTransactionRemainsOwnedByCallerAndCanUndoMetadataCleanup(): void
    {
        $this->pdo->exec("INSERT INTO personnel_job_role_structure VALUES (14,11,NULL,'CNOS')");
        $this->pdo->beginTransaction();
        self::assertTrue($this->repository->deleteRole(11,14));
        self::assertTrue($this->pdo->inTransaction());
        self::assertSame(0, $this->rowCount('personnel_job_role_structure'));
        $this->pdo->rollBack();
        self::assertSame(3, $this->rowCount('personnel_job_roles'));
        self::assertSame(1, $this->rowCount('personnel_job_role_structure'));

        $this->pdo->beginTransaction();
        self::assertFalse($this->repository->deleteRole(12,14));
        self::assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
    }

    public function testCleanupFailureRollsBackRoleAndPermissionDeletion(): void
    {
        $this->pdo->exec("INSERT INTO personnel_job_role_structure VALUES (14,11,NULL,'CNOS')");
        $this->pdo->exec('INSERT INTO personnel_job_role_permissions VALUES (11,901)');
        $this->pdo->exec("CREATE TRIGGER reject_metadata_delete BEFORE DELETE ON personnel_job_role_structure BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        try {
            $this->repository->deleteRole(11,14);
            self::fail('Fixture failure should propagate to the caller.');
        } catch (\PDOException) {
            self::assertFalse($this->pdo->inTransaction());
            self::assertSame(3, $this->rowCount('personnel_job_roles'));
            self::assertSame(1, $this->rowCount('personnel_job_role_structure'));
            self::assertSame(1, $this->rowCount('personnel_job_role_permissions'));
        }
    }

    public function testLegacyInstallationWithoutOptionalTablesStillSupportsSafeDeletion(): void
    {
        foreach (['personnel_job_role_structure','personnel_temporary_assignments','orbat_billets','recruitment_openings','personnel_profiles','personnel_profile_job_roles'] as $table) {
            $this->pdo->exec('DROP TABLE ' . $table);
        }
        self::assertTrue($this->repository->deleteRole(11,14));
        self::assertSame(2, $this->rowCount('personnel_job_roles'));
    }

    public function testOnlyEmptyTenantOwnedCategoryCanBeDeleted(): void
    {
        self::assertFalse($this->repository->deleteCategory(1,14));
        self::assertFalse($this->repository->deleteCategory(3,14));
        self::assertFalse($this->repository->deleteCategory(99,14));
        $this->pdo->exec('INSERT INTO personnel_job_role_categories VALUES (4,14,2)');
        self::assertFalse($this->repository->deleteCategory(2,14));
        self::assertSame(4, $this->rowCount('personnel_job_role_categories'));
        self::assertSame(3, $this->rowCount('personnel_job_roles'));
        self::assertTrue($this->repository->deleteCategory(4,14));
        self::assertTrue($this->repository->deleteCategory(2,14));
        self::assertSame(2, $this->rowCount('personnel_job_role_categories'));
        self::assertSame(3, $this->rowCount('personnel_job_roles'));
    }

    private function rowCount(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
}
