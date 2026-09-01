<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HrCharterMemberUiAssetTest extends TestCase
{
    public function testMemberCharterUsesAccountHubReadingLayout(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/views/rh/charter.php');
        $css = (string) file_get_contents($root . '/public/assets/css/account-hub.css');
        $js = (string) file_get_contents($root . '/public/assets/js/rh-charter.js');
        $controller = (string) file_get_contents($root . '/app/Controllers/Web/HrCharterController.php');
        $repo = (string) file_get_contents($root . '/app/Repositories/HrCharterRepository.php');

        self::assertStringContainsString("accountNavKey = 'charter'", $view);
        self::assertStringContainsString('shell_open.php', $view);
        self::assertStringContainsString('hr-charter__scroll', $view);
        self::assertStringContainsString('hr-charter__reader', $view);
        self::assertStringContainsString('hr-charter-progress-fill', $view);
        self::assertStringContainsString('Je confirme avoir lu', $view);
        self::assertStringContainsString('Environ', $view);
        self::assertStringContainsString('account-hub__stat-grid', $view);
        self::assertStringContainsString('accountHubPage', $controller);
        self::assertStringContainsString('findAcceptanceAt', $repo);
        self::assertStringContainsString('.hr-charter__prose', $css);
        self::assertStringContainsString('.hr-charter__confirm', $css);
        self::assertStringContainsString('.hr-charter__reader', $css);
        self::assertStringContainsString('hr-charter-progress-fill', $js);
        self::assertStringContainsString('hr-charter-jump', $js);
        self::assertStringContainsString('La charte des formations se lit comme un vrai document', (string) file_get_contents($root . '/app/Support/DevDispatchCatalog.php'));
    }
}
