<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AtakReleaseInfo;
use App\Support\ComspecApiKeyAuth;
use PHPUnit\Framework\TestCase;

/**
 * Dernières versions exposées par GET /api/atak/version : lues dans les fichiers du mod, sans valeur à recopier.
 */
final class AtakReleaseInfoTest extends TestCase
{
    public function testReadsVersionsFromModSources(): void
    {
        $info = AtakReleaseInfo::latest(dirname(__DIR__, 2));

        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) $info['atak_native']);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) $info['link']);
    }

    public function testMainPboExpectsTheLinkDllOfTheSameCommit(): void
    {
        $info = AtakReleaseInfo::latest(dirname(__DIR__, 2));

        // LINK_VERSION_STR (script_component.hpp) doit suivre COMSPECExtension.csproj : sinon chaque joueur à jour
        // verrait « DLL d'une autre version » dans ses notifications.
        self::assertSame($info['link'], $info['link_expected']);
    }

    public function testExtensionConstantMatchesCsproj(): void
    {
        $root = dirname(__DIR__, 2);
        $cs = (string) file_get_contents($root . '/mod/UptoDate/COMSPECExtension/Extension.cs');
        self::assertSame(1, preg_match('/private const string ExtensionVersion = "([0-9.]+)";/', $cs, $m));

        self::assertSame(AtakReleaseInfo::latest($root)['link'], $m[1]);
    }

    public function testMissingFilesGiveNullsInsteadOfErrors(): void
    {
        $info = AtakReleaseInfo::latest(sys_get_temp_dir() . '/atak-release-info-missing');

        self::assertNull($info['atak_native']);
        self::assertNull($info['link']);
    }

    public function testVersionEndpointNeedsNoApiKey(): void
    {
        self::assertFalse(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/version'));
    }
}
