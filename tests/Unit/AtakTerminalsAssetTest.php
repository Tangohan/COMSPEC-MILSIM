<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AtakTerminalsAssetTest extends TestCase
{
    public function testLiaisonBadgeHasReservedWidthAndDoesNotScale(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/atak.css');
        $start = strpos($css, '.atak-terminal-card__head {');
        $end = strpos($css, '.atak-terminal-card__rows {');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        self::assertGreaterThan($start, $end);
        $block = substr($css, $start, $end - $start);

        self::assertStringContainsString('flex-wrap: nowrap;', $block);
        self::assertStringContainsString('align-items: center;', $block);
        self::assertStringContainsString('flex: 0 0 8.25rem;', $block);
        self::assertStringContainsString('white-space: nowrap;', $block);
        self::assertStringContainsString('text-align: center;', $block);
        self::assertStringContainsString('text-overflow: ellipsis;', $block);
        self::assertStringContainsString('atak-terminal-state-pulse', $block);
        self::assertStringNotContainsString('align-items: baseline;', $block);
        self::assertStringNotContainsString('atak-badge-pulse', $block);
        self::assertStringNotContainsString('scale(', $block);
    }

    public function testCardShowsPackVersionsOutsideTheTypeRow(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/atak-terminals.js');

        self::assertStringContainsString("row('Overwatch'", $js);
        self::assertStringContainsString("row('Liaison Athena'", $js);
        self::assertStringContainsString('function typeLabel(', $js);
        self::assertStringContainsString('function versionRows(', $js);
        self::assertStringContainsString('packVersionFromPlatform', $js);
        self::assertStringContainsString('t.mod_version', $js);
        self::assertStringContainsString('t.extension_version', $js);
        self::assertStringContainsString('versionRows(t, extra)', $js);
        self::assertStringNotContainsString(
            "t.terminal_type === 'phone' ? 'Téléphone' : (t.platform_label || t.terminal_type || 'Poste')",
            $js
        );
    }
}
