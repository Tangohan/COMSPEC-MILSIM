<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

/**
 * Anciennes adresses du back-office retirées ou fusionnées : redirection permanente (301)
 * vers la page qui les remplace. La chaîne de requête d’origine est conservée (filtres, favoris) ;
 * l’onglet de la cible, s’il y en a un, prime.
 *
 * Seules les lectures (GET) sont redirigées : les formulaires POST gardent leurs adresses.
 */
final class BackOfficeLegacyRedirects
{
    /**
     * Ancien chemin (sans slash initial) → nouvelle cible (chemin, éventuellement « ?onglet=… »).
     *
     * @var array<string, string>
     */
    public const MAP = [
        // Centre d’opérations : doublon du tableau de bord (photos perdues → Qualité des données).
        'back-office/centre-operations' => 'back-office',
        'back-office/operations-admin' => 'back-office',
        // Page de liens « Structure & effectifs » : l’organigramme (ORBAT) la remplace.
        'back-office/organisation-effectifs' => 'orbat',
        // Même page sous deux adresses : on garde celle du menu.
        'back-office/organisation/parametres' => 'back-office/community',
        // Forum retiré le 25/10/2026.
        'back-office/ressources/forum-config' => 'back-office',
        'back-office/onboarding-members' => 'back-office/integration-membres',
        'back-office/personnel/advanced-edit' => 'admin/system/advanced-fiche-edit',
    ];

    public static function target(string $legacyPath): ?string
    {
        return self::MAP[trim($legacyPath, '/')] ?? null;
    }

    /**
     * Adresse de destination, requête d’origine comprise.
     *
     * @param array<string, mixed> $query
     */
    public static function location(string $legacyPath, array $query = []): ?string
    {
        $target = self::target($legacyPath);
        if ($target === null) {
            return null;
        }
        $path = $target;
        $targetQuery = [];
        $qPos = strpos($target, '?');
        if ($qPos !== false) {
            $path = substr($target, 0, $qPos);
            parse_str(substr($target, $qPos + 1), $targetQuery);
        }
        $merged = array_merge($query, $targetQuery);
        $qs = $merged === [] ? '' : '?' . http_build_query($merged, '', '&', PHP_QUERY_RFC3986);

        return url($path) . $qs;
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function response(string $legacyPath, array $query = []): Response
    {
        $location = self::location($legacyPath, $query) ?? url('back-office');

        return Response::redirect($location, 301);
    }
}
