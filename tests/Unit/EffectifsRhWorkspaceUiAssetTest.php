<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsRhWorkspaceUiAssetTest extends TestCase
{
    public function testSelectChevronDoesNotTileOnLightTheme(): void
    {
        $css = (string) file_get_contents(
            dirname(__DIR__, 2) . '/public/assets/css/back-office-effectifs-workspace.css'
        );

        self::assertStringContainsString('.bo-eff-workspace .eff-rh-field select', $css);
        self::assertStringContainsString('background-repeat: no-repeat', $css);
        self::assertStringContainsString('background-position: right 0.7rem center', $css);
        self::assertStringContainsString('background-size: 12px 8px', $css);
        self::assertStringContainsString('background-color: #fff', $css);

        // Le shorthand `background: #fff` sur le select réinitialisait repeat → chevrons en mosaïque (« vvvv »).
        self::assertDoesNotMatchRegularExpression(
            '/\.bo-eff-workspace \.eff-rh-field (?:input:not\(\[type="file"\]\),\s*)?select(?:,\s*\.bo-eff-workspace \.eff-rh-field textarea)?\s*\{[^}]*\bbackground:\s*#fff\b/s',
            $css
        );
    }

    public function testDocumentsAndDeparturesUseRhWorkspaceLanguage(): void
    {
        $docs = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/rh_documents.php'
        );
        $deps = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/departures.php'
        );
        $settings = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/rh_settings.php'
        );

        self::assertStringContainsString('eff-rh-hero', $docs);
        self::assertStringContainsString('Ajouter une pièce', $docs);
        self::assertStringContainsString('Établir une pièce', $docs);
        self::assertStringContainsString('<textarea name="description"', $docs);
        self::assertStringContainsString('eff-rh-hero', $deps);
        self::assertStringContainsString('eff-rh-pills', $deps);
        self::assertStringContainsString('Départs', $deps);
        self::assertStringContainsString('eff-rh-tiles', $settings);
        self::assertStringContainsString("effectifs_workspace_url('documents-rh')", $settings);
    }
}
