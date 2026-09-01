<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\OrganizationCatalog\OrganizationKitDefinitions;
use PHPUnit\Framework\TestCase;

final class OrganizationKitDefinitionsTest extends TestCase
{
    public function testOfficialKitsAreTenantAgnosticAndComplete(): void
    {
        $kits = OrganizationKitDefinitions::officialKits();
        self::assertCount(2, $kits);
        self::assertSame(
            [OrganizationKitDefinitions::INFANTRY_LIGHT, OrganizationKitDefinitions::GAMING_COMMUNITY],
            OrganizationKitDefinitions::officialCodes()
        );

        foreach ($kits as $kit) {
            self::assertArrayNotHasKey('tenant_id', $kit);
            $encoded = json_encode($kit);
            self::assertIsString($encoded);
            self::assertStringNotContainsString('"tenant_id"', $encoded);
            self::assertNotSame('', trim((string) ($kit['title'] ?? '')));
            self::assertNotSame('', trim((string) ($kit['summary'] ?? '')));
            self::assertNotEmpty($kit['units'] ?? []);
            self::assertNotEmpty($kit['job_roles'] ?? []);
            self::assertNotEmpty($kit['roles'] ?? []);
            self::assertNotSame('', (string) ($kit['grade_system_code'] ?? ''));
            self::assertStringContainsString('unités', OrganizationKitDefinitions::volumeLabel($kit));
        }
    }
}
