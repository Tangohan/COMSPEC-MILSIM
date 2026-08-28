<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class EffectifsElevationDialogAssetTest extends TestCase
{
    public function testRosterElevationOpensInDialogNotTablePopover(): void
    {
        $roster = (string) file_get_contents(
            dirname(__DIR__, 2) . '/views/admin/effectifs_workspace/roster.php'
        );
        $css = (string) file_get_contents(
            dirname(__DIR__, 2) . '/public/assets/css/effectifs_lms.css'
        );

        self::assertStringContainsString('data-eff-elev-open', $roster);
        self::assertStringContainsString('eff-elev-dialog', $roster);
        self::assertStringContainsString('showModal', $roster);
        self::assertStringContainsString('elevation_request_fields.php', $roster);
        self::assertStringNotContainsString('eff-sheets__pop--end', $roster);

        self::assertStringContainsString('.eff-elev-dialog {', $css);
        self::assertStringContainsString('white-space: normal', $css);
        self::assertStringContainsString('max-height: min(90vh, 48rem)', $css);
    }
}
