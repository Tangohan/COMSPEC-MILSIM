<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Audit\AuditAction;
use App\Support\PlatformStorageCatalog;
use PHPUnit\Framework\TestCase;

final class PlatformStorageAdminAssetTest extends TestCase
{
    public function testPurgeGroupsNeverIncludeAccountsOrCommunities(): void
    {
        $protected = PlatformStorageCatalog::protectedTables();
        self::assertContains('users', $protected);
        self::assertContains('tenants', $protected);
        self::assertContains('audit_logs', $protected);

        $tables = [];
        foreach (PlatformStorageCatalog::purgeGroups() as $group) {
            foreach ($group['tables'] as $name) {
                $tables[] = $name;
                self::assertNotContains($name, $protected, $name);
            }
            self::assertNotSame('', (string) $group['title']);
            self::assertDoesNotMatchRegularExpression('/\b(sql|json|endpoint)\b/i', (string) $group['blurb']);
        }

        self::assertContains('atak_unit_motion_samples', $tables);
        self::assertContains('atak_terrain_chunks', $tables);
        self::assertSame('VIDER', PlatformStorageCatalog::CONFIRM_WORD);
        self::assertNotNull(PlatformStorageCatalog::groupByKey('atak_terrain'));
        self::assertNull(PlatformStorageCatalog::groupByKey('users'));
    }

    public function testAdminPageIsPlatformOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $view = (string) file_get_contents($root . '/views/admin/system/storage.php');
        $side = (string) file_get_contents($root . '/views/partials/platform_admin_sidebar.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemStorageController.php');

        self::assertStringContainsString("get('/admin/system/storage'", $routes);
        self::assertStringContainsString('SystemAdminMiddleware::class', $routes);
        self::assertStringContainsString('Espace disque et historiques', $view);
        self::assertStringContainsString('toutes les communautés', strtolower($view));
        self::assertStringContainsString('VIDER', $view);
        self::assertStringContainsString('admin/system/storage', $side);
        self::assertStringContainsString('AuditAction::PLATFORM_STORAGE_PURGED', $ctrl);
        self::assertSame('platform.storage_purged', AuditAction::PLATFORM_STORAGE_PURGED);
        self::assertStringNotContainsString('endpoint', $view);
    }
}
