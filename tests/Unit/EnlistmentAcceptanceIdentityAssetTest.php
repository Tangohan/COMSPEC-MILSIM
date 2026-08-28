<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EnlistmentAcceptanceIdentityAssetTest extends TestCase
{
    public function testAcceptProvisioningDoesNotKeepReviewerIdentity(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Recruitment/EnlistmentAcceptanceProvisioningService.php'
        );
        $clone = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Repositories/UserRepository.php'
        );
        $tableau = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/partials/personnel/file_tableau_admin_tab.php'
        );

        self::assertStringContainsString('applyAcceptedIdentityFromEnlistment', $src);
        self::assertStringContainsString('EnlistmentAcceptedIdentity', $src);
        self::assertStringContainsString('$srcEmail !== $wantEmail', $src);
        self::assertStringContainsString('$cloneOverrides', $src);
        self::assertStringContainsString('identityOverrides', $clone);
        self::assertStringContainsString('distinctCharacterLabel', $tableau);
        self::assertStringContainsString('Nom de personnage', $tableau);
    }
}
