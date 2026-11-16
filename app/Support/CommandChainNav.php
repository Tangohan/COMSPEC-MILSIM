<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Sous-navigation « Chaîne de commandement » des Effectifs : les pages qui disent qui commande qui et au niveau attendu.
 *
 * Deux pages de l’espace Effectifs (la chaîne, l’adéquation de l’organisation avec ses échelons) et les référentiels
 * qu’elles lisent, chacun servi par sa propre page et son propre contrôle d’accès : aucune page n’est créée ici, aucun
 * droit n’est ajouté. Un lien n’est rendu que si la personne peut ouvrir la page ($allowed, mêmes règles que le menu
 * latéral : type de communauté et Gate).
 *
 * Inventaire du domaine et pages sans entrée : docs/dev/EFFECTIFS-NAVIGATION-CHAINE.md.
 */
final class CommandChainNav
{
    /**
     * [clé, libellé, chemin (sans requête), requête, groupe, description courte].
     * Groupe « chain » : pages de l’espace Effectifs ; « ref » : référentiels liés, hors de l’espace.
     */
    public const PAGES = [
        ['chaine', 'Vue de la chaîne', 'back-office/ressources/effectifs/chaine', '', 'chain', 'Chefs, postes à pourvoir et qui relève de qui.'],
        ['adequation', 'Adéquation', 'back-office/ressources/effectifs/chaine/adequation', '', 'chain', 'Écarts entre l’organisation réelle et les attentes.'],
        ['echelons', 'Échelons attendus', 'back-office/ressources/effectifs/chaine/adequation', 'onglet=echelons', 'chain', 'Ce que chaque échelon attend de son chef et de son adjoint.'],
        ['grades', 'Grades et équivalences OTAN', 'back-office/referentiels/grades', '', 'ref', 'Échelle des grades comparée à l’échelon.'],
        ['qualifications', 'Qualifications', 'back-office/referentiels/qualifications', '', 'ref', 'Brevets exigibles d’un chef selon l’échelon.'],
        ['fonctions', 'Doctrine des fonctions', 'back-office/roles-functions', '', 'ref', 'Relations de commandement entre les rôles.'],
        ['doctrine', 'Référentiel doctrinal', 'back-office/doctrine/referentiel', '', 'ref', 'Doctrine française, américaine ou les deux.'],
    ];

    /** Pages de l’espace Effectifs qui affichent cette sous-navigation (clé effectifsNav du shell). */
    public const SHELL_KEYS = ['chaine', 'adequation'];

    /**
     * Clé de la page courante d’après le chemin et l’onglet de l’adéquation.
     */
    public static function currentKey(string $path, string $onglet = ''): string
    {
        $path = trim($path, '/');
        if (str_ends_with($path, 'effectifs/chaine/adequation')) {
            return $onglet === 'echelons' ? 'echelons' : 'adequation';
        }
        if (str_ends_with($path, 'effectifs/chaine')) {
            return 'chaine';
        }

        return '';
    }

    /**
     * Liens visibles, dans l’ordre, avec l’état courant et la pastille.
     *
     * @param callable(string): bool $allowed adresse → la personne peut-elle ouvrir la page
     * @param array<string, int> $badges clé → nombre (0 : pas de pastille)
     * @return list<array{key: string, label: string, href: string, group: string, desc: string, current: bool, badge: int}>
     */
    public static function links(string $current, callable $allowed, array $badges = []): array
    {
        $out = [];
        foreach (self::PAGES as [$key, $label, $path, $query, $group, $desc]) {
            $href = (function_exists('url') ? url($path) : '/' . $path) . ($query !== '' ? '?' . $query : '');
            if (!$allowed($href)) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'href' => $href,
                'group' => $group,
                'desc' => $desc,
                'current' => $key === $current,
                'badge' => max(0, (int) ($badges[$key] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * Règle d’accès du menu latéral (views/partials/back_office_sidebar.php) : type de communauté, puis Gate.
     *
     * @return callable(string): bool
     */
    public static function menuAllowed(?string $tenantType = null): callable
    {
        $tenantType ??= function_exists('navigation_current_tenant_type')
            ? navigation_current_tenant_type()
            : \App\Services\Community\TenantTypeConfig::TYPE_FULL;

        return static function (string $href) use ($tenantType): bool {
            $path = function_exists('back_office_nav_href_to_path')
                ? back_office_nav_href_to_path($href)
                : trim((string) parse_url($href, PHP_URL_PATH), '/');
            if ($path !== '' && (!\App\Services\Community\TenantTypeConfig::uriAllowed($tenantType, $path)
                || \App\Services\Community\TenantTypeConfig::backOfficePathHidden($tenantType, $path))) {
                return false;
            }

            return !function_exists('back_office_nav_href_permission_allowed') || back_office_nav_href_permission_allowed($href);
        };
    }
}
