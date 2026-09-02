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
        $this->assertStringContainsString('Entrer dans', $src);
        $this->assertStringContainsString('account-facts', $src);
        $this->assertStringNotContainsString('unitLabel', $src);
        $this->assertStringContainsString('Appuyez sur Entrée', $src);
        $this->assertStringContainsString('Archivo', $src);
        $this->assertStringContainsString('lock-slides', $src);
        $this->assertStringContainsString('LoginAccueilImageStorage::defaultPublicUrl', $src);

        $routes = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $this->assertStringContainsString("'/login/accueil'", $routes);
        $this->assertStringContainsString('showWelcome', $routes);
        $this->assertStringContainsString('enterWelcome', $routes);
    }

    public function testGateAndServiceClassesExist(): void
    {
        $this->assertTrue(class_exists(\App\Support\LoginWelcomeGate::class));
        $this->assertTrue(class_exists(\App\Services\Auth\LoginWelcomeProfileService::class));
    }
}
