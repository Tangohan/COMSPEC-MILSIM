<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Personnel\PersonnelDuplicateDetectionService;
use PHPUnit\Framework\TestCase;

final class EffectifsDuplicatesUiAssetTest extends TestCase
{
    public function testDuplicatesPageUsesRhWorkspaceLanguage(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/admin/effectifs_workspace/duplicates.php');
        $css = (string) file_get_contents($root . '/public/assets/css/effectifs_lms.css');
        $labels = PersonnelDuplicateDetectionService::FIELD_LABELS;

        self::assertStringContainsString('eff-rh-hero', $view);
        self::assertStringContainsString('Fiches jumelles', $view);
        self::assertStringContainsString('eff-rh-checks', $view);
        self::assertStringContainsString('eff-rh-switch', $view);
        self::assertStringContainsString("effectifs_workspace_url('doublons')", $view);
        self::assertStringContainsString("effectifs_workspace_url('membres/'", $view);
        self::assertStringNotContainsString('bg-white', $view);
        self::assertSame('Indicatif', $labels['callsign']);
        self::assertSame('Nom du personnage', $labels['character_name']);
        self::assertStringContainsString('.eff-rh-check', $css);
        self::assertStringContainsString('.eff-rh-dup-group', $css);
    }
}
