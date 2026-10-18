<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Account\PlatformAccountBanService;
use PHPUnit\Framework\TestCase;

final class PlatformAccountBanAssetTest extends TestCase
{
    public function testBanIpAndMembershipActionsAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        foreach (['/admin/users/bannir', '/admin/users/lever-bannissement', '/admin/users/bloquer-ip', '/admin/users/retirer-communaute', '/admin/users/reintegrer-communaute'] as $path) {
            self::assertStringContainsString("'" . $path . "'", $routes);
        }
        self::assertStringContainsString('platform_account_bans_migration.php', (string) file_get_contents($root . '/run-migrations.php'));

        $ctrl = (string) file_get_contents($root . '/app/Controllers/Admin/System/SystemUsersController.php');
        self::assertStringContainsString('function removeMembership', $ctrl);
        self::assertStringContainsString('function restoreMembership', $ctrl);
        self::assertStringContainsString('personKnownIps', $ctrl);

        $list = (string) file_get_contents($root . '/views/admin/system/users.php');
        self::assertStringContainsString('Bannir du site', $list);
        self::assertStringContainsString('Lever le bannissement', $list);
        self::assertStringContainsString('Réintégrer', $list);
        self::assertStringContainsString('Sans communauté', $list);
        self::assertStringNotContainsString('pas d’organisation »</option>', $list);

        $person = (string) file_get_contents($root . '/views/admin/system/user_person.php');
        self::assertStringContainsString('id="reseau"', $person);
        self::assertStringContainsString('admin/users/bloquer-ip', $person);
        self::assertStringContainsString('id="bannissement"', $person);
        self::assertStringNotContainsString('Tenant système', $person);

        $register = (string) file_get_contents($root . '/app/Controllers/Web/RegisterController.php');
        self::assertStringContainsString('isIpBlockedForLogin', $register);
    }

    public function testCleanIpsKeepsOnlyValidUniqueAddresses(): void
    {
        self::assertSame(
            ['1.2.3.4', '2001:db8::1', '5.6.7.8'],
            PlatformAccountBanService::cleanIps(['1.2.3.4, 2001:db8::1', 'pas une ip', '1.2.3.4;5.6.7.8'])
        );
    }

    public function testNameFilterCanEditRenameAndPointToBan(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('/admin/system/noms-inappropries/mots/{id}/modifier', $routes);
        self::assertStringContainsString('/admin/system/noms-inappropries/alertes/{id}/renommer', $routes);
        $view = (string) file_get_contents($root . '/views/admin/system/name_filter.php');
        self::assertStringContainsString('Renommer…', $view);
        self::assertStringContainsString('Bannir l’auteur…', $view);
        self::assertStringContainsString("/modifier')", $view);
    }
}
