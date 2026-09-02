<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class RoleplayFollowupSheetsAssetTest extends TestCase
{
    public function testActionPopoversEscapeTheScrollableTable(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/roleplay_followup.php');
        self::assertStringContainsString('rp-followup-sheets__pop-panel', $src);
        self::assertStringContainsString('overflow: visible', $src);
        self::assertStringContainsString('is-ported', $src);
        self::assertStringContainsString('document.body.appendChild(panel)', $src);
        self::assertStringContainsString('nth-last-child(2)', $src);
        self::assertStringContainsString('Nouvelle étape', $src);
    }
}
