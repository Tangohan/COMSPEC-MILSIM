<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;

/**
 * Annonce de l'extranet JNET sur le portail : la fenêtre « JNET arrive » (une seule fois par membre, pendant une période)
 * et le bandeau du tableau de bord (masquable, réaffichable).
 *
 * Les règles d'affichage sont ici, sans accès à la base, pour être testées : les vues (views/partials/jnet_announce_modal.php,
 * views/partials/jnet_dashboard_banner.php) ne font que les appliquer. L'état « déjà vu » d'un membre est une ligne de
 * la table user_ui_tours (UserUiTourRepository), qui existe déjà : aucune migration.
 *
 * POUR RETIRER L'ANNONCE : mettre MODAL_ENDS_AT dans le passé (la fenêtre ne s'affiche plus, le bandeau reste), ou
 * supprimer l'inclusion de views/partials/jnet_announce_modal.php dans views/layout/main.php et views/dashboard.php.
 */
final class JnetAnnouncement
{
    /** Clé « déjà vu » de la fenêtre d'annonce (table user_ui_tours, 64 caractères au plus). Changer la version la remontre à tous. */
    public const MODAL_KEY = 'jnet.announce.v1';

    /** Clé « masqué » du bandeau du tableau de bord. */
    public const BANNER_KEY = 'jnet.banner.v1';

    /** Début de la période d'affichage de la fenêtre (heure du serveur, format Y-m-d H:i:s). */
    public const MODAL_STARTS_AT = '2026-11-15 00:00:00';

    /** Fin de la période : passé cette date, la fenêtre ne s'affiche plus pour personne. */
    public const MODAL_ENDS_AT = '2027-01-31 23:59:59';

    /** Clé de session : la fenêtre a déjà été vue (évite une requête à chaque page). */
    public const SESSION_MODAL_SEEN = 'jnet_announce_modal_seen';

    /**
     * Pages où la fenêtre ne s'affiche jamais : connexion et inscription, récupération de compte, sécurité et e-mail du
     * compte, accueil d'un nouveau compte, API, et JNET lui-même (qui n'a pas besoin d'être annoncé).
     */
    private const EXCLUDED_PATH = '~(^|/)(login|logout|register|forgot-password|reset-password|verify-email|resend-verification|choisir-espace|api|jnet|account/(security|password|mail|bienvenue|steam|steam-doublon)|compte/(securite|mot-de-passe))(/|$)~i';

    /** Le moment est-il dans la période d'affichage de la fenêtre ? */
    public static function isModalPeriod(int $now, string $startsAt = self::MODAL_STARTS_AT, string $endsAt = self::MODAL_ENDS_AT): bool
    {
        $start = strtotime($startsAt);
        $end = strtotime($endsAt);
        if ($start === false || $end === false) {
            return false;
        }

        return $now >= $start && $now <= $end;
    }

    /** Page exclue (connexion, sécurité, API…) : chemin de l'adresse, avec ou sans préfixe du site. */
    public static function isExcludedPath(string $path): bool
    {
        $path = trim(strtolower($path), '/');

        return $path !== '' && preg_match(self::EXCLUDED_PATH, $path) === 1;
    }

    /**
     * Faut-il montrer la fenêtre à ce visiteur sur cette page ?
     *
     * @param array{loggedIn?: bool, hasJnet?: bool, seen?: bool, now?: int, path?: string, otherModal?: bool, shell?: bool} $ctx
     *        shell : page d'administration ou d'un espace de travail (pas de fenêtre) ; otherModal : une autre fenêtre passe d'abord
     */
    public static function shouldShowModal(array $ctx): bool
    {
        if (empty($ctx['loggedIn']) || empty($ctx['hasJnet']) || !empty($ctx['seen']) || !empty($ctx['otherModal']) || !empty($ctx['shell'])) {
            return false;
        }
        if (self::isExcludedPath((string) ($ctx['path'] ?? ''))) {
            return false;
        }

        return self::isModalPeriod((int) ($ctx['now'] ?? time()));
    }

    /**
     * Le bandeau est-il affiché en grand, réduit à un lien discret, ou absent ?
     *
     * @return 'full'|'collapsed'|'none'
     */
    public static function bannerMode(bool $hasJnet, bool $dismissed): string
    {
        if (!$hasJnet) {
            return 'none';
        }

        return $dismissed ? 'collapsed' : 'full';
    }

    /**
     * Le membre a-t-il accès à JNET ? Même condition que l'arrivée par défaut (PortalAccessChoice::memberLandingUrl) et que
     * le hub : une organisation réelle, et un profil de communauté qui propose JNET (TenantTypeConfig). L'encadrement y
     * a accès aussi (onglet « Mon extranet ») : il n'est pas écarté ici.
     */
    public static function viewerHasJnet(): bool
    {
        if ((int) Session::get('user_id', 0) < 1 || PortalAccessChoice::isNoOrganizationContext()) {
            return false;
        }
        try {
            /** @var \App\Repositories\TenantRepository $tenants */
            $tenants = \App\Core\Container::get(\App\Repositories\TenantRepository::class);
            $tenant = $tenants->findById((int) Session::get('tenant_id', 0));
        } catch (\Throwable) {
            return false;
        }
        if (!is_array($tenant)) {
            return false;
        }

        return PortalAccessChoice::jnetOfferedTo((string) ($tenant['tenant_type'] ?? 'full'));
    }

    /** Ce membre a-t-il fermé l'élément (clé MODAL_KEY ou BANNER_KEY) ? Faux si la table manque ou si la base ne répond pas. */
    public static function isDismissed(int $userId, string $key): bool
    {
        if ($userId < 1) {
            return false;
        }
        try {
            return (new \App\Repositories\UserUiTourRepository())->isDismissed($userId, $key);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Chemin de la requête en cours, sans paramètres. */
    public static function currentPath(): string
    {
        return (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    }
}
