<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelFunctionKitCatalog;
use App\Services\Personnel\PersonnelFunctionKitService;
use App\Services\Rbac\MilitaryOperationalRoleCatalog;
use PHPUnit\Framework\TestCase;

final class PersonnelFunctionKitCatalogTest extends TestCase
{
    public function testKitIdsAreUniqueAndHuman(): void
    {
        $ids = [];
        foreach (PersonnelFunctionKitCatalog::all() as $kit) {
            $id = (string) ($kit['id'] ?? '');
            self::assertNotSame('', $id);
            self::assertArrayNotHasKey($id, $ids, 'Identifiant de kit dupliqué : ' . $id);
            $ids[$id] = true;
            self::assertNotSame('', trim((string) ($kit['label'] ?? '')));
            self::assertNotSame('', trim((string) ($kit['summary'] ?? '')));
            self::assertNotSame('', trim((string) ($kit['role_slug'] ?? '')));
            self::assertTrue(PersonnelFunctionKitCatalog::isAccessKitRoleSlug($kit['role_slug']));
            self::assertGreaterThanOrEqual(2, count($kit['permission_slugs']));
            self::assertContains($kit['tone'], ['lecture', 'modification', 'admin']);
        }
        self::assertGreaterThanOrEqual(8, count($ids));
        self::assertLessThanOrEqual(14, count($ids));
        self::assertArrayHasKey('lecture', $ids);
        self::assertArrayHasKey('lecture_modification', $ids);
        self::assertArrayHasKey('recrutement', $ids);
        self::assertArrayHasKey('recrutement_lecture', $ids);
        self::assertArrayHasKey('tenant_parametres', $ids);
    }

    public function testPermissionSlugsAreKnownAndNonReserved(): void
    {
        $known = array_fill_keys(\App\Authorization\TenantPermissionCatalog::allSlugs(), true);
        foreach (PersonnelFunctionKitCatalog::all() as $kit) {
            foreach ($kit['permission_slugs'] as $slug) {
                self::assertArrayHasKey($slug, $known, $kit['id'] . ' ' . $slug);
                self::assertFalse(
                    \App\Authorization\SystemReservedPermissions::isReserved($slug),
                    $kit['id'] . ' reserved ' . $slug
                );
            }
        }
    }

    public function testEmptyKitsMeanNoSlugUnion(): void
    {
        self::assertSame([], PersonnelFunctionKitCatalog::permissionSlugsForKitIds([]));
        self::assertSame([], PersonnelFunctionKitCatalog::keyFunctionsForKitIds([]));
    }

    public function testLectureKitResolvesViewSlugsOnly(): void
    {
        $slugs = PersonnelFunctionKitCatalog::permissionSlugsForKitIds(['lecture']);
        self::assertContains('personnel.profile.view', $slugs);
        self::assertContains('documents.view', $slugs);
        self::assertNotContains('personnel.profile.update', $slugs);
        self::assertNotContains('organization.recruitment.manage', $slugs);
        self::assertNotContains('admin.settings.manage', $slugs);
    }

    public function testRecruitmentAndTenantKitsAreDistinct(): void
    {
        $recruit = PersonnelFunctionKitCatalog::permissionSlugsForKitIds(['recrutement']);
        $tenant = PersonnelFunctionKitCatalog::permissionSlugsForKitIds(['tenant_parametres']);
        self::assertContains('organization.recruitment.manage', $recruit);
        self::assertContains('invitations.send', $recruit);
        self::assertContains('admin.settings.manage', $tenant);
        self::assertContains('admin.organization', $tenant);
        self::assertNotContains('admin.settings.manage', $recruit);
    }

    public function testMultiSelectUnionsPermissions(): void
    {
        $slugs = PersonnelFunctionKitCatalog::permissionSlugsForKitIds(['lecture', 'recrutement_lecture']);
        self::assertContains('forum.view', $slugs);
        self::assertContains('admin.members.view', $slugs);
        self::assertNotContains('organization.recruitment.manage', $slugs);
    }

    public function testFilterKeepsAssignedCustomAndCatalogHits(): void
    {
        $allowed = ['infantry_rifleman' => true];
        $catalog = MilitaryOperationalRoleCatalog::catalogSlugSet();
        $filtered = PersonnelFunctionKitService::filterOptionsByAllowedSlugs(
            [
                ['id' => 1, 'slug' => 'infantry_rifleman', 'label' => 'Fusilier'],
                ['id' => 2, 'slug' => 'medical_officer', 'label' => 'Médecin militaire'],
                ['id' => 3, 'slug' => 'maison_custom', 'label' => 'Fonction maison'],
            ],
            $allowed,
            $catalog
        );
        $slugs = array_column($filtered, 'slug');
        self::assertContains('infantry_rifleman', $slugs);
        self::assertContains('maison_custom', $slugs);
        self::assertNotContains('medical_officer', $slugs);
    }
}
