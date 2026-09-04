<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SseWorkshopPackAssetTest extends TestCase
{
    private function sseRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'mod' . DIRECTORY_SEPARATOR . '@COMSPEC_SSE';
    }

    public function testBuildScriptsPointAtAddonBuilderLikeOverwatch(): void
    {
        $root = $this->sseRoot();
        $build = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'build_pbo.bat');
        $wrapper = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'build_mod.bat');

        self::assertStringContainsString('Arma 3 Tools\AddonBuilder\AddonBuilder.exe', $build);
        self::assertStringContainsString('COMSPEC_BUILD_NOPAUSE', $build);
        self::assertStringContainsString('comspec_sse_%%C.pbo', $build);
        self::assertStringContainsString('compat_ace', $build);
        self::assertStringContainsString('compat_bii', $build);
        self::assertStringContainsString('build_pbo.bat', $wrapper);
    }

    public function testWorkshopPackCopiesPbosAndExcludesInternalDocs(): void
    {
        $script = (string) file_get_contents($this->sseRoot() . DIRECTORY_SEPARATOR . 'workshop-pack.ps1');

        self::assertStringContainsString("publisher\\@COMSPEC_SSE", $script);
        self::assertStringContainsString('comspec_sse_main.pbo', $script);
        self::assertStringContainsString('comspec_sse_compat_ace.pbo', $script);
        self::assertStringContainsString('STEAM_DESCRIPTION.md', $script);
        self::assertStringContainsString('PACKAGING.md', $script);
        self::assertStringContainsString("docs|obj|bin|tools|missions", $script);
        self::assertStringNotContainsString('Copy-Item -LiteralPath $docsSrc', $script);
        self::assertStringNotContainsString('Copie documentation joueur (docs/)', $script);
    }

    public function testPublicationGuideStaysHumanReadable(): void
    {
        $pub = (string) file_get_contents(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'mod' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'PUBLICATION.md'
        );
        $lower = strtolower($pub);

        self::assertStringContainsString('Publisher', $pub);
        self::assertStringContainsString('publisher/@COMSPEC_SSE', $pub);
        self::assertStringContainsString('CBA', $pub);
        self::assertStringContainsString('ACE3', $pub);
        self::assertStringNotContainsString('sqf', $lower);
        self::assertStringNotContainsString('endpoint', $lower);
        self::assertStringNotContainsString('json', $lower);
        self::assertStringNotContainsString('.pbo', $lower);
        self::assertStringNotContainsString('addonbuilder', $lower);
    }

    public function testRequiredPackedAddonsHaveSources(): void
    {
        $addons = $this->sseRoot() . DIRECTORY_SEPARATOR . 'addons';
        foreach ([
            'main', 'core', 'generator', 'evidence', 'intel', 'interaction',
            'zeus', 'eden', 'ui', 'network', 'digital', 'biometrics',
            'compat_ace', 'compat_bii',
        ] as $component) {
            self::assertFileExists($addons . DIRECTORY_SEPARATOR . $component . DIRECTORY_SEPARATOR . 'config.cpp');
            self::assertFileExists($addons . DIRECTORY_SEPARATOR . $component . DIRECTORY_SEPARATOR . '$PBOPREFIX$');
        }
    }
}
