<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OverwatchSteamIdentityTransportAssetTest extends TestCase
{
    public function testAsyncConnectFallbackKeepsSteamIdentityAndClientMetadata(): void
    {
        $extension = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs'
        );
        $fallback = strstr($extension, 'private static void RvExtensionArgsImpl');
        self::assertNotFalse($fallback);
        $connect = substr((string) $fallback, 0, 1800);

        self::assertStringContainsString('ApplySteamUid(args[3])', $connect);
        self::assertStringContainsString('ApplyModVersion(args[4])', $connect);
        self::assertStringContainsString('ApplyBloodType(args[5])', $connect);
        self::assertStringContainsString('StartClientInitAsync()', $connect);
    }

    public function testPlayerPositionWaitsForSteamInsteadOfSpammingDeniedWrites(): void
    {
        $extension = (string) file_get_contents(
            dirname(__DIR__, 2) . '/mod/UptoDate/COMSPECExtension/Extension.cs'
        );

        self::assertStringContainsString(
            'if (!isProxyContact && steamNorm.Length == 0)\n                    return;',
            $extension
        );

        $guard = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Support/AtakArmaWriteGuard.php');
        self::assertStringContainsString("'steam_required',\n                300,", $guard);
        self::assertStringNotContainsString(
            "\$this->log(\$tenantId, false, 'Accès jeu refusé — identifiant Steam manquant'",
            $guard
        );
    }
}
