<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LoginWelcomeViewAssetTest extends TestCase
{
    public function testWelcomeViewAndRoutesExist(): void
    {
        $view = dirname(__DIR__, 2) . '/views/auth/welcome.php';
        $this->assertFileExists($view);
        $src = (string) file_get_contents($view);
        $this->assertStringContainsString("welcome_enter_brand", $src);
        $this->assertStringContainsString('account-facts', $src);
        $this->assertStringNotContainsString('unitLabel', $src);
        $this->assertStringContainsString("welcome_continue_hint", $src);
        $this->assertStringContainsString('toLocaleTimeString(browserLocale', $src);
        $this->assertStringContainsString('welcome_opening', $src);
        $this->assertStringContainsString('Archivo', $src);
        $this->assertStringContainsString('lock-slides', $src);
        $this->assertStringContainsString('LoginAccueilImageStorage::defaultPublicUrl', $src);

        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $this->assertStringContainsString("'/login/accueil'", $routes);
        $this->assertStringContainsString('showWelcome', $routes);
        $this->assertStringContainsString('enterWelcome', $routes);
    }

    public function testWelcomeTranslationsExistInBothLocales(): void
    {
        $root = dirname(__DIR__, 2);
        $fr = require $root . '/lang/fr/auth.php';
        $en = require $root . '/lang/en/auth.php';
        foreach ([
            'welcome_title',
            'welcome_screen_aria',
            'welcome_continue_hint',
            'welcome_enter_brand',
            'welcome_profile_ready',
            'welcome_opening',
            'welcome_fact_seniority',
            'welcome_fact_role',
            'welcome_fact_assignment',
            'welcome_not_provided',
            'welcome_years_months',
        ] as $key) {
            self::assertArrayHasKey($key, $fr);
            self::assertArrayHasKey($key, $en);
            self::assertNotSame($fr[$key], $en[$key], $key);
        }
    }

    public function testGateAndServiceClassesExist(): void
    {
        $this->assertTrue(class_exists(\App\Support\LoginWelcomeGate::class));
        $this->assertTrue(class_exists(\App\Services\Auth\LoginWelcomeProfileService::class));
    }
}
