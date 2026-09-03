<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsRosterDarkUiAssetTest extends TestCase
{
    public function testRosterUsesDarkAlertsMetricsAndHumanCopy(): void
    {
        $root = dirname(__DIR__, 2);
        $roster = (string) file_get_contents($root . '/views/admin/effectifs_workspace/roster.php');
        $css = (string) file_get_contents($root . '/public/assets/css/effectifs_lms.css');
        $dispatch = (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php');

        self::assertStringContainsString('eff-banner eff-banner--warn', $roster);
        self::assertStringContainsString('Fiches jumelles à relire', $roster);
        self::assertStringContainsString('deux dossiers pour la même personne', $roster);
        self::assertStringContainsString('Ouvrir les fiches', $roster);
        self::assertStringNotContainsString('bg-amber-50', $roster);
        self::assertStringNotContainsString('contamination d’identité', $roster);
        self::assertStringNotContainsString('color:#0f172a', $roster);
        self::assertStringNotContainsString('background:#f8fafc', $roster);

        self::assertStringContainsString('eff-metrics--roster', $roster);
        self::assertStringContainsString('eff-metric--link', $roster);
        self::assertStringContainsString('eff-bulkbar', $roster);
        self::assertStringContainsString('$assignmentLeaf', $roster);
        self::assertStringContainsString('$assignmentParent', $roster);

        $js = (string) file_get_contents($root . '/public/assets/js/eff-bulk-actions.js');
        self::assertStringContainsString("checked + ' sélectionnés'", $js);

        self::assertStringContainsString('.eff-banner--warn', $css);
        self::assertStringContainsString('.eff-metrics--roster', $css);
        self::assertStringContainsString('.eff-bulkbar', $css);
        self::assertStringContainsString('.eff-catalog--dark .eff-sheets__badge--watch', $css);
        self::assertStringContainsString('.eff-catalog--dark .eff-catalog-filters input', $css);

        self::assertStringContainsString('$pr(402,', $dispatch);
        self::assertStringContainsString('Le bureau effectifs se lit sur fond sombre', $dispatch);
    }
}
