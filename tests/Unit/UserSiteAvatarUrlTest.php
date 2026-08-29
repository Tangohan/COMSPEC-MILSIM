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

    public function testDefaultsToOperatorPortraitWithAccountFallback(): void
    {
        $url = user_site_avatar_url(
            ['avatar_url' => 'uploads/avatars/account.jpg'],
            ['character_portrait_path' => 'uploads/portraits/op.jpg'],
            null
        );
        self::assertNotNull($url);
        self::assertStringContainsString('uploads/portraits/op.jpg', (string) $url);
    }

    public function testAccountPriorityUsesAccountAvatar(): void
    {
        $url = user_site_avatar_url(
            ['avatar_url' => 'uploads/avatars/account.jpg'],
            ['character_portrait_path' => 'uploads/portraits/op.jpg'],
            ['site_photo_priority' => 'account']
        );
        self::assertNotNull($url);
        self::assertStringContainsString('uploads/avatars/account.jpg', (string) $url);
    }

    public function testFallsBackWhenPreferredMissing(): void
    {
        $url = user_site_avatar_url(
            ['avatar_url' => 'uploads/avatars/account.jpg'],
            ['character_portrait_path' => ''],
            ['site_photo_priority' => 'operator']
        );
        self::assertNotNull($url);
        self::assertStringContainsString('uploads/avatars/account.jpg', (string) $url);
    }

    public function testReturnsNullWhenNoPhotos(): void
    {
        self::assertNull(user_site_avatar_url(['avatar_url' => ''], null, ['site_photo_priority' => 'operator']));
        self::assertNull(user_site_avatar_url(null, null, null));
    }

    public function testInvalidPriorityTreatedAsOperator(): void
    {
        $url = user_site_avatar_url(
            ['avatar_url' => 'uploads/avatars/account.jpg'],
            ['character_portrait_path' => 'uploads/portraits/op.jpg'],
            ['site_photo_priority' => 'weird']
        );
        self::assertNotNull($url);
        self::assertStringContainsString('uploads/portraits/op.jpg', (string) $url);
    }

    public function testPreferencesViewOffersPhotoPriorityControl(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 2) . '/views/account/preferences.php');
        self::assertStringContainsString('name="site_photo_priority"', $view);
        self::assertStringContainsString('value="operator"', $view);
        self::assertStringContainsString('value="account"', $view);
        self::assertStringContainsString('Photo affichée sur le site', $view);
    }

    public function testHeaderUsesSiteAvatarHelper(): void
    {
        $header = (string) file_get_contents(dirname(__DIR__, 2) . '/views/partials/athena_caverne_header.php');
        self::assertStringContainsString('user_site_avatar_url', $header);
        self::assertStringContainsString('site_photo_priority', $header);
    }
}
