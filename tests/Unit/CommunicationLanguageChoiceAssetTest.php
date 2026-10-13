<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CommunicationLanguageChoiceAssetTest extends TestCase
{
    public function testCookieBannerOffersLanguageSyncedToProfileAndLogin(): void
    {
        $root = dirname(__DIR__, 2);
        $banner = (string) file_get_contents($root . '/views/partials/cookie_banner.php');
        $locale = (string) file_get_contents($root . '/app/Services/I18n/LocaleService.php');
        $auth = (string) file_get_contents($root . '/app/Services/Auth/AuthService.php');
        $account = (string) file_get_contents($root . '/app/Controllers/Web/AccountController.php');
        $fr = require $root . '/lang/fr/cookies.php';
        $en = require $root . '/lang/en/cookies.php';

        self::assertStringContainsString("locale_switch_url(\$code)", $banner);
        self::assertStringContainsString("cookies.language_title", $banner);
        self::assertArrayHasKey('language_title', $fr);
        self::assertArrayHasKey('language_title', $en);
        self::assertStringContainsString('public function syncAfterLogin(int $userId): void', $locale);
        self::assertStringContainsString('syncAfterLogin((int) $user[\'id\'])', $auth);
        self::assertStringContainsString('setUserLocale($language, false)', $account);
    }
}
