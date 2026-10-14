<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Core\Response;
use App\Repositories\TenantRepository;
use App\Services\Platform\FeatureGateService;

/**
 * Accès plan « carte et liaisons » (feature atak) pour l’API tactique.
 * Le poste web l’applique déjà ; l’API doit refuser la même chose.
 */
final class AtakPlanAccess
{
    public const FEATURE = 'atak';

    public static function allows(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }

        try {
            $gate = Container::get(FeatureGateService::class);
            if (!$gate instanceof FeatureGateService) {
                return false;
            }

            return $gate->allows($tenantId, self::FEATURE);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Rapport du mod qui porte ce refus (ex. « ERR|http_403|photo.png|feature_unavailable »). */
    public static function isDenialReport(string $message, string $detail = ''): bool
    {
        return str_contains(strtolower($message . ' ' . $detail), 'feature_unavailable');
    }

    /**
     * Diagnostic ajouté au rapport du mod : communauté, formule appliquée et accès à la carte.
     * Vide si la communauté est inconnue.
     */
    public static function diagnosis(int $tenantId): string
    {
        if ($tenantId < 1) {
            return '';
        }
        try {
            $tenant = Container::get(TenantRepository::class)->findById($tenantId);
        } catch (\Throwable) {
            $tenant = null;
        }
        if (!is_array($tenant)) {
            return '';
        }
        $name = trim((string) ($tenant['name'] ?? '')) ?: ('#' . $tenantId);
        $plan = PlanGift::effectivePlanSlug($tenant);
        if (self::allows($tenantId)) {
            return sprintf('la communauté « %s » (formule « %s ») a bien la carte et les liaisons : refus passager ou ancien.', $name, $plan);
        }

        return sprintf(
            'la communauté « %s » est en formule « %s », sans la carte et les liaisons (essai fondateur terminé ou formule de base) : '
            . 'toutes les remontées du jeu (photos, positions, comptes rendus) sont refusées. Pour la débloquer : offrir une formule '
            . '(Administration > Formules offertes) ou changer sa formule.',
            $name,
            $plan
        );
    }

    public static function deniedJson(): Response
    {
        return Response::json([
            'error' => 'feature_unavailable',
            'message' => 'La carte et les liaisons ne sont pas disponibles pour cette communauté. Contactez votre administrateur.',
        ], 403);
    }
}
