<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;

/**
 * Contexte d’accueil pour le passage portail → module LMS (formations, recrutement, effectifs).
 * Libellés métier uniquement — jamais de permissions snake_case côté UI.
 */
final class LmsModuleEntry
{
    public const MODULE_FORMATION = 'formation';
    public const MODULE_RECRUTEMENT = 'recrutement';
    public const MODULE_EFFECTIFS = 'effectifs';

    /**
     * Payload JSON pour le script client (profil + modules).
     *
     * @return array{
     *   autoOpen: ?string,
     *   profile: array{display_name: string, callsign: string, community: string, role: string},
     *   modules: array<string, array{key: string, title: string, kicker: string, lead: string, cta: string, accent: string, rights: list<string>}>
     * }
     */
    public static function clientConfig(?string $autoOpenModule = null): array
    {
        $auto = is_string($autoOpenModule) ? trim($autoOpenModule) : '';
        if ($auto !== '' && !isset(self::moduleBlueprints()[$auto])) {
            $auto = '';
        }

        return [
            'autoOpen' => $auto !== '' ? $auto : null,
            'profile' => self::profile(),
            'modules' => self::modulesForClient(),
        ];
    }

    /**
     * @return array{display_name: string, callsign: string, community: string, role: string}
     */
    public static function profile(): array
    {
        if (!function_exists('portal_header_context')) {
            require_once base_path('app/Support/portal_header.php');
        }

        $ctx = portal_header_context();
        $displayName = trim((string) ($ctx['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = 'Opérateur';
        }

        $callsign = trim((string) (Session::get('callsign') ?? ''));
        if ($callsign !== '' && mb_strtolower($callsign) === mb_strtolower($displayName)) {
            $callsign = '';
        }

        $community = trim((string) ($ctx['tenant_label'] ?? ''));
        if ($community === '') {
            $community = 'Communauté';
        }

        $role = trim((string) ($ctx['role_label'] ?? ''));
        if ($role === '') {
            $role = 'Membre';
        }

        return [
            'display_name' => $displayName,
            'callsign' => $callsign,
            'community' => $community,
            'role' => $role,
        ];
    }

    /**
     * @return array<string, array{key: string, title: string, kicker: string, lead: string, cta: string, accent: string, rights: list<string>}>
     */
    private static function modulesForClient(): array
    {
        $out = [];
        foreach (self::moduleBlueprints() as $key => $meta) {
            $out[$key] = [
                'key' => $key,
                'title' => $meta['title'],
                'kicker' => $meta['kicker'],
                'lead' => $meta['lead'],
                'cta' => $meta['cta'],
                'accent' => $meta['accent'],
                'rights' => self::rightsFor($key),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{title: string, kicker: string, lead: string, cta: string, accent: string}>
     */
    private static function moduleBlueprints(): array
    {
        return [
            self::MODULE_FORMATION => [
                'title' => 'Module Formations',
                'kicker' => 'Passage portail → module',
                'lead' => 'Vous quittez le tableau de bord pour rejoindre l’espace Formations : catalogue, parcours et suivi de progression de votre communauté.',
                'cta' => 'Entrer dans les formations',
                'accent' => 'emerald',
            ],
            self::MODULE_RECRUTEMENT => [
                'title' => 'Bureau recrutement',
                'kicker' => 'Passage portail → module',
                'lead' => 'Vous rejoignez le bureau recrutement : pilotage des candidatures, offres et coordination de l’équipe d’instruction.',
                'cta' => 'Entrer dans le recrutement',
                'accent' => 'sky',
            ],
            self::MODULE_EFFECTIFS => [
                'title' => 'Module Effectifs',
                'kicker' => 'Passage portail → module',
                'lead' => 'Vous quittez le tableau de bord pour rejoindre l’espace Effectifs : annuaire, structure et dossier personnel de la communauté.',
                'cta' => 'Entrer dans les effectifs',
                'accent' => 'amber',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private static function rightsFor(string $module): array
    {
        $can = static function (string $permission): bool {
            return function_exists('can') && can($permission);
        };

        return match ($module) {
            self::MODULE_FORMATION => self::formationRights($can),
            self::MODULE_RECRUTEMENT => self::recrutementRights($can),
            self::MODULE_EFFECTIFS => self::effectifsRights($can),
            default => ['Accéder aux contenus ouverts de ce module'],
        };
    }

    /**
     * @param callable(string): bool $can
     * @return list<string>
     */
    private static function formationRights(callable $can): array
    {
        $rights = [
            'Consulter le catalogue et les parcours ouverts',
            'Suivre vos inscriptions et votre progression',
        ];

        $staff = $can('admin.organization') || $can('admin.access')
            || $can('training.manage') || $can('training.assign')
            || $can('training.create') || $can('training.update')
            || $can('training.delete') || $can('training.publish')
            || $can('training.publications.manage');

        if ($staff) {
            $rights[] = 'Accéder au pilotage des formations de la communauté';
        }
        if ($can('training.create') || $can('training.publish') || $can('training.manage') || $can('admin.access')) {
            $rights[] = 'Créer ou publier des contenus de formation';
        }
        if ($can('training.assign') || $can('training.manage') || $can('admin.access')) {
            $rights[] = 'Affecter des parcours aux membres';
        }

        return $rights;
    }

    /**
     * @param callable(string): bool $can
     * @return list<string>
     */
    private static function recrutementRights(callable $can): array
    {
        $rights = [];

        if ($can('organization.recruitment.manage') || $can('admin.organization') || $can('admin.access')) {
            $rights[] = 'Instruire les dossiers de candidature';
            $rights[] = 'Coordonner le bureau recrutement';
        }
        if ($can('organization.recruitment.openings.manage') || $can('organization.recruitment.manage') || $can('admin.organization') || $can('admin.access')) {
            $rights[] = 'Gérer les offres publiées';
        }

        if ($rights === []) {
            $rights[] = 'Consulter les espaces recrutement autorisés pour votre compte';
        }

        return $rights;
    }

    /**
     * @param callable(string): bool $can
     * @return list<string>
     */
    private static function effectifsRights(callable $can): array
    {
        $rights = [
            'Consulter l’annuaire de la communauté',
            'Ouvrir votre fiche et votre espace personnel',
        ];

        if (!function_exists('can') || $can('organization.orbat.view')) {
            $rights[] = 'Consulter l’organisation (ORBAT)';
        }
        if ($can('admin.organization') || $can('admin.access')) {
            $rights[] = 'Administrer les effectifs depuis le back-office';
        }

        return array_values(array_unique($rights));
    }
}
