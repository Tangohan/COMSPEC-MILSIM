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

    public function testDisplayedNameStartsWithTheComparableVersion(): void
    {
        $root = dirname(__DIR__, 2);
        $hpp = (string) file_get_contents($root . '/mod/COMSPEC_ATAK_Native/Sources/addons/main/script_component.hpp');

        // VERSION_NAME (« 2.0.1.AB - Denver ») n'est qu'affiché ; VERSION_STR reste MAJEUR.MINEUR.CORRECTIF pour
        // les comparaisons (versionStr, contrôle d'intégrité, /api/atak/version).
        self::assertSame(1, preg_match('/^#define VERSION_NAME "([^"]+)"$/m', $hpp, $m));
        self::assertStringStartsWith(AtakReleaseInfo::latest($root)['atak_native'] . '.', $m[1]);
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

    public function testBuildStampIsNeverCommitted(): void
    {
        $root = dirname(__DIR__, 2) . '/mod/COMSPEC_ATAK_Native/Sources/addons/main';
        $hpp = (string) file_get_contents($root . '/build_info.hpp');

        // L'empreinte (date, commit) est posée au build puis retirée (docs/VERSIONING.md) : committée, elle ferait
        // entrer en conflit chaque PR native et afficherait une date fausse dans les builds suivants.
        self::assertSame(1, preg_match('/^#define BUILD_STAMP ""$/m', $hpp));
        self::assertStringContainsString('#include "build_info.hpp"', (string) file_get_contents($root . '/script_component.hpp'));
    }

    public function testMissingFilesGiveNullsInsteadOfErrors(): void
    {
        $info = AtakReleaseInfo::latest(sys_get_temp_dir() . '/atak-release-info-missing');

        self::assertNull($info['atak_native']);
        self::assertNull($info['link']);
    }

    public function testPlayerSummaryOfTheLatestVersionIsInBothLanguages(): void
    {
        $root = dirname(__DIR__, 2);
        $summary = AtakReleaseInfo::playerSummary((string) AtakReleaseInfo::latest($root)['atak_native'], $root);

        // Chaque version a son « Résumé joueur » : la notification « mise à jour disponible » le montre au joueur.
        self::assertNotSame('', $summary['fr']);
        self::assertNotSame('', $summary['en']);
        self::assertStringNotContainsString('|', $summary['fr'] . $summary['en']);
        self::assertLessThanOrEqual(300, mb_strlen($summary['fr']));
    }

    public function testPlayerSummaryReadsTitleAndBulletsOfTheRightEntry(): void
    {
        $dir = sys_get_temp_dir() . '/atak-release-info-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/CHANGELOG-ATAK.md', "# CHANGELOG\n\n## Autre (ATAK natif 1.10.0)\n\n### Résumé joueur\nType : correctif\n"
            . "FR : Mauvaise entrée\n- Non\nEN : Wrong entry\n- No\n\n## Carte (ATAK natif 1.1.0)\n\n### Résumé joueur\nType : nouveauté\n"
            . "FR : La carte | change\n- Un point.\n- Deux points.\nEN : The map changes\n- One dot.\n\n### Détail technique\n- `fn_x` interne\n");
        try {
            $summary = AtakReleaseInfo::playerSummary('1.1.0', $dir);
            self::assertSame('La carte  change. Un point. Deux points.', $summary['fr']);
            self::assertSame('The map changes. One dot.', $summary['en']);
            self::assertSame(['fr' => '', 'en' => ''], AtakReleaseInfo::playerSummary('1.2.0', $dir));
        } finally {
            @unlink($dir . '/CHANGELOG-ATAK.md');
            @rmdir($dir);
        }
    }

    public function testVersionEndpointNeedsNoApiKey(): void
    {
        self::assertFalse(ComspecApiKeyAuth::pathRequiresProtection('/api/atak/version'));
    }
}
