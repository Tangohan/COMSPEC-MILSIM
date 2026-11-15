<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Table unique des pages « Ma situation » du membre. Elles vivent dans l'espace JNET (/jnet/moi/…) ; l'ancienne adresse
 * (/back-office/ma-situation/…) ne sert plus que de redirection (GET) et de point d'arrivée des anciens formulaires (POST).
 *
 * Tout lien, menu, notification ou retour de formulaire construit son adresse ici (jnet_moi_url()) plutôt que de la
 * recopier : une page qui change d'adresse ne se corrige qu'une fois.
 */
final class JnetMemberPaths
{
    /** Racine des pages du membre dans JNET (sans slash). */
    public const BASE = 'jnet/moi';

    /** Ancienne racine (sans slash) : redirigée en 301 pour les lectures, toujours servie pour les envois. */
    public const LEGACY_BASE = 'back-office/ma-situation';

    /**
     * Les 17 pages (lecture), par fin d'adresse : rubrique du hub où elle s'affiche et classe qui en porte la migration.
     *
     * « owner » est le contrôleur qui rend réellement la page (Response::view) : tant qu'il ne déclare pas
     * JNET_MOI_READY, la page est servie par JnetMemberBridge sous l'habillage d'avant (voir cette classe).
     *
     * @var array<string, array{section: string, owner: class-string}>
     */
    public const PAGES = [
        'ma-fiche' => ['section' => 'profil', 'owner' => \App\Controllers\Web\PersonnelController::class],
        'ma-fiche/modifier' => ['section' => 'profil', 'owner' => \App\Controllers\Web\PersonnelController::class],
        'unite' => ['section' => 'unite', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'evenements' => ['section' => 'unite', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'qualifications' => ['section' => 'profil', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'avancement' => ['section' => 'profil', 'owner' => \App\Controllers\Admin\Organization\MemberAdvancementController::class],
        'carriere' => ['section' => 'profil', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'decorations' => ['section' => 'profil', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'dotation' => ['section' => 'profil', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'liaison-atak' => ['section' => 'atak', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'premiere-liaison' => ['section' => 'atak', 'owner' => \App\Controllers\Web\AtakFirstLinkController::class],
        'appareils' => ['section' => 'atak', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'musique' => ['section' => 'atak', 'owner' => \App\Controllers\Web\MusicLibraryController::class],
        'carnet-de-vol' => ['section' => 'atak', 'owner' => \App\Controllers\Admin\Organization\PilotLogbookController::class],
        'coffre' => ['section' => 'donnees', 'owner' => \App\Controllers\Admin\Organization\MemberSituationController::class],
        'mes-papiers' => ['section' => 'donnees', 'owner' => \App\Controllers\Admin\MemberIdentityPapersController::class],
        'mes-demarches' => ['section' => 'service', 'owner' => \App\Controllers\Web\RhWorkspaceController::class],
    ];

    /**
     * Lectures à paramètres (téléchargements) : forme de la fin d'adresse, avec ses jokers de routeur.
     *
     * @var list<string>
     */
    public const DOWNLOADS = [
        'qualifications/{awardId}/brevet',
        'mes-papiers/couverture/{exportId}/telecharger',
        'musique/ecouter/{id}',
    ];

    /**
     * Envois (POST) par fin d'adresse. Ils restent aussi servis à l'ancienne adresse.
     *
     * @var list<string>
     */
    public const POSTS = [
        'avancement/{campaignId}/volontaire',
        'avancement/demande',
        'avancement/affectation',
        'qualifications/{awardId}/generer-brevet',
        'carnet-de-vol/ajouter',
        'carnet-de-vol/{entryId}/modifier',
        'carnet-de-vol/{entryId}/supprimer',
        'mes-papiers/couverture',
        'appareils/retirer',
        'musique/envoyer',
        'musique/morceaux/{id}/modifier',
        'musique/morceaux/{id}/supprimer',
        'musique/morceaux/{id}/moderer',
        'musique/morceaux/{id}/duree',
        'musique/playlists/creer',
        'musique/playlists/{id}/modifier',
        'musique/playlists/{id}/supprimer',
        'musique/playlists/{id}/ajouter',
        'musique/playlists/{id}/retirer',
        'musique/playlists/{id}/deplacer',
    ];

    /**
     * Nouvelle adresse (sans slash initial) d'une page ou d'un téléchargement, par fin d'adresse.
     * Aucune validation : la fin d'adresse vient du code, jamais de la requête.
     */
    public static function path(string $slug = ''): string
    {
        $slug = trim($slug, '/');

        return $slug === '' ? self::BASE : self::BASE . '/' . $slug;
    }

    /** Ancienne adresse (sans slash initial). */
    public static function legacyPath(string $slug = ''): string
    {
        $slug = trim($slug, '/');

        return $slug === '' ? self::LEGACY_BASE : self::LEGACY_BASE . '/' . $slug;
    }

    /**
     * Adresse complète d'une page, avec sa requête et son ancre éventuelles : url('jnet/moi/…').
     *
     * @param array<string, scalar> $query
     */
    public static function url(string $slug, array $query = [], string $fragment = ''): string
    {
        $qs = $query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return url(self::path($slug)) . $qs . ($fragment !== '' ? '#' . ltrim($fragment, '#') : '');
    }

    /**
     * Ancienne adresse d'une lecture statique → nouvelle, pour BackOfficeLegacyRedirects::MAP.
     *
     * @return array<string, string>
     */
    public static function legacyRedirects(): array
    {
        $map = [];
        foreach (array_keys(self::PAGES) as $slug) {
            $map[self::legacyPath($slug)] = self::path($slug);
        }

        return $map;
    }

    /**
     * Traduit un chemin (avec ou sans slash initial) d'une ancienne adresse vers la nouvelle ; null si ce n'en est pas une.
     * Sert aux règles de chemin (modules de profil de communauté, droits de menu) qui connaissent les deux formes.
     */
    public static function toLegacy(string $path): ?string
    {
        $p = trim($path, '/ ');
        if ($p === self::BASE) {
            return self::LEGACY_BASE;
        }
        if (str_starts_with($p, self::BASE . '/')) {
            return self::LEGACY_BASE . substr($p, strlen(self::BASE));
        }

        return null;
    }

    /** Le chemin (HTTP, avec slash initial ou non) est-il sous /jnet/moi ? */
    public static function isMemberPath(string $path): bool
    {
        $p = trim($path, '/ ');

        return $p === self::BASE || str_starts_with($p, self::BASE . '/');
    }

    /** Fin d'adresse d'un chemin sous /jnet/moi ('' pour la racine), null hors de cet espace. */
    public static function slugOf(string $path): ?string
    {
        $p = trim($path, '/ ');
        if ($p === self::BASE) {
            return '';
        }

        return str_starts_with($p, self::BASE . '/') ? substr($p, strlen(self::BASE) + 1) : null;
    }

    /**
     * Page (clé de PAGES) à laquelle appartient une fin d'adresse, téléchargements compris : « qualifications/12/brevet »
     * → « qualifications ». Sert à allumer la bonne entrée du rail.
     */
    public static function pageOf(string $slug): ?string
    {
        $slug = trim($slug, '/');
        if (isset(self::PAGES[$slug])) {
            return $slug;
        }
        $best = null;
        foreach (array_keys(self::PAGES) as $page) {
            if (str_starts_with($slug, $page . '/') && ($best === null || strlen($page) > strlen($best))) {
                $best = $page;
            }
        }

        return $best;
    }
}
