<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PortalAccessChoice;
use PHPUnit\Framework\TestCase;

final class PortalAccessChoiceNoOrgTest extends TestCase
{
    public function testPlaceholderTenantDetection(): void
    {
        self::assertTrue(PortalAccessChoice::isPlaceholderTenant(null));
        self::assertTrue(PortalAccessChoice::isPlaceholderTenant(['slug' => 'default', 'name' => 'Default']));
        self::assertTrue(PortalAccessChoice::isPlaceholderTenant(['slug' => '', 'name' => 'Aucune']));
        self::assertTrue(PortalAccessChoice::isPlaceholderTenant(['slug' => 'foo', 'name' => 'Aucune organisation']));
        self::assertTrue(PortalAccessChoice::isPlaceholderTenant(['slug' => 'foo', 'name' => "Pas d'organisation"]));
        self::assertFalse(PortalAccessChoice::isPlaceholderTenant(['slug' => 'alpha', 'name' => 'Unité Alpha']));
    }

    public function testPersonFileHidesPlaceholderWhenALiveCommunityExists(): void
    {
        $soar = ['tenant_slug' => 'soar', 'tenant_name' => 'SOAR', 'id' => 10];
        $placeholder = ['tenant_slug' => 'default', 'tenant_name' => "Pas d'organisation", 'id' => 10];

        $visible = PortalAccessChoice::personFileDossiers([$soar, $placeholder]);
        self::assertCount(1, $visible);
        self::assertSame('soar', $visible[0]['tenant_slug']);

        $orphanOnly = PortalAccessChoice::personFileDossiers([$placeholder]);
        self::assertCount(1, $orphanOnly);
        self::assertSame('default', $orphanOnly[0]['tenant_slug']);

        $leftCommunity = [
            'tenant_slug' => 'soar',
            'tenant_name' => 'SOAR',
            'status' => 'active',
            'membership_status' => 'left',
        ];
        $afterLeave = PortalAccessChoice::personFileDossiers([$leftCommunity, $placeholder]);
        self::assertCount(1, $afterLeave);
        self::assertSame('default', $afterLeave[0]['tenant_slug']);
    }

    public function testRedirectConstantsRemainStable(): void
    {
        self::assertSame('tba', PortalAccessChoice::PORTAL_TBA);
        self::assertSame('jnet', PortalAccessChoice::PORTAL_JNET);
        self::assertSame('jnet', PortalAccessChoice::normalize('JNET'));
        self::assertSame('tba', PortalAccessChoice::normalize('tba'));
        self::assertNull(PortalAccessChoice::normalize('dashboard'));
        $choice = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/PortalAccessChoice.php');
        self::assertStringContainsString("return url('dashboard');", $choice);
        self::assertStringNotContainsString("return url('jnet');", $choice);
        $select = (string) file_get_contents(dirname(__DIR__, 2) . '/views/auth/select-portal.php');
        self::assertStringContainsString('Accueil Athena', $select);
        self::assertStringNotContainsString('JNET Extranet', $select);
    }
}
