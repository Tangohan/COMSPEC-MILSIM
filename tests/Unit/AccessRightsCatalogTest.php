<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
use App\Services\Rbac\AccessRightsCatalog;
use PHPUnit\Framework\TestCase;

final class AccessRightsCatalogTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $rows = [];
        $id = 1;
        foreach (TenantPermissionCatalog::definitions() as $d) {
            $rows[] = ['id' => $id++, 'slug' => $d['slug'], 'name' => $d['name'], 'module' => $d['module']];
        }
        $rows[] = ['id' => $id++, 'slug' => 'admin.system', 'name' => 'Réservé', 'module' => 'admin'];

        return $rows;
    }

    public function testEveryCatalogRightHasDomainExplanationAndSensitivity(): void
    {
        $domains = array_column(AccessRightsCatalog::domains(), 'key');
        $all = TenantPermissionCatalog::allSlugs();
        foreach ($all as $slug) {
            $meta = AccessRightsCatalog::meta($slug);
            self::assertTrue(in_array($meta['domain'], $domains, true), $slug . ' sans domaine connu');
            self::assertTrue(trim($meta['explanation']) !== '', $slug . ' sans explication');
            self::assertTrue(in_array($meta['sensitivity'], ['standard', 'elevated', 'critical'], true), $slug);
            foreach ($meta['requires'] as $required) {
                self::assertTrue(in_array($required, $all, true), $slug . ' suppose un droit inconnu ' . $required);
                self::assertTrue($required !== $slug);
            }
        }
    }

    public function testGroupingListsEveryEditableRightOnceAndHidesPlatformRights(): void
    {
        $groups = AccessRightsCatalog::group($this->rows());
        $seen = [];
        foreach ($groups as $g) {
            self::assertTrue($g['items'] !== [], 'groupe vide ' . $g['key']);
            foreach ($g['items'] as $item) {
                self::assertFalse(isset($seen[$item['slug']]), 'doublon ' . $item['slug']);
                $seen[$item['slug']] = true;
            }
        }
        self::assertFalse(isset($seen['admin.system']));
        self::assertSame(count(TenantPermissionCatalog::allSlugs()), count($seen));
    }

    public function testDomainsSeparateSseTocAndRecruitment(): void
    {
        self::assertSame('sse', AccessRightsCatalog::domainFor('atak.sse.grant'));
        self::assertSame('toc', AccessRightsCatalog::domainFor('atak.toc.use'));
        self::assertSame('atak', AccessRightsCatalog::domainFor('atak.terminals.view'));
        self::assertSame('recruitment', AccessRightsCatalog::domainFor('member_integration.view'));
        self::assertSame('members', AccessRightsCatalog::domainFor('personnel.profile.view'));
        self::assertSame('other', AccessRightsCatalog::domainFor('zzz.unknown'));
    }

    public function testSensitivityAndDependencies(): void
    {
        self::assertSame('critical', AccessRightsCatalog::sensitivityFor('admin.roles.manage'));
        self::assertSame('critical', AccessRightsCatalog::sensitivityFor('documents.export', 'export'));
        self::assertSame('standard', AccessRightsCatalog::sensitivityFor('documents.view', 'view'));
        self::assertSame(['atak.sse.access'], AccessRightsCatalog::requiresFor('atak.sse.grant'));
        self::assertSame([], AccessRightsCatalog::requiresFor('documents.view'));
    }

    public function testScopeSummary(): void
    {
        self::assertSame('none', AccessRightsCatalog::scopeOf([]));
        self::assertSame('read', AccessRightsCatalog::scopeOf(['documents.view', 'training.view']));
        self::assertSame('write', AccessRightsCatalog::scopeOf(['documents.view', 'documents.upload']));
        self::assertSame('admin', AccessRightsCatalog::scopeOf(['documents.view', 'admin.roles.manage']));
    }

    public function testSpacesAreReadFromNavigationAndOpenedByRights(): void
    {
        $nav = ['menu' => ['a' => ['sections' => [['title' => 'Documents', 'links' => [
            ['label' => 'Bibliothèque', 'path' => 'documents', 'permission' => 'documents.view'],
            ['label' => 'Gestion', 'path' => 'documents/gestion', 'any_permissions' => ['documents.upload', 'admin.organization']],
            ['label' => 'Plateforme', 'path' => 'admin', 'permission' => 'admin.system'],
        ]]]]]];
        $spaces = AccessRightsCatalog::spacesFromNavigation($nav);
        self::assertCount(2, $spaces, 'les espaces plateforme sont exclus');
        $opened = AccessRightsCatalog::spacesOpenedBy($spaces, ['documents.view']);
        self::assertTrue($opened[0]['open']);
        self::assertFalse($opened[1]['open']);
        self::assertSame('Documents', $opened[0]['section']);
    }

    public function testMissingFromTenantListsCatalogRightsWithoutRow(): void
    {
        $rows = array_values(array_filter($this->rows(), static fn (array $r): bool => $r['slug'] !== 'events.manage'));
        self::assertSame(['events.manage'], AccessRightsCatalog::missingFromTenant($rows));
    }
}
