<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;

/**
 * Lecture sûre de l’adresse de la page « Comptes utilisateurs » (filtres, tri, pagination, colonnes).
 * Tout passe par des listes blanches : le tri et les filtres choisis par l’utilisateur n’entrent jamais
 * tels quels dans une requête SQL.
 */
final class PlatformUsersDirectoryQuery
{
    public const STATUSES = ['active', 'inactive', 'pending_verification', 'deleted', 'no_org', 'banned', 'inactifs'];

    public const PER_PAGE = [25, 50, 100];

    public const DEFAULT_PER_PAGE = 50;

    /**
     * @param array<string, mixed> $src $_GET ou équivalent
     * @return array{q: string, status: string, tenant_id: int, inactif: string, cree: string, sanction: string, tri: string, sens: string, par: int, page: int, cols: list<string>|null}
     */
    public static function normalize(array $src): array
    {
        $q = self::text($src['q'] ?? '', 120);
        $status = self::text($src['status'] ?? '', 30);
        if (!in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $inactif = self::text($src['inactif'] ?? '', 10);
        if (!in_array($inactif, UserRepository::PLATFORM_INACTIVITY_FILTERS, true)) {
            $inactif = '';
        }
        $cree = self::text($src['cree'] ?? '', 10);
        if (!in_array($cree, UserRepository::PLATFORM_CREATED_FILTERS, true)) {
            $cree = '';
        }
        $sanction = self::text($src['sanction'] ?? '', 10) === 'passee' ? 'passee' : '';
        [$tri, $sens] = UserRepository::normalizePlatformDirectorySort(
            self::text($src['tri'] ?? '', 20),
            self::text($src['sens'] ?? '', 4)
        );
        $par = (int) ($src['par'] ?? self::DEFAULT_PER_PAGE);
        if (!in_array($par, self::PER_PAGE, true)) {
            $par = self::DEFAULT_PER_PAGE;
        }
        // Le formulaire des colonnes envoie toujours « cols_set » : sans lui, colonnes par défaut.
        $cols = isset($src['cols_set']) ? PlatformUserColumns::normalize(is_array($src['cols'] ?? null) ? $src['cols'] : []) : (
            is_array($src['cols'] ?? null) ? PlatformUserColumns::normalize($src['cols']) : null
        );

        return [
            'q' => $q,
            'status' => $status,
            'tenant_id' => max(0, (int) ($src['tenant_id'] ?? 0)),
            'inactif' => $inactif,
            'cree' => $cree,
            'sanction' => $sanction,
            'tri' => $tri,
            'sens' => $sens,
            'par' => $par,
            'page' => max(1, (int) ($src['page'] ?? 1)),
            'cols' => $cols,
        ];
    }

    /**
     * Adresse relative (sans domaine) d’une vue, en omettant les valeurs par défaut.
     *
     * @param array<string, mixed> $filters résultat de normalize()
     * @param array<string, mixed> $override valeurs à remplacer (ex. ['page' => 2])
     */
    public static function query(array $filters, array $override = []): string
    {
        $f = array_merge($filters, $override);
        $bits = [];
        foreach (['q', 'status', 'inactif', 'cree', 'sanction', 'tri'] as $key) {
            $value = (string) ($f[$key] ?? '');
            if ($value !== '') {
                $bits[$key] = $value;
            }
        }
        if ((int) ($f['tenant_id'] ?? 0) > 0) {
            $bits['tenant_id'] = (string) (int) $f['tenant_id'];
        }
        if (($f['tri'] ?? '') !== '' && ($f['sens'] ?? 'asc') === 'desc') {
            $bits['sens'] = 'desc';
        }
        if ((int) ($f['par'] ?? self::DEFAULT_PER_PAGE) !== self::DEFAULT_PER_PAGE) {
            $bits['par'] = (string) (int) $f['par'];
        }
        if ((int) ($f['page'] ?? 1) > 1) {
            $bits['page'] = (string) (int) $f['page'];
        }
        $query = http_build_query($bits, '', '&', PHP_QUERY_RFC3986);
        if (is_array($f['cols'] ?? null)) {
            $query .= ($query !== '' ? '&' : '') . 'cols_set=1';
            foreach ($f['cols'] as $col) {
                $query .= '&' . rawurlencode('cols[]') . '=' . rawurlencode((string) $col);
            }
        }

        return $query;
    }

    private static function text(mixed $value, int $max): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }
        $value = trim((string) $value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}
