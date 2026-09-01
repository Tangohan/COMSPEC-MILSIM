<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Repositories\OrganizationCatalogRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RoleRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Services\Admin\TenantRolePermissionPresetService;
use App\Services\Audit\AuditService;
use App\Services\ConfigurationUpdate\ConfigurationUpdateService;
use App\Services\OrganizationCatalog\OrganizationCatalogService;
use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use PDO;
use PHPUnit\Framework\TestCase;

final class OrganizationCatalogServiceTest extends TestCase
{
    public function testTwoTenantsReceiveDistinctCopiedIdsFromTheSameOfficialKit(): void
    {
        $kit = OrganizationKitDefinitions::infantryLight();
        $itemRow = [
            'id' => 9,
            'code' => OrganizationKitDefinitions::INFANTRY_LIGHT,
            'title' => $kit['title'],
            'summary' => $kit['summary'],
            'version' => 1,
            'visibility' => 'official',
            'owner_tenant_id' => null,
            'definition_json' => json_encode($kit),
        ];

        $catalog = $this->createMock(OrganizationCatalogRepository::class);
        $catalog->method('tablesExist')->willReturn(true);
        $catalog->method('upsertOfficial')->willReturn(9);
        $catalog->method('findByCode')->willReturn($itemRow);
        $catalog->expects($this->exactly(2))->method('recordInstall');

        $unitSeq = 1000;
        $unitIdsByTenant = [11 => [], 22 => []];
        $units = $this->createMock(UnitRepository::class);
        $units->method('findBySlugForTenant')->willReturn(null);
        $units->method('allForTenant')->willReturn([]);
        $units->method('create')->willReturnCallback(
            static function (int $tenantId, array $data) use (&$unitSeq, &$unitIdsByTenant): array {
                $id = ++$unitSeq;
                $unitIdsByTenant[$tenantId][] = $id;

                return ['id' => $id, 'tenant_id' => $tenantId, 'name' => $data['name'] ?? ''];
            }
        );

        $catSeq = 2000;
        $roleSeq = 3000;
        $jobRoles = $this->createMock(PersonnelJobRoleRepository::class);
        $jobRoles->method('tablesExist')->willReturn(true);
        $jobRoles->method('findCategoryIdBySlug')->willReturn(null);
        $jobRoles->method('findRoleIdBySlug')->willReturn(null);
        $jobRoles->method('createCategory')->willReturnCallback(
            static function () use (&$catSeq): int {
                return ++$catSeq;
            }
        );
        $jobRoles->method('createRole')->willReturnCallback(
            static function () use (&$roleSeq): int {
                return ++$roleSeq;
            }
        );

        $orgRoleSeq = 4000;
        $orgRoleIds = [11 => [], 22 => []];
        $roles = $this->createMock(RoleRepository::class);
        $roles->method('getIdBySlug')->willReturn(null);
        $roles->method('forTenantOrganization')->willReturn([]);
        $roles->method('createOrganizationRole')->willReturnCallback(
            static function (int $tenantId) use (&$orgRoleSeq, &$orgRoleIds): int {
                $id = ++$orgRoleSeq;
                $orgRoleIds[$tenantId][] = $id;

                return $id;
            }
        );

        $permissions = $this->createMock(PermissionRepository::class);
        $permissions->method('setPermissionsForRole');

        $presets = $this->createMock(TenantRolePermissionPresetService::class);
        $presets->method('getPermissionIdsForPreset')->willReturn([]);

        $tenants = $this->createMock(TenantRepository::class);
        $tenants->method('getSettings')->willReturn([]);
        $tenants->method('mergeSettings');

        $audit = $this->createMock(AuditService::class);
        $config = $this->createMock(ConfigurationUpdateService::class);
        $config->expects($this->exactly(2))->method('markCompleted');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->expects($this->exactly(2))->method('beginTransaction');
        $pdo->expects($this->exactly(2))->method('commit');

        $svc = new OrganizationCatalogService(
            $catalog,
            $units,
            $jobRoles,
            $roles,
            $permissions,
            $presets,
            $tenants,
            $audit,
            $config,
            $pdo
        );

        $a = $svc->apply(11, OrganizationKitDefinitions::INFANTRY_LIGHT, [], 1);
        $b = $svc->apply(22, OrganizationKitDefinitions::INFANTRY_LIGHT, [], 2);

        self::assertTrue($a['ok']);
        self::assertTrue($b['ok']);
        self::assertSame(count($kit['units']), count($unitIdsByTenant[11]));
        self::assertSame(count($kit['units']), count($unitIdsByTenant[22]));
        self::assertSame([], array_intersect($unitIdsByTenant[11], $unitIdsByTenant[22]));
        self::assertSame([], array_intersect($orgRoleIds[11], $orgRoleIds[22]));
        self::assertStringContainsString('unités ajoutées', (string) ($a['report']['summary'] ?? ''));
    }

    public function testReapplyKeepsExistingUnits(): void
    {
        $kit = OrganizationKitDefinitions::gamingCommunity();
        $itemRow = [
            'id' => 3,
            'code' => OrganizationKitDefinitions::GAMING_COMMUNITY,
            'title' => $kit['title'],
            'summary' => $kit['summary'],
            'version' => 1,
            'visibility' => 'official',
            'owner_tenant_id' => null,
            'definition_json' => json_encode($kit),
        ];
        $existingUnits = [];
        foreach ($kit['units'] as $u) {
            $existingUnits[] = [
                'id' => 50 + count($existingUnits),
                'name' => $u['name'],
                'slug' => $u['slug'],
            ];
        }

        $catalog = $this->createMock(OrganizationCatalogRepository::class);
        $catalog->method('tablesExist')->willReturn(true);
        $catalog->method('upsertOfficial')->willReturn(3);
        $catalog->method('findByCode')->willReturn($itemRow);

        $units = $this->createMock(UnitRepository::class);
        $units->method('findBySlugForTenant')->willReturnCallback(
            static function (int $tenantId, string $slug) use ($existingUnits): ?array {
                foreach ($existingUnits as $row) {
                    if ((string) $row['slug'] === $slug) {
                        return $row;
                    }
                }

                return null;
            }
        );
        $units->method('allForTenant')->willReturn($existingUnits);
        $units->expects($this->never())->method('create');

        $jobRoles = $this->createMock(PersonnelJobRoleRepository::class);
        $jobRoles->method('tablesExist')->willReturn(true);
        $jobRoles->method('findCategoryIdBySlug')->willReturn(1);
        $jobRoles->method('findRoleIdBySlug')->willReturn(1);

        $roles = $this->createMock(RoleRepository::class);
        $roles->method('getIdBySlug')->willReturn(8);
        $roles->method('forTenantOrganization')->willReturn([]);

        $svc = new OrganizationCatalogService(
            $catalog,
            $units,
            $jobRoles,
            $roles,
            $this->createMock(PermissionRepository::class),
            $this->createMock(TenantRolePermissionPresetService::class),
            $this->createMock(TenantRepository::class),
            $this->createMock(AuditService::class),
            null,
            $this->createMock(PDO::class)
        );

        $preview = $svc->preview(7, OrganizationKitDefinitions::GAMING_COMMUNITY, ['orbat' => true]);
        self::assertTrue($preview['ok']);
        self::assertSame(0, (int) ($preview['report']['units_added'] ?? -1));
        self::assertSame(count($kit['units']), (int) ($preview['report']['units_kept'] ?? 0));
        self::assertStringContainsString('déjà présentes', (string) ($preview['report']['summary'] ?? ''));
        self::assertNotEmpty($preview['report']['units_kept_names'] ?? []);
        self::assertNotEmpty($preview['item']['unit_outline'] ?? []);
    }

    public function testOfficialModelCannotBeRenamedOrArchived(): void
    {
        $kit = OrganizationKitDefinitions::infantryLight();
        $itemRow = [
            'id' => 9,
            'code' => OrganizationKitDefinitions::INFANTRY_LIGHT,
            'title' => $kit['title'],
            'summary' => $kit['summary'],
            'version' => 1,
            'visibility' => 'official',
            'owner_tenant_id' => null,
            'definition_json' => json_encode($kit),
        ];
        $catalog = $this->createMock(OrganizationCatalogRepository::class);
        $catalog->method('tablesExist')->willReturn(true);
        $catalog->method('upsertOfficial')->willReturn(9);
        $catalog->method('findByCode')->willReturn($itemRow);
        $catalog->expects($this->never())->method('renamePrivate');
        $catalog->expects($this->never())->method('archivePrivate');

        $svc = new OrganizationCatalogService(
            $catalog,
            $this->createMock(UnitRepository::class),
            $this->createMock(PersonnelJobRoleRepository::class),
            $this->createMock(RoleRepository::class),
            $this->createMock(PermissionRepository::class),
            $this->createMock(TenantRolePermissionPresetService::class),
            $this->createMock(TenantRepository::class),
            $this->createMock(AuditService::class),
            null,
            $this->createMock(PDO::class)
        );

        $rename = $svc->renamePrivate(11, OrganizationKitDefinitions::INFANTRY_LIGHT, 'Autre nom', 1);
        self::assertFalse($rename['ok']);
        $archive = $svc->archivePrivate(11, OrganizationKitDefinitions::INFANTRY_LIGHT, 1);
        self::assertFalse($archive['ok']);
    }
}
