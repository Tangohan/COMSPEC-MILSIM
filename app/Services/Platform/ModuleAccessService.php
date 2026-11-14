<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Core\Container;
use App\Services\Community\TenantTypeConfig;
use Closure;

/**
 * « Ce module est-il ouvert pour cette communauté ? » : un seul endroit pour les deux filtres cumulatifs,
 * le type de communauté (TenantTypeConfig, vocabulaire des modules) et la formule (FeatureGateService, clés de formule),
 * avec la raison du refus.
 *
 * Ordre identique à celui de l'application : le type de communauté d'abord (TenantTypeModuleAccessMiddleware), la formule ensuite.
 * Le service ne remplace aucun des deux : il les appelle.
 */
final class ModuleAccessService
{
    /**
     * @param Closure(int, string): bool|null $planAllows Remplace FeatureGateService::allows (tests)
     */
    public function __construct(private ?Closure $planAllows = null) {}

    /**
     * @param string $tenantType Type effectif de la communauté (EffectiveTenantType::resolve pour l'affichage, resolveForGuard pour refuser)
     * @param string $module Module de TenantTypeConfig (« training », « recruitment »…) ; vide = jamais restreint
     * @param int|null $tenantId Communauté dont on lit la formule ; sans elle, seul le type est contrôlé
     * @param string|null $feature Clé de formule (FeatureGateService::allows) ; sans elle, seul le type est contrôlé
     */
    public function check(string $tenantType, string $module, ?int $tenantId = null, ?string $feature = null): ModuleAccessDecision
    {
        $module = strtolower(trim($module));
        if (!TenantTypeConfig::moduleAllowed($tenantType, $module)) {
            return ModuleAccessDecision::refused(
                ModuleAccessDecision::REASON_COMMUNITY_TYPE,
                $module,
                self::typeRefusalMessage($tenantType, $module)
            );
        }
        if ($tenantId !== null && $tenantId > 0 && $feature !== null && $feature !== '' && !$this->formulaAllows($tenantId, $feature)) {
            return ModuleAccessDecision::refused(
                ModuleAccessDecision::REASON_PLAN,
                $module,
                self::planRefusalMessage($module)
            );
        }

        return ModuleAccessDecision::open($module);
    }

    public function isOpen(string $tenantType, string $module, ?int $tenantId = null, ?string $feature = null): bool
    {
        return $this->check($tenantType, $module, $tenantId, $feature)->allowed;
    }

    /**
     * Message d'un module absent du profil de la communauté (repris tel quel par TenantTypeModuleAccessMiddleware).
     */
    public static function typeRefusalMessage(string $tenantType, string $module): string
    {
        $label = TenantTypeConfig::label($tenantType);
        $moduleLabel = (string) (TenantTypeConfig::moduleCatalog()[$module]['label'] ?? '');
        $message = ($moduleLabel !== '' ? '« ' . $moduleLabel . ' » n’est' : 'Cette fonctionnalité n’est')
            . ' pas incluse dans le profil « ' . $label . ' » de votre communauté.';
        $including = array_values(array_diff(TenantTypeConfig::profilesIncludingModule($module), [$label]));
        if ($including !== []) {
            $message .= ' Elle fait partie du profil « ' . implode(' » ou « ', $including) . ' » : un Gestionnaire peut changer de profil dans Paramètres → Profil.';
        }

        return $message;
    }

    public static function planRefusalMessage(string $module): string
    {
        $moduleLabel = (string) (TenantTypeConfig::moduleCatalog()[$module]['label'] ?? '');

        return ($moduleLabel !== '' ? '« ' . $moduleLabel . ' » n’est' : 'Cette fonctionnalité n’est')
            . ' pas incluse dans la formule actuelle de votre communauté.';
    }

    private function formulaAllows(int $tenantId, string $feature): bool
    {
        if ($this->planAllows !== null) {
            return (bool) ($this->planAllows)($tenantId, $feature);
        }

        return Container::get(FeatureGateService::class)->allows($tenantId, $feature);
    }
}
