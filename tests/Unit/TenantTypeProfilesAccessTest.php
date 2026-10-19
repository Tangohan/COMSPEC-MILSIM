<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\TenantTypeConfig;
use PHPUnit\Framework\TestCase;

final class TenantTypeProfilesAccessTest extends TestCase
{
    /**
     * Pages et API qui échappaient au filtre de profil (ouvertes à une communauté « Carte ATAK »).
     *
     * @return array<string, array{string, string}>
     */
    public static function moduleUris(): array
    {
        return [
            'API formations' => ['api/training/courses', 'training'],
            'admin formations' => ['admin/training/courses', 'training'],
            'admin candidatures' => ['admin/recruitments', 'recruitment'],
            'corrections dossiers' => ['back-office/personnel/corrections', 'personnel'],
            'avancement RH' => ['back-office/rh/avancement', 'personnel'],
            'unités' => ['back-office/units/create', 'personnel'],
            'intégration' => ['back-office/integration-membres', 'personnel'],
            'ma fiche' => ['back-office/ma-situation/ma-fiche', 'personnel'],
            'documents BO' => ['back-office/documents/types', 'documents'],
            'événements BO' => ['back-office/evenements', 'operations'],
            'API opérations' => ['api/operations/missions', 'operations'],
            'pointage' => ['pointage', 'operations'],
            'missions inter-unités' => ['admin/interteam-missions', 'cooperation'],
            'arsenal' => ['equipment', 'equipment'],
            'liaison ATAK perso' => ['back-office/ma-situation/liaison-atak', 'atak'],
        ];
    }

    /**
     * @dataProvider moduleUris
     */
    public function testUriIsMappedToItsModule(string $uri, string $module): void
    {
        self::assertSame($module, TenantTypeConfig::moduleForUri($uri));
    }

    public function testAtakProfileKeepsMapForumAdminAndEquipmentOnly(): void
    {
        foreach (['atak', 'back-office/atak/fire-teams', 'forum', 'back-office/invitations', 'equipment', 'documentation/profils', 'account'] as $uri) {
            self::assertTrue(TenantTypeConfig::uriAllowed('atak', $uri), $uri);
        }
        foreach (['api/training/courses', 'back-office/recruitments', 'personnel', 'back-office/evenements', 'admin/training'] as $uri) {
            self::assertFalse(TenantTypeConfig::uriAllowed('atak', $uri), $uri);
        }
        self::assertTrue(TenantTypeConfig::uriAllowed('effectifs', 'back-office/personnel/corrections'));
        self::assertFalse(TenantTypeConfig::uriAllowed('effectifs', 'formation'));
    }

    public function testMembersNeverReceiveAdministrationFromTheProfile(): void
    {
        foreach (['atak', 'effectifs'] as $type) {
            $member = TenantTypeConfig::basePermissionSlugsForLevel($type, 'member');
            foreach (['admin.access', 'invitations.send', 'atak.manage', 'personnel.manage', 'personnel.edit'] as $slug) {
                self::assertNotContains($slug, $member, $type . ' ' . $slug);
            }
            self::assertContains('forum.view', $member);
            self::assertContains('atak.access', $member);
            self::assertContains('admin.access', TenantTypeConfig::basePermissionSlugsForLevel($type, 'manager'));
            self::assertNotContains('admin.access', TenantTypeConfig::basePermissionSlugsForLevel($type, 'hr'));
        }
        self::assertContains('personnel.edit', TenantTypeConfig::basePermissionSlugsForLevel('effectifs', 'hr'));
    }

    public function testEveryTutorialStepPointsInsideItsProfile(): void
    {
        foreach (['full', 'effectifs', 'atak'] as $type) {
            $guide = TenantTypeConfig::profileGuide($type);
            self::assertNotEmpty($guide['steps']);
            self::assertSame(['member', 'hr', 'manager'], array_keys($guide['levels']));
            foreach ($guide['steps'] as $step) {
                self::assertTrue(TenantTypeConfig::uriAllowed($type, $step['path']), $type . ' → ' . $step['path']);
                self::assertContains($step['audience'], ['member', 'hr', 'manager']);
            }
        }
    }

    public function testCatalogCoversEveryAllowedModule(): void
    {
        $catalog = TenantTypeConfig::moduleCatalog();
        foreach (TenantTypeConfig::allowedModulesByType() as $modules) {
            foreach ($modules as $m) {
                self::assertArrayHasKey($m, $catalog);
            }
        }
        self::assertSame(['Complet'], TenantTypeConfig::profilesIncludingModule('training'));
    }
}
