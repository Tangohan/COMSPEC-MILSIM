<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Community\TenantTypeConfig;
use App\Services\Platform\ModuleAccessDecision;
use App\Services\Platform\ModuleAccessService;
use PHPUnit\Framework\TestCase;

/** Audit du back-office, lot 4.4 : « module ouvert ? » = type de communauté puis formule, avec la raison du refus. */
final class ModuleAccessServiceTest extends TestCase
{
    public function testTypeThatIncludesTheModuleIsOpen(): void
    {
        $decision = (new ModuleAccessService(static fn (): bool => true))->check(TenantTypeConfig::TYPE_FULL, 'training', 3, 'training');
        self::assertTrue($decision->allowed);
        self::assertNull($decision->reason);
        self::assertSame('training', $decision->module);
    }

    public function testTypeWithoutTheModuleIsRefusedWithItsReason(): void
    {
        $called = false;
        $service = new ModuleAccessService(static function () use (&$called): bool {
            $called = true;

            return true;
        });
        $decision = $service->check(TenantTypeConfig::TYPE_EFFECTIFS, 'training', 3, 'training');
        self::assertFalse($decision->allowed);
        self::assertSame(ModuleAccessDecision::REASON_COMMUNITY_TYPE, $decision->reason);
        self::assertStringContainsString('« Formations »', $decision->message);
        self::assertStringContainsString('profil « ' . TenantTypeConfig::label(TenantTypeConfig::TYPE_EFFECTIFS) . ' »', $decision->message);
        self::assertFalse($called, 'le type refuse avant que la formule soit lue');
    }

    public function testPlanRefusalCarriesItsOwnReason(): void
    {
        $asked = [];
        $service = new ModuleAccessService(static function (int $tenantId, string $feature) use (&$asked): bool {
            $asked[] = [$tenantId, $feature];

            return false;
        });
        $decision = $service->check(TenantTypeConfig::TYPE_FULL, 'training', 3, 'training');
        self::assertFalse($decision->allowed);
        self::assertSame(ModuleAccessDecision::REASON_PLAN, $decision->reason);
        self::assertStringContainsString('formule', $decision->message);
        self::assertSame([[3, 'training']], $asked);
        self::assertFalse($service->isOpen(TenantTypeConfig::TYPE_FULL, 'training', 3, 'training'));
    }

    public function testWithoutCommunityOrFeatureOnlyTheTypeIsChecked(): void
    {
        $service = new ModuleAccessService(static fn (): bool => false);
        self::assertTrue($service->isOpen(TenantTypeConfig::TYPE_ATAK, 'atak'));
        self::assertTrue($service->isOpen(TenantTypeConfig::TYPE_ATAK, 'atak', 3));
        self::assertTrue($service->isOpen(TenantTypeConfig::TYPE_ATAK, 'atak', null, 'atak'));
        self::assertFalse($service->isOpen(TenantTypeConfig::TYPE_ATAK, 'personnel'));
    }

    public function testEmptyModuleIsNeverRestrictedByType(): void
    {
        self::assertTrue((new ModuleAccessService())->isOpen(TenantTypeConfig::TYPE_ATAK, ''));
    }

    /** Même réponse que TenantTypeConfig pour tous les types et tous les modules. */
    public function testTypeAnswerMatchesTenantTypeConfig(): void
    {
        $service = new ModuleAccessService(static fn (): bool => true);
        foreach ([TenantTypeConfig::TYPE_FULL, TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_ATAK, 'inconnu'] as $type) {
            foreach (array_keys(TenantTypeConfig::moduleCatalog()) as $module) {
                self::assertSame(TenantTypeConfig::moduleAllowed($type, $module), $service->isOpen($type, $module), $type . '/' . $module);
            }
        }
    }

    /** Le message de refus du middleware n'a pas changé : texte identique à l'ancien calcul. */
    public function testTypeRefusalMessageIsTheMiddlewareMessage(): void
    {
        foreach ([TenantTypeConfig::TYPE_EFFECTIFS, TenantTypeConfig::TYPE_ATAK] as $type) {
            foreach (array_keys(TenantTypeConfig::moduleCatalog()) as $module) {
                $label = TenantTypeConfig::label($type);
                $moduleLabel = (string) (TenantTypeConfig::moduleCatalog()[$module]['label'] ?? '');
                $expected = ($moduleLabel !== '' ? '« ' . $moduleLabel . ' » n’est' : 'Cette fonctionnalité n’est')
                    . ' pas incluse dans le profil « ' . $label . ' » de votre communauté.';
                $including = array_values(array_diff(TenantTypeConfig::profilesIncludingModule($module), [$label]));
                if ($including !== []) {
                    $expected .= ' Elle fait partie du profil « ' . implode(' » ou « ', $including) . ' » : un Gestionnaire peut changer de profil dans Paramètres → Profil.';
                }
                self::assertSame($expected, ModuleAccessService::typeRefusalMessage($type, $module), $type . '/' . $module);
            }
        }
        self::assertStringContainsString('Cette fonctionnalité n’est', ModuleAccessService::typeRefusalMessage(TenantTypeConfig::TYPE_ATAK, ''));
    }

    public function testMiddlewareAndHomeUseTheService(): void
    {
        $root = dirname(__DIR__, 2);
        $mw = (string) file_get_contents($root . '/app/Middleware/TenantTypeModuleAccessMiddleware.php');
        self::assertStringContainsString('ModuleAccessService::typeRefusalMessage($tenantType, $module)', $mw);
        self::assertStringNotContainsString('profilesIncludingModule', $mw);
        $home = (string) file_get_contents($root . '/app/Controllers/Web/HomeController.php');
        self::assertStringContainsString("->isOpen(\$dashboardTenantType, 'training', \$tid, 'training')", $home);
        self::assertStringContainsString("->isOpen(\$dashboardTenantType, 'equipment', \$tid, 'equipment')", $home);
    }
}
