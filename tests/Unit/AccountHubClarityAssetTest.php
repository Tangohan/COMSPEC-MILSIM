<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AccountHubClarityAssetTest extends TestCase
{
    public function testAccountOverviewDropsDeadPhotoAndPortalShortcuts(): void
    {
        $root = dirname(__DIR__, 2);
        $index = (string) file_get_contents($root . '/views/account/index.php');
        $nav = (string) file_get_contents($root . '/views/partials/account/shell_open.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/AccountController.php');
        $portrait = (string) file_get_contents($root . '/views/account/portrait.php');
        $banner = (string) file_get_contents($root . '/views/account/banner.php');

        self::assertStringContainsString('Connexion, sécurité et préférences', $index);
        self::assertStringContainsString('accountHasPortrait', $index);
        self::assertStringContainsString('url(\'account/portrait\')', $index);
        self::assertStringContainsString('État du compte', $index);
        self::assertStringContainsString('Activité récente', $index);
        self::assertStringContainsString("url('account/sessions/fermer-les-autres')", $index);
        self::assertStringNotContainsString('Où aller', $index);
        self::assertStringNotContainsString('Photo de compte', $index);
        self::assertStringNotContainsString('État des services', $index);
        self::assertStringNotContainsString('Images du compte', $index);
        self::assertStringNotContainsString('systemHealth', $index);
        self::assertStringNotContainsString('Charte des formations', $index);

        self::assertStringContainsString("'label' => 'Portrait'", $nav);
        self::assertStringContainsString("'label' => 'Appareils ATAK'", $nav);
        self::assertStringNotContainsString('Photo de compte', $nav);
        self::assertStringNotContainsString('Terminaux ATAK', $nav);
        self::assertStringNotContainsString('Appareils liés', $nav);
        self::assertStringNotContainsString('Notifications e-mail', $nav);
        self::assertStringNotContainsString("'key' => 'image'", $nav);
        self::assertStringNotContainsString("'key' => 'leave'", $nav);
        self::assertStringContainsString('OperatorPortraits::forUser', $nav);
        self::assertStringNotContainsString('user_site_avatar_url', $nav);

        self::assertStringContainsString("return Response::redirect(url('account/portrait'));", $ctrl);
        self::assertStringContainsString('accountHasPortrait', $ctrl);
        self::assertStringNotContainsString("'systemHealth'", $ctrl);

        self::assertStringContainsString('Une seule photo', $portrait);
        self::assertStringNotContainsString('Distincte de la photo de compte', $portrait);
        self::assertStringNotContainsString('Photo de compte', $banner);
        self::assertStringNotContainsString('account/image', $banner);
    }

    public function testDeadAccountViewsAndDuplicatesAreGone(): void
    {
        $root = dirname(__DIR__, 2);
        $prefs = (string) file_get_contents($root . '/views/account/preferences.php');

        self::assertFileDoesNotExist($root . '/views/account/atak_devices.php');
        self::assertFileDoesNotExist($root . '/views/account/image.php');
        self::assertStringNotContainsString('Compte actuel', $prefs);
        self::assertStringNotContainsString('id="connexion-verification"', $prefs);
        self::assertStringNotContainsString('name="ui_sidebar_collapsed"', $prefs);
        self::assertStringContainsString('athena.bo.theme', $prefs);
    }

    public function testOtherSessionsCanBeClosed(): void
    {
        $root = dirname(__DIR__, 2);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        $mw = (string) file_get_contents($root . '/app/Middleware/AuthMiddleware.php');
        $auth = (string) file_get_contents($root . '/app/Services/Auth/AuthService.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/AccountController.php');

        self::assertStringContainsString("/account/sessions/fermer-les-autres', [AccountController::class, 'revokeOtherSessions']", $routes);
        self::assertStringContainsString("session_epoch", $mw);
        self::assertStringContainsString("Session::set('auth_issued_at', time());", $auth);
        self::assertStringContainsString('AUTH_PASSWORD_CHANGED', $ctrl);
        self::assertFileExists($root . '/bootstrap/account_session_epoch_migration.php');
    }
}
