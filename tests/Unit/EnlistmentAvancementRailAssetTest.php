<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\EnlistmentMilsimPackService;
use PHPUnit\Framework\TestCase;

final class EnlistmentAvancementRailAssetTest extends TestCase
{
    public function testDefaultPackIncludesMilitaryRailAtmosphere(): void
    {
        $pack = EnlistmentMilsimPackService::defaultPack();

        self::assertSame('Avancement', $pack['session_block_title']);
        self::assertSame('DIFFUSION RESTREINTE', $pack['rail_classification']);
        self::assertIsArray($pack['rail_meta_rows']);
        self::assertNotEmpty($pack['rail_meta_rows']);
        self::assertSame('Bureau émetteur', $pack['rail_meta_rows'][0]['label']);
        self::assertStringContainsString('S1', (string) $pack['rail_meta_rows'][0]['value']);
    }

    public function testNormalizeRailMetaRowsFiltersEmptyAndCaps(): void
    {
        $rows = EnlistmentMilsimPackService::normalizeRailMetaRows([
            ['label' => 'Priorité', 'value' => 'ROUTINE'],
            ['label' => '', 'value' => 'x'],
            ['label' => 'x', 'value' => ''],
            'ignore',
            ['label' => str_repeat('A', 60), 'value' => str_repeat('B', 80)],
        ]);

        self::assertCount(2, $rows);
        self::assertSame('Priorité', $rows[0]['label']);
        self::assertSame(48, mb_strlen($rows[1]['label']));
        self::assertSame(64, mb_strlen($rows[1]['value']));
    }

    public function testEnlistmentViewRendersRailClassificationAndMeta(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/enlistment.php');

        self::assertStringContainsString('ce-rail__classif', $view);
        self::assertStringContainsString('rail_classification', $view);
        self::assertStringContainsString('rail_meta_rows', $view);
        self::assertStringContainsString('ce-rail__stamp', $view);
        self::assertStringContainsString('Parcours dossier', $view);
        self::assertStringContainsString('01 — Mode de candidature', $view);
    }

    public function testCommunityCssStylesRailAtmosphere(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/community-enlistment.css');

        self::assertStringContainsString('.ce-rail__classif', $css);
        self::assertStringContainsString('.ce-rail__stamp', $css);
        self::assertStringContainsString('.ce-rail__row--meta', $css);
        self::assertStringContainsString('.ce-rail__nav-label', $css);
    }
}
