<?php

declare(strict_types=1);

namespace Tests\Contract;

use PDO;
use PHPUnit\Framework\TestCase;

final class PersonnelAccessPreservationTest extends TestCase
{
    public function testMigrationPreservesCustomRolesAndGrantsWithoutRegrantingRemovedAccessOnReplay(): void
    {
        $dsn=getenv('RH_PRESERVATION_TEST_DSN');
        if (!$dsn) self::markTestSkipped('Set RH_PRESERVATION_TEST_DSN to an isolated rh_preservation_test database.');
        $pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        self::assertSame('rh_preservation_test',$pdo->query('SELECT DATABASE()')->fetchColumn());
        foreach (['access_control_v2_role_snapshot','access_control_v2_site_assignment_snapshot','access_control_v2_snapshot_state','access_control_v2_user_primary_role_snapshot','access_control_v2_user_role_snapshot','access_control_v2_tenant_user_role_snapshot','access_control_v2_user_access_roles','access_control_sensitive_audit','site_role_assignments','tenant_user_roles','user_roles','role_permissions','permissions','roles','users','tenants'] as $table) $pdo->exec('DROP TABLE IF EXISTS '.$table);
        $pdo->exec("CREATE TABLE tenants(id INT PRIMARY KEY);INSERT INTO tenants VALUES(14),(15);
            CREATE TABLE roles(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,tenant_id INT,name VARCHAR(190),slug VARCHAR(190),description VARCHAR(500),is_system INT,is_locked INT,role_layer VARCHAR(30),is_visual_only INT DEFAULT 0,created_at DATETIME,UNIQUE(tenant_id,slug));
            INSERT INTO roles(id,tenant_id,name,slug,created_at) VALUES(99,14,'CNOS','cnos',NOW()),(100,15,'Autre communauté','custom-other',NOW());
            CREATE TABLE users(id INT PRIMARY KEY,tenant_id INT,role_id INT);INSERT INTO users VALUES(1,14,99),(2,15,100);
            CREATE TABLE permissions(id INT PRIMARY KEY,tenant_id INT,slug VARCHAR(190));INSERT INTO permissions VALUES(1,14,'personnel.assignments.manage'),(2,14,'platform.secrets'),(3,15,'custom.right');
            CREATE TABLE role_permissions(role_id INT,permission_id INT,PRIMARY KEY(role_id,permission_id));INSERT INTO role_permissions VALUES(99,1),(99,2),(100,3);
            CREATE TABLE user_roles(user_id INT,role_id INT,PRIMARY KEY(user_id,role_id));INSERT INTO user_roles VALUES(1,99),(2,100);
            CREATE TABLE tenant_user_roles(tenant_id INT,user_id INT,role_id INT,org_unit_id INT NULL,co_unit_id INT DEFAULT 0,created_at DATETIME,UNIQUE(tenant_id,user_id,role_id,co_unit_id));INSERT INTO tenant_user_roles VALUES(14,1,99,NULL,0,NOW()),(15,2,100,NULL,0,NOW());
            CREATE TABLE site_role_assignments(id INT PRIMARY KEY,email_normalized VARCHAR(255),role_id INT,assigned_by_user_id INT,created_at DATETIME,revoked_at DATETIME NULL);");
        require_once dirname(__DIR__,2).'/bootstrap/function_based_access_v2_migration.php';
        ob_start();try { \run_function_based_access_v2_migration($pdo); } finally { ob_end_clean(); }
        self::assertSame(99,(int)$pdo->query('SELECT role_id FROM users WHERE id=1')->fetchColumn());
        self::assertSame([1],array_map('intval',$pdo->query('SELECT permission_id FROM role_permissions WHERE role_id=99')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame([3],array_map('intval',$pdo->query('SELECT permission_id FROM role_permissions WHERE role_id=100')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM user_roles WHERE user_id=1 AND role_id=99')->fetchColumn());
        self::assertSame(1,(int)$pdo->query('SELECT COUNT(*) FROM tenant_user_roles WHERE tenant_id=14 AND user_id=1 AND role_id=99')->fetchColumn());
        $snapshot=(int)$pdo->query('SELECT COUNT(*) FROM access_control_v2_role_snapshot')->fetchColumn();
        // An administrator intentionally removes the seeded compatibility role after the first import.
        $pdo->exec("DELETE ur FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=1 AND r.slug='member';DELETE tur FROM tenant_user_roles tur JOIN roles r ON r.id=tur.role_id WHERE tur.user_id=1 AND r.slug='member';");
        ob_start();try { \run_function_based_access_v2_migration($pdo); } finally { ob_end_clean(); }
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=1 AND r.slug='member'")->fetchColumn());
        self::assertSame(0,(int)$pdo->query("SELECT COUNT(*) FROM tenant_user_roles tur JOIN roles r ON r.id=tur.role_id WHERE tur.user_id=1 AND r.slug='member'")->fetchColumn());
        self::assertSame($snapshot,(int)$pdo->query('SELECT COUNT(*) FROM access_control_v2_role_snapshot')->fetchColumn());
        self::assertSame(1,(int)$pdo->query("SELECT COUNT(*) FROM roles WHERE tenant_id=14 AND slug='cnos'")->fetchColumn());
    }
}
