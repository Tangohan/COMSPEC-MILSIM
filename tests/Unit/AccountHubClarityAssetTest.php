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

        self::assertStringContainsString('Connexion, photo et préférences', $index);
        self::assertStringContainsString('accountHasPortrait', $index);
        self::assertStringContainsString('url(\'account/portrait\')', $index);
        self::assertStringContainsString('Où aller', $index);
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

        self::assertStringContainsString("return Response::redirect(url('account/portrait'));", $ctrl);
        self::assertStringContainsString('accountHasPortrait', $ctrl);
        self::assertStringNotContainsString("'systemHealth'", $ctrl);

        self::assertStringContainsString('Une seule photo', $portrait);
        self::assertStringNotContainsString('Distincte de la photo de compte', $portrait);
        self::assertStringNotContainsString('Photo de compte', $banner);
        self::assertStringNotContainsString('account/image', $banner);
    }
}
