<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Core\Container;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\TenantEmailTemplateRepository;
use App\Repositories\TenantMiniArticleRepository;
use App\Services\Platform\FeatureGateService;

/**
 * Plafonds de création par formule (0 = illimité) pour les clés laissées au propriétaire de la plateforme
 * (SubscriptionPlanFeaturesCatalog::OWNER_DECIDES). On bloque seulement la création d'un nouvel élément : ce qui
 * existe déjà reste lisible, modifiable et supprimable. Clé absente de la formule : illimité.
 */
final class PlanCreationQuota
{
    public const OFFERS = 'max_recruitment_offers';
    public const ARTICLES = 'max_mini_articles';
    public const TEMPLATES = 'max_email_templates';

    /** Éléments encore créables ; null si la formule est illimitée. */
    public static function remainingFor(int $cap, int $used): ?int
    {
        return $cap <= 0 ? null : max(0, $cap - $used);
    }

    /** Phrase d'invitation à changer de formule (jamais une erreur brute). */
    public static function messageFor(string $key, int $cap): string
    {
        return match ($key) {
            self::OFFERS => 'Votre formule est limitée à ' . $cap . ' offres de recrutement en cours (brouillons compris). Clôturez une offre ou passez à une formule supérieure pour en créer davantage.',
            self::ARTICLES => 'Votre formule est limitée à ' . $cap . ' mini-articles. Supprimez un mini-article ou passez à une formule supérieure pour en écrire davantage.',
            self::TEMPLATES => 'Votre formule est limitée à ' . $cap . ' modèles de message personnalisés. Supprimez un modèle ou passez à une formule supérieure pour en créer davantage.',
            default => 'Votre formule est limitée à ' . $cap . ' éléments de ce type. Passez à une formule supérieure pour en créer davantage.',
        };
    }

    /** Message à afficher si la formule interdit une nouvelle création, sinon null. */
    public static function blockMessage(int $tenantId, string $key): ?string
    {
        $cap = Container::get(FeatureGateService::class)->intWhenDefined($tenantId, $key);
        if ($cap <= 0) {
            return null;
        }

        return self::remainingFor($cap, self::used($tenantId, $key)) === 0 ? self::messageFor($key, $cap) : null;
    }

    private static function used(int $tenantId, string $key): int
    {
        return match ($key) {
            self::OFFERS => Container::get(RecruitmentOpeningRepository::class)->countForPlanQuota($tenantId),
            self::ARTICLES => Container::get(TenantMiniArticleRepository::class)->countForPlanQuota($tenantId),
            self::TEMPLATES => (new TenantEmailTemplateRepository())->countCustomForPlanQuota($tenantId),
            default => 0,
        };
    }
}
