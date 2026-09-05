<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class UserSiteAvatarUrlTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/app/Support/helpers.php';
    }

    public function testUsesOperatorPortraitExclusively(): void
    {
        $url = user_site_avatar_url(
            ['avatar_url' => 'https://steamcdn.example/account.jpg'],
            ['character_portrait_path' => 'uploads/portraits/op.jpg'],
            ['site_photo_priority' => 'account']
        );
        self::assertStringContainsString('uploads/portraits/op.jpg', (string) $url);
        self::assertStringNotContainsString('steamcdn', (string) $url);
    }

    public function testMissingOperatorPortraitUsesUnknownPhoto(): void
    {
        $url = user_site_avatar_url(['avatar_url' => 'uploads/avatars/account.jpg'], null, null);
        self::assertStringContainsString('assets/images/inconnu.svg', (string) $url);
    }

    public function testPreferencesRequireOperatorPortrait(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/account/preferences.php');
        self::assertStringNotContainsString('name="site_photo_priority"', $view);
        self::assertStringNotContainsString('Synchroniser photo Steam', $view);
        self::assertStringContainsString('Portrait opérateur obligatoire', $view);
    }

    public function testAdministrationCanUploadLockAndNotify(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Admin/Organization/UserAdminController.php');
        self::assertStringContainsString("\$action === 'upload'", $controller);
        self::assertStringContainsString("\$action === 'lock'", $controller);
        self::assertStringContainsString("\$action === 'notify'", $controller);
    }
}
