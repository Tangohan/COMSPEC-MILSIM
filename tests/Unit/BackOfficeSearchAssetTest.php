<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BackOfficeSearchAssetTest extends TestCase
{
    public function testBackOfficeSearchIsWiredInShellAndApi(): void
    {
        $topbar = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/back_office_topbar.php');
        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/views/layout/main.php');
        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $svc = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Services/Portal/BackOfficeSearchService.php');
        $users = (string) file_get_contents(dirname(__DIR__, 2) . '/views/admin/organization/users/index.php');

        self::assertStringContainsString('Pages, membres, documents', $topbar);
        self::assertStringContainsString('back_office_search.php', $layout);
        self::assertStringContainsString('back-office-search.js', $layout);
        self::assertStringContainsString('/api/back-office/search', $routes);
        self::assertStringContainsString('filterPages', $svc);
        self::assertStringContainsString('searchPersonnel', $svc);
        self::assertStringContainsString('Poste de situation', $svc);
        self::assertStringContainsString('Annonces et alertes', $svc);
        self::assertStringContainsString('name="search"', $users);
    }
}
