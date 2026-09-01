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
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');
        $labels = PersonnelDuplicateDetectionService::FIELD_LABELS;

        self::assertStringContainsString('eff-page-head', $view);
        self::assertStringContainsString('eff-page-title', $view);
        self::assertStringContainsString('Fiches jumelles', $view);
        self::assertStringContainsString('Pilotage RH', $view);
        self::assertStringContainsString('eff-metrics', $view);
        self::assertStringContainsString('eff-dup', $view);
        self::assertStringContainsString('eff-dup-fields', $view);
        self::assertStringContainsString('eff-dup-chip', $view);
        self::assertStringContainsString('eff-dup-switch', $view);
        self::assertStringContainsString('eff-empty', $view);
        self::assertStringContainsString('Aucune fiche jumelle', $view);
        self::assertStringContainsString('eff-btn eff-btn--primary', $view);
        self::assertStringContainsString("effectifs_workspace_url('doublons')", $view);
        self::assertStringContainsString("effectifs_workspace_url('membres/'", $view);
        self::assertStringNotContainsString('bg-white', $view);
        self::assertStringNotContainsString('Alerte RH', $view);
        self::assertStringNotContainsString('.eff-dup-fields { grid-template-columns: 1fr', $css);
        self::assertSame('Indicatif', $labels['callsign']);
        self::assertSame('Nom du personnage', $labels['character_name']);
        self::assertStringContainsString('.eff-dup-chip', $css);
        self::assertStringContainsString('.eff-dup-fields', $css);
        self::assertStringContainsString('flex: 0 0 11rem', $css);
        self::assertStringContainsString('.eff-rh-dup-group', $css);
        self::assertStringContainsString('Les fiches jumelles occupent enfin le bureau', $dispatch);
        self::assertStringContainsString('$pr(273,', $dispatch);
    }
}
