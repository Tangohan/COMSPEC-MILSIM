<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Gate;
use App\Repositories\TenantAdminSettingsRepository;
use App\Repositories\TenantRepository;
use App\Services\Game\FirstLoginGameLinkGate;
use PHPUnit\Framework\TestCase;

/** Première connexion : un nouveau membre relie son jeu (code ATAK sur /lier) avant d'ouvrir le site. */
final class FirstLoginGameLinkGateTest extends TestCase
{
    protected function tearDown(): void
    {
        Gate::reset();
    }

    /** @param array<string, mixed> $settings */
    private function gate(array $settings = [], string $slug = 'unite'): FirstLoginGameLinkGate
    {
        $tenants = new class ($settings, $slug) extends TenantRepository {
            /** @param array<string, mixed> $settings */
            public function __construct(private array $settings, private string $slug)
            {
                parent::__construct(null);
            }

            public function findById(int $id): ?array
            {
                return ['id' => $id, 'slug' => $this->slug, 'settings' => json_encode($this->settings)];
            }
        };

        return new FirstLoginGameLinkGate(new TenantAdminSettingsRepository($tenants), $tenants);
    }

    /** @return array<string, mixed> */
    private function newMember(array $extra = []): array
    {
        return array_merge(['id' => 12, 'steam_id' => '', 'created_at' => '2026-11-02 18:00:00'], $extra);
    }

    public function testNewMemberWithoutGameIsHeldByDefault(): void
    {
        self::assertTrue($this->gate()->mustLink($this->newMember(), 4));
    }

    public function testLinkedGameOldMemberOrDefaultCommunityPass(): void
    {
        self::assertFalse($this->gate()->mustLink($this->newMember(['steam_id' => '76561198000000001']), 4));
        self::assertFalse($this->gate()->mustLink($this->newMember(['created_at' => '2025-03-01 10:00:00']), 4));
        self::assertFalse($this->gate([], 'default')->mustLink($this->newMember(), 1));
        self::assertFalse($this->gate()->mustLink($this->newMember(), 0));
    }

    public function testCommunityCanTurnItOff(): void
    {
        $off = ['admin_runtime' => ['atak_defaults' => ['require_game_link_first_login' => false]]];
        self::assertFalse($this->gate($off)->mustLink($this->newMember(), 4));
        self::assertTrue((new TenantAdminSettingsRepository())->defaults()['atak_defaults']['require_game_link_first_login']);
    }

    public function testCommunityAdminsAreNeverHeld(): void
    {
        Gate::getInstance()->setPermissions(['admin.organization']);
        self::assertFalse($this->gate()->mustLink($this->newMember(), 4));
    }

    public function testLinkPagesStayOpen(): void
    {
        foreach (['/lier', '/lier/etat', '/logout', '/atak/mod', '/atak/premiere-liaison', '/locale/en', '/api/atak/version'] as $path) {
            self::assertTrue(FirstLoginGameLinkGate::pathIsOpen($path), $path);
        }
        foreach (['/dashboard', '/atak', '/back-office', '/liens', '/account/preferences'] as $path) {
            self::assertFalse(FirstLoginGameLinkGate::pathIsOpen($path), $path);
        }
    }

    public function testWiredIntoAuthAndLinkPage(): void
    {
        $root = dirname(__DIR__, 2);
        $auth = (string) file_get_contents($root . '/app/Middleware/AuthMiddleware.php');
        $ctrl = (string) file_get_contents($root . '/app/Controllers/Web/AtakQuickLinkController.php');
        $view = (string) file_get_contents($root . '/views/link/lier.php');
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('FirstLoginGameLinkGate', $auth);
        self::assertStringContainsString("Response::redirect(url('lier'))", $auth);
        self::assertStringContainsString('FirstLoginGameLinkGate::markLinked', $ctrl);
        self::assertStringContainsString('Dernière étape : reliez votre jeu', $view);
        self::assertStringContainsString('Accéder au site', $view);
        self::assertStringContainsString("'/back-office/ressources/atak-config/first-login-link'", $routes);
        $en = require $root . '/lang/en/site_auto.php';
        self::assertSame('Last step: link your game', $en['Dernière étape : reliez votre jeu'] ?? null);
    }
}
