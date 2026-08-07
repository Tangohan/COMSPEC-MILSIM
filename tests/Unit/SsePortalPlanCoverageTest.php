<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Rbac\RolePermissionMatrixCatalog;
use App\Services\Sse\SseCrossMatchService;
use PHPUnit\Framework\TestCase;

final class SsePortalPlanCoverageTest extends TestCase
{
    public function testCommandRolesHaveFullSsePermissions(): void
    {
        foreach (['community_owner', 'tenant_admin', 'deputy_commander', 'operations_officer'] as $slug) {
            $profile = RolePermissionMatrixCatalog::defaultProfileForRoleSlug($slug);
            self::assertNotNull($profile, $slug);
            $atak = $profile['modules'][RolePermissionMatrixCatalog::MODULE_ATAK] ?? RolePermissionMatrixCatalog::LEVEL_NONE;
            $slugs = RolePermissionMatrixCatalog::permissionSlugsForModuleLevel(
                RolePermissionMatrixCatalog::MODULE_ATAK,
                $atak
            );
            foreach (['atak.sse.access', 'atak.sse.grant', 'atak.sse.case.manage', 'atak.sse.export'] as $perm) {
                self::assertContains($perm, $slugs, $slug . ' doit inclure ' . $perm);
            }
        }
    }

    public function testOfficerRolesHaveSseWithoutGrant(): void
    {
        foreach (['officer', 'intelligence_officer'] as $slug) {
            $profile = RolePermissionMatrixCatalog::defaultProfileForRoleSlug($slug);
            self::assertNotNull($profile, $slug);
            $atak = $profile['modules'][RolePermissionMatrixCatalog::MODULE_ATAK] ?? RolePermissionMatrixCatalog::LEVEL_NONE;
            $slugs = RolePermissionMatrixCatalog::permissionSlugsForModuleLevel(
                RolePermissionMatrixCatalog::MODULE_ATAK,
                $atak
            );
            self::assertContains('atak.sse.access', $slugs, $slug);
            self::assertContains('atak.sse.case.manage', $slugs, $slug);
            self::assertContains('atak.sse.export', $slugs, $slug);
            self::assertNotContains('atak.sse.grant', $slugs, $slug . ' ne doit pas délivrer les codes');
        }
    }

    public function testMemberHasNoSsePermissions(): void
    {
        $profile = RolePermissionMatrixCatalog::defaultProfileForRoleSlug('member');
        self::assertNotNull($profile);
        $atak = $profile['modules'][RolePermissionMatrixCatalog::MODULE_ATAK] ?? RolePermissionMatrixCatalog::LEVEL_NONE;
        $slugs = RolePermissionMatrixCatalog::permissionSlugsForModuleLevel(
            RolePermissionMatrixCatalog::MODULE_ATAK,
            $atak
        );
        self::assertNotContains('atak.sse.access', $slugs);
        self::assertNotContains('atak.sse.grant', $slugs);
    }

    public function testCrossMatchScoresIdenticalNamesAboveThreshold(): void
    {
        $svc = new SseCrossMatchService();
        $hit = $svc->evaluateMatch(
            ['last_name' => 'Karim', 'first_name' => 'Hassan', 'alias' => 'Falcon'],
            ['last_name' => 'Karim', 'first_name' => 'Hassan', 'alias' => 'Falcon']
        );
        self::assertGreaterThanOrEqual(SseCrossMatchService::MATCH_THRESHOLD, $hit['score']);
        self::assertStringContainsString('Nom', $hit['reason']);
    }

    public function testCrossMatchIgnoresUnrelatedIdentities(): void
    {
        $svc = new SseCrossMatchService();
        $hit = $svc->evaluateMatch(
            ['last_name' => 'Martin', 'first_name' => 'Paul', 'alias' => ''],
            ['last_name' => 'Dupont', 'first_name' => 'Jean', 'alias' => 'Ghost']
        );
        self::assertLessThan(SseCrossMatchService::MATCH_THRESHOLD, $hit['score']);
    }

    public function testClassificationLabelsAreMetierFrench(): void
    {
        $labels = \App\Repositories\SseCaseRepository::CLASSIFICATION_LABELS;
        self::assertSame('Diffusion interne', $labels['interne']);
        self::assertSame('Encadrement', $labels['encadrement']);
        self::assertSame('Confidentiel', $labels['confidentiel']);
        self::assertSame('Diffusion très restreinte', $labels['tres_restreint']);
    }
}
