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

    /** Liaison de base (décision TTRD 2026-10-14, variante B) : positions, comptes rendus et briefing. */
    public const FEATURE_BASIC = 'atak_link_basic';

    /**
     * Appels du jeu ouverts avec la liaison de base. Tout le reste (photos, vidéo, drone, commandes web, IFF,
     * marqueurs, CAS, logistique…) reste réservé aux formules qui ont la carte complète (Standard et plus).
     * Préfixes de chemin après /api/.
     */
    public const BASIC_PATHS = [
        // Connexion et fonctionnement du lien (sans eux, rien ne passe).
        'atak/whoami', 'atak/client-init', 'atak/me-link-status', 'atak/disconnect',
        'atak/game-session/', 'atak/game-link/', 'atak/operator/', 'atak/presence', 'atak/server-presence',
        'atak/session-restore', 'atak/mod-modules', 'atak/mod-report', 'atak/device-logs', 'atak/device-alerts',
        'atak/web-log', 'atak/send-queue/report', 'atak/telemetry/', 'atak/notifications', 'atak/stream',
        'atak/playtime', 'atak/screen-time', 'atak/experience', 'atak/beta-register', 'atak/player-profile',
        'atak/phone-pairing', 'atak/mission-settings', 'atak/roleplay-stats',
        // Positions.
        'atak/position', 'atak/units', 'atak/squads/sync', 'units',
        // Comptes rendus.
        'atak/reports', 'atak/salute',
        // Briefing.
        'atak/briefing-plan', 'atak/briefing-presence', 'atak/briefing-slides',
    ];

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

    /** Liaison de base : la formule l'inclut (clé absente = valeur par défaut du catalogue, vraie partout). */
    public static function allowsBasic(int $tenantId): bool
    {
        if ($tenantId < 1) {
            return false;
        }

        try {
            $gate = Container::get(FeatureGateService::class);

            return $gate instanceof FeatureGateService && $gate->allowsWithCatalogDefault($tenantId, self::FEATURE_BASIC);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Chemin d'API ouvert avec la liaison de base (ex. « /api/atak/position », sous-dossier éventuel compris). */
    public static function isBasicPath(string $path): bool
    {
        $path = strtolower((string) parse_url($path, PHP_URL_PATH));
        $at = strpos($path, '/api/');
        if ($at === false) {
            return false;
        }
        $rel = substr($path, $at + 5);
        foreach (self::BASIC_PATHS as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($rel === $prefix || str_starts_with($rel, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** Appel de l'API tactique : carte complète, ou liaison de base pour les chemins de base. */
    public static function allowsRequest(int $tenantId, string $path): bool
    {
        if (self::allows($tenantId)) {
            return true;
        }

        return self::isBasicPath($path) && self::allowsBasic($tenantId);
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
            'la communauté « %s » est en formule « %s », sans la carte complète (essai fondateur terminé ou formule de base) : '
            . '%s Pour la débloquer : offrir une formule (Administration > Formules offertes) ou changer sa formule.',
            $name,
            $plan,
            self::allowsBasic($tenantId)
                ? 'la liaison de base (positions, comptes rendus, briefing) passe, le reste (photos, vidéo, drone…) est refusé.'
                : 'toutes les remontées du jeu (photos, positions, comptes rendus) sont refusées.'
        );
    }

    /** $basic : la communauté a la liaison de base, seule cette fonction est réservée aux formules supérieures. */
    public static function deniedJson(bool $basic = false): Response
    {
        return Response::json([
            'error' => 'feature_unavailable',
            'message' => $basic
                ? 'Fonction réservée aux formules Standard et plus. La liaison de base (positions, comptes rendus, briefing) reste active.'
                : 'La carte et les liaisons ne sont pas disponibles pour cette communauté. Contactez votre administrateur.',
        ], 403);
    }
}
