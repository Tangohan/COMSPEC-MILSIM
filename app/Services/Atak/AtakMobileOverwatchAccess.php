<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Core\Container;
use App\Core\Session;
use App\Services\Moderation\ModerationRestrictionResolver;
use App\Services\Moderation\ModerationRestrictionsCatalog;
use App\Support\ComspecApiKeyAuth;

/**
 * Carte du téléphone (/m/carte, /m/briefs) : lit les mêmes données que la carte Overwatch, par les mêmes adresses.
 *
 * La session « Sur mon téléphone » (QR code du jeu, AtakMobileLinkService) ne contient ni compte ni communauté : pour
 * les seules lectures ci-dessous (GET), la requête est traitée comme celle du membre qui a scanné le QR code, dans la
 * communauté du lien, exactement comme son Overwatch connecté. Les contrôleurs appliquent donc les mêmes filtres
 * (communauté, carte, brouillage renseignement, formule, maintenance) : aucun droit en plus.
 * - Rien n'est écrit en session (Session::actAs, pour la requête en cours) : la session du téléphone reste limitée
 *   aux pages /m/… ; les écritures passent toujours par /m/api/… (CSRF, débit limité).
 * - Navigateur déjà connecté au portail, ou client du jeu (clé, jeton) : rien ne change.
 * - Session du téléphone expirée, révoquée ou d'une autre communauté : relue en base, refusée comme avant.
 * - Membre privé de la carte ATAK par la modération (comme à l'ouverture de l'Overwatch) : refusé.
 */
final class AtakMobileOverwatchAccess
{
    /** Lectures de la carte Overwatch utilisées par la carte du téléphone. */
    public const READ_PATHS = [
        '/api/units',
        '/api/atak/markers',
        '/api/map-shapes',
        '/api/atak/air-assets',
        '/api/cas',
        '/api/nine-line',
    ];

    public static function covers(string $method, string $path): bool
    {
        return strtoupper($method) === 'GET' && in_array(rtrim($path, '/'), self::READ_PATHS, true);
    }

    /**
     * Requête de la carte du téléphone : prête l'identité du lien (compte et communauté) pour cette requête.
     *
     * @param (callable(int, int): bool)|null $atakAllowed module ATAK permis au membre (modération), par défaut celui
     *                                                  de l'Overwatch (AtakModuleSanctionMiddleware)
     * @return bool vrai si la requête lit avec la session du téléphone
     */
    public static function apply(string $method, string $path, ?AtakMobileLinkService $links = null, ?callable $atakAllowed = null): bool
    {
        if (!self::covers($method, $path)) {
            return false;
        }
        if ((int) Session::get('user_id') > 0 || ComspecApiKeyAuth::extractPresentedKey() !== '') {
            return false;
        }
        if (!is_array(Session::get(AtakMobileLinkService::SESSION_KEY))) {
            return false;
        }
        $link = ($links ?? new AtakMobileLinkService())->current();
        if ($link === null || (int) ($link['user_id'] ?? 0) < 1 || (int) ($link['tenant_id'] ?? 0) < 1) {
            return false;
        }
        $tenantId = (int) $link['tenant_id'];
        $userId = (int) $link['user_id'];
        $atakAllowed ??= static fn (int $t, int $u): bool => Container::get(ModerationRestrictionResolver::class)
            ->isModuleAllowed($t, $u, ModerationRestrictionsCatalog::KEY_ATAK);
        try {
            if (!$atakAllowed($tenantId, $userId)) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }
        Session::actAs(['user_id' => $userId, 'tenant_id' => $tenantId]);

        return true;
    }
}
