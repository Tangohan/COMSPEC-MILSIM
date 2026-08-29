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

    public function testRedirectConstantsRemainStable(): void
    {
        self::assertSame('tba', PortalAccessChoice::PORTAL_TBA);
        self::assertSame('jnet', PortalAccessChoice::PORTAL_JNET);
        self::assertSame('jnet', PortalAccessChoice::normalize('JNET'));
        self::assertSame('tba', PortalAccessChoice::normalize('tba'));
        self::assertNull(PortalAccessChoice::normalize('dashboard'));
    }
}
