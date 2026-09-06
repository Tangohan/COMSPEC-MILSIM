<?php

declare(strict_types=1);

use App\Services\Rbac\CommunityAccessProfiles;
use PHPUnit\Framework\TestCase;

final class SimplifiedCommunityRolesTest extends TestCase
{
    public function testCanonicalAccessLabelsAreTheThreeProfiles(): void
    {
        $labels = array_column(CommunityAccessProfiles::definitions(), 'name', 'slug');

        self::assertSame('Membre', $labels['member']);
        self::assertSame('Ressources humaines', $labels['hr']);
        self::assertSame('Gestionnaire', $labels['community_owner']);
    }

    public function testRolePermissionsMatchPageResponsibilities(): void
    {
        $member = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::MEMBER);
        $hr = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::HR);
        $manager = CommunityAccessProfiles::permissionSlugsFor(CommunityAccessProfiles::MANAGER);

        self::assertContains('training.view', $member);
        self::assertContains('organization.recruitment.manage', $hr);
        self::assertContains('organization.effectifs.hub.view', $hr);
        self::assertContains('admin.organization', $manager);
    }
}
