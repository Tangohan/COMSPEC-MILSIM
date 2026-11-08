<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Authorization\TenantPermissionCatalog;
use App\Services\Aar\AarConclusionAccess;
use App\Services\Rbac\CommunityAccessProfiles;
use PHPUnit\Framework\TestCase;

final class AarConclusionAccessTest extends TestCase
{
    public function testDeputyPostHolderIsRecognisedInAnyUnit(): void
    {
        $holders = [4 => [10], 9 => [11, 12]];

        self::assertTrue(AarConclusionAccess::holdsDeputyPost($holders, 12));
        self::assertFalse(AarConclusionAccess::holdsDeputyPost($holders, 13));
        self::assertFalse(AarConclusionAccess::holdsDeputyPost($holders, 0));
    }

    public function testPermissionBelongsToTheManagerProfile(): void
    {
        self::assertContains(AarConclusionAccess::PERMISSION, TenantPermissionCatalog::allSlugs());
        self::assertContains(AarConclusionAccess::PERMISSION, CommunityAccessProfiles::managerPermissionSlugs());
    }
}
