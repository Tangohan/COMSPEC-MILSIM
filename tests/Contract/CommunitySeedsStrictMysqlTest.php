<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Authorization\TenantPermissionCatalog;
use App\Core\Database;
use App\Services\Community\TenantSeedHelper;
use App\Services\Community\TenantTypeConfig;
use App\Support\SqlDataTooLong;
use App\Support\UserFacingExceptionMapper;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Seeds d'une nouvelle communauté sur un vrai serveur MySQL ou MariaDB en mode strict, avec les tables de
 * migrations/schema.sql (permissions.name et roles.name en VARCHAR(100)).
 *
 * Le droit atak.toc.phone (libellé de 102 caractères) faisait échouer toute création de communauté en production :
 * « SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'name' ». SQLite ne contrôle pas les
 * longueurs : seule cette exécution le prouve. Ignoré sans base de test ; la CI le lance sur MySQL 8 et MariaDB
 * (COMSPEC_TEST_MYSQL_DSN, COMSPEC_TEST_MYSQL_USER, COMSPEC_TEST_MYSQL_PASS). La base est vidée au début.
 */
final class CommunitySeedsStrictMysqlTest extends TestCase
{
    private const TABLES = ['tenants', 'permissions', 'roles', 'role_permissions', 'document_categories', 'equipment_classes', 'personnel_admin_panels'];

    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = (string) getenv('COMSPEC_TEST_MYSQL_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Base MySQL de test non configurée (COMSPEC_TEST_MYSQL_DSN).');
        }
        $this->pdo = new PDO($dsn, (string) getenv('COMSPEC_TEST_MYSQL_USER'), (string) getenv('COMSPEC_TEST_MYSQL_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec(Database::sessionInitSql());
        $this->pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $this->pdo->exec('DROP TABLE `' . $table . '`');
        }
        $schema = (string) file_get_contents(dirname(__DIR__, 2) . '/migrations/schema.sql');
        foreach (self::TABLES as $table) {
            if (preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` \(.*?\) ENGINE=InnoDB[^;]*;/s', $schema, $m) !== 1) {
                self::fail('Table ' . $table . ' introuvable dans migrations/schema.sql.');
            }
            $this->pdo->exec($m[0]);
        }
        // Colonnes ajoutées par migrations/20260420103000_access_control_rbac_abac.sql, présentes en production.
        $this->pdo->exec('ALTER TABLE `permissions` ADD COLUMN `code` varchar(190) NULL AFTER `tenant_id`, ADD COLUMN `label` varchar(190) NULL AFTER `name`');
    }

    private function newTenant(string $name, string $slug): int
    {
        $this->pdo->prepare('INSERT INTO tenants (name, slug, created_at, updated_at) VALUES (?, ?, NOW(), NOW())')->execute([$name, $slug]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testFullCommunitySeedsFitTheStrictSchema(): void
    {
        $tenantId = $this->newTenant('Jsoc And Taskforce 121', 'jsoc-and-taskforce-121');
        $this->pdo->beginTransaction();
        // Même ordre que TenantBootstrapService::createCommunity (profil « Complet »).
        TenantSeedHelper::ensureAccessProfilesForTenant($this->pdo, $tenantId);
        TenantSeedHelper::seedBaseRoles($this->pdo, $tenantId);
        TenantSeedHelper::seedDocumentsEquipment($this->pdo, $tenantId);
        TenantSeedHelper::ensureSystemAdminPermissions($this->pdo, $tenantId);
        TenantSeedHelper::ensureTenantPermissionCatalog($this->pdo, $tenantId);
        TenantSeedHelper::applyWizardCommunityRoles($this->pdo, $tenantId, 'quick');
        TenantSeedHelper::ensurePersonnelPanelsAndMatricule($this->pdo, $tenantId);
        TenantSeedHelper::alignAccessProfilesForType($this->pdo, $tenantId, TenantTypeConfig::TYPE_FULL);
        // Deuxième passage : chemin UPDATE du catalogue (communautés existantes, migrations).
        TenantSeedHelper::ensureTenantPermissionCatalog($this->pdo, $tenantId);
        $this->pdo->commit();

        $st = $this->pdo->prepare('SELECT name FROM permissions WHERE tenant_id = ? AND slug = ?');
        $st->execute([$tenantId, 'atak.toc.phone']);
        self::assertSame('Agir sur les téléphones en jeu depuis le poste TOC web', $st->fetchColumn());

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM permissions WHERE tenant_id = ?');
        $count->execute([$tenantId]);
        self::assertGreaterThanOrEqual(count(TenantPermissionCatalog::definitions()), (int) $count->fetchColumn());
    }

    public function testRestrictedProfilesSeedTooWithoutTruncationErrors(): void
    {
        foreach (array_keys(TenantTypeConfig::basePermissionsByType()) as $i => $type) {
            $tenantId = $this->newTenant('Profil ' . $type, 'profil-' . $i);
            TenantSeedHelper::ensureAccessProfilesForTenant($this->pdo, $tenantId);
            TenantSeedHelper::seedBaseRoles($this->pdo, $tenantId);
            TenantSeedHelper::alignAccessProfilesForType($this->pdo, $tenantId, (string) $type);
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM permissions WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            self::assertGreaterThan(0, (int) $st->fetchColumn(), (string) $type);
        }
    }

    public function testRealError1406IsMappedToTableAndColumn(): void
    {
        $tenantId = $this->newTenant('Erreur', 'erreur');
        try {
            $st = $this->pdo->prepare('INSERT INTO permissions (tenant_id, name, slug, module) VALUES (?, ?, ?, ?)');
            $st->execute([$tenantId, str_repeat('x', 101), 'test.too_long', 'test']);
            self::fail('MySQL strict aurait dû refuser 101 caractères dans permissions.name.');
        } catch (PDOException $e) {
            self::assertTrue(SqlDataTooLong::matches($e), $e->getMessage());
            self::assertSame('permissions.name', SqlDataTooLong::reference($e));
            self::assertStringContainsString('(permissions.name)', UserFacingExceptionMapper::communityCreationMessage($e));
        }
        self::assertSame(100, mb_strlen(TenantSeedHelper::permissionName(str_repeat('é', 150)), 'UTF-8'));
    }
}
