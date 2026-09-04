<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Auth\LoginAccueilBackgroundService;
use App\Support\LoginAccueilImageStorage;
use PHPUnit\Framework\TestCase;

final class LoginAccueilImageAssetTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testDefaultImageAndWelcomeSlideshowExist(): void
    {
        $root = $this->root();
        $image = $root . '/public/assets/images/login-accueil-nvg-forest.jpg';
        self::assertFileExists($image);
        $head = (string) file_get_contents($image, false, null, 0, 3);
        self::assertSame("\xFF\xD8\xFF", $head);

        $view = (string) file_get_contents($root . '/views/auth/welcome.php');
        self::assertStringContainsString('lock-slides', $view);
        self::assertStringContainsString('lock-slide', $view);
        self::assertStringContainsString('prefers-reduced-motion', $view);
        self::assertStringContainsString('LoginAccueilImageStorage::defaultPublicUrl', $view);
        self::assertStringNotContainsString('WES_Operator_V2_re_05.jpg', $view);
        self::assertStringNotContainsString('rotate(', $view);

        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/login/accueil'", $routes);
        self::assertStringContainsString("'/login/accueil/fond/{tenantId}/{file}'", $routes);
        self::assertStringContainsString('streamWelcomeBackground', $routes);
        self::assertStringContainsString("'/back-office/organisation/parametres/accueil-connexion'", $routes);
        self::assertStringContainsString('storeLoginAccueilImage', $routes);
    }

    public function testSettingsUiUsesHumanLabels(): void
    {
        $root = $this->root();
        $settings = (string) file_get_contents($root . '/views/admin/organization/settings.php');
        $start = strpos($settings, 'id="accueil-connexion"');
        self::assertNotFalse($start);
        $end = strpos($settings, 'id="bo-community-settings-form"');
        self::assertNotFalse($end);
        $section = substr($settings, $start, $end - $start);
        self::assertStringContainsString('Images d’accueil', $section);
        self::assertStringContainsString('Ajouter une image', $section);
        self::assertStringContainsString('Défiler automatiquement les images', $section);
        self::assertStringContainsString('login_accueil_images[]', $section);
        self::assertStringNotContainsString('slug', strtolower($section));
        self::assertStringNotContainsString('JSON', $section);
        self::assertStringNotContainsString('endpoint', $section);
    }

    public function testConfigurationUpdateIsDeclared(): void
    {
        $root = $this->root();
        $catalog = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateCatalog.php');
        $probe = (string) file_get_contents($root . '/app/Services/ConfigurationUpdate/ConfigurationUpdateProbes.php');
        $seed = (string) file_get_contents($root . '/bootstrap/configuration_updates_migration.php');
        $bootstrap = (string) file_get_contents($root . '/app/Services/Community/TenantBootstrapService.php');
        $run = (string) file_get_contents($root . '/run-migrations.php');
        self::assertStringContainsString('LOGIN_ACCUEIL_IMAGES_V1', $catalog);
        self::assertStringContainsString('hasLoginAccueilImage', $probe);
        self::assertStringContainsString('LOGIN_ACCUEIL_IMAGES_V1', $seed);
        self::assertStringContainsString('back-office/organisation/parametres#accueil-connexion', $catalog);
        self::assertStringContainsString("markCompleted(\$tenantId, 'LOGIN_ACCUEIL_IMAGES_V1'", $bootstrap);
        self::assertStringContainsString('tenant_login_accueil_images_migration', $run);
        self::assertFileExists($root . '/bootstrap/tenant_login_accueil_images_migration.php');
        self::assertFileExists($root . '/migrations/20260902190000_tenant_login_accueil_images.sql');
    }

    public function testStorageRejectsUnsafeNamesAndCrossTenant(): void
    {
        self::assertTrue(LoginAccueilImageStorage::isSafeFileName('a-abc123.jpg'));
        self::assertTrue(LoginAccueilImageStorage::isSafeFileName('a_1.webp'));
        self::assertFalse(LoginAccueilImageStorage::isSafeFileName('../x.jpg'));
        self::assertFalse(LoginAccueilImageStorage::isSafeFileName('a/b.jpg'));
        self::assertFalse(LoginAccueilImageStorage::isSafeFileName(''));
        $cross = LoginAccueilImageStorage::stream(1, 2, 'a.jpg');
        self::assertSame(404, $cross->statusCode());
        $missing = LoginAccueilImageStorage::stream(1, 1, 'a-missing.jpg');
        self::assertSame(404, $missing->statusCode());
    }

    public function testBackgroundFallsBackToPlatformDefault(): void
    {
        $repo = $this->createMock(\App\Repositories\TenantLoginAccueilImageRepository::class);
        $repo->expects(self::never())->method('listForTenant');
        $svc = new LoginAccueilBackgroundService($repo);
        $out = $svc->forTenant(0, null);
        self::assertTrue($out['is_default']);
        self::assertFalse($out['rotate']);
        self::assertCount(1, $out['urls']);
        self::assertStringContainsString('login-accueil-nvg-forest.jpg', $out['urls'][0]);
        self::assertTrue($svc->slideshowEnabled(null));
        self::assertFalse($svc->slideshowEnabled(['login_accueil_slideshow' => false]));
        self::assertTrue($svc->slideshowEnabled(['login_accueil_slideshow' => '1']));
    }
}
