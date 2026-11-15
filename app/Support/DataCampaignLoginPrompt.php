<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Core\Session;
use App\Services\DataCampaign\DataCampaignService;

/**
 * Fiabilisation à la connexion : quand une campagne ouverte attend la confirmation de la fiche du membre, la connexion
 * l'envoie sur la page de vue d'ensemble de la campagne (/jnet/moi/fiabilisation?etape=accueil), avant sa destination.
 *
 * La décision se prend une seule fois, au moment de la connexion (AuthController::redirectToDashboardAfterLogin, donc aussi
 * pour la connexion par Discord et après la double vérification) : « Plus tard » renvoie à la destination demandée et la
 * page ne revient qu'à la connexion suivante. Elle ne bloque rien : aucune autre page ne la force, avant comme après la
 * date limite (seul le ton du texte change, voir tone()). La destination d'origine (page demandée avant la connexion,
 * invitation, espace) est gardée côté serveur dans la session : jamais dans l'adresse, donc aucune redirection ouverte.
 *
 * Les règles de décision et d'état des parties sont pures (testables sans base) ; prepare() ne fait que les appliquer.
 */
final class DataCampaignLoginPrompt
{
    /** Clé de session : destination d'origine, rejouée par « Plus tard » ou à la fin de la confirmation. */
    public const SESSION_CONTINUE = 'data_campaign_login_continue';

    /** Étape de la page : vue d'ensemble de toutes les parties de la fiche. */
    public const STEP = 'accueil';

    /** Clés de session d'une session d'assistance ou d'usurpation par un administrateur : jamais de redirection. */
    private const IMPERSONATION_KEYS = ['impersonator_id', 'impersonating', 'admin_impersonation', 'support_session_id'];

    /**
     * Faut-il envoyer ce membre sur la page de fiabilisation ?
     *
     * @param array{pending?: bool, impersonating?: bool, ajax?: bool, hasOrganization?: bool, continueUrl?: string} $ctx
     *        pending : une campagne ouverte attend sa confirmation ; continueUrl : destination déjà prévue
     */
    public static function shouldPrompt(array $ctx): bool
    {
        if (empty($ctx['pending']) || empty($ctx['hasOrganization']) || !empty($ctx['impersonating']) || !empty($ctx['ajax'])) {
            return false;
        }
        // Déjà en route vers la page de la campagne : rien à ajouter.
        $continue = strtolower((string) ($ctx['continueUrl'] ?? ''));

        return !str_contains((string) parse_url($continue, PHP_URL_PATH), '/fiabilisation');
    }

    /**
     * Ton du texte : 'open' avant la date limite (ou sans date), 'overdue' après. Jamais bloquant.
     *
     * @return 'open'|'overdue'
     */
    public static function tone(string $dueOn, string $today): string
    {
        return $dueOn !== '' && $dueOn < $today ? 'overdue' : 'open';
    }

    /**
     * État d'une partie pour la vue d'ensemble.
     *  - 'ok'         : confirmée exacte (et inchangée depuis) ;
     *  - 'flagged'    : le membre a signalé une erreur, le suivi est en cours ;
     *  - 'incomplete' : la fiche est vide ou a une valeur manquante dans cette partie : à compléter ;
     *  - 'to_confirm' : lue, complète, en attente du « c'est exact ».
     *
     * @param array<string, mixed> $block bloc de DataCampaignService::memberView
     * @return 'ok'|'flagged'|'incomplete'|'to_confirm'
     */
    public static function blockStatus(array $block): string
    {
        $state = (string) ($block['state'] ?? '');
        if ($state === 'flag') {
            return 'flagged';
        }
        if ($state === 'ok') {
            return 'ok';
        }
        $rows = is_array($block['rows'] ?? null) ? $block['rows'] : [];
        if ($rows === [] && empty($block['portrait'])) {
            return 'incomplete';
        }
        foreach ($rows as $row) {
            $value = trim((string) (is_array($row) ? ($row['value'] ?? '') : ''));
            if ($value === '' || $value === '—' || $value === '-') {
                return 'incomplete';
            }
        }

        return 'to_confirm';
    }

    /**
     * Compte des états et avancement global ; « Tout confirmer » n'est proposé que si rien n'est à compléter ni signalé.
     *
     * @param array<string, array<string, mixed>> $blocks
     * @return array{ok: int, flagged: int, incomplete: int, to_confirm: int, total: int, percent: int, can_confirm_all: bool}
     */
    public static function summarize(array $blocks): array
    {
        $count = ['ok' => 0, 'flagged' => 0, 'incomplete' => 0, 'to_confirm' => 0];
        foreach ($blocks as $block) {
            $count[self::blockStatus($block)]++;
        }
        $total = count($blocks);
        $done = $count['ok'] + $count['flagged'];

        return $count + [
            'total' => $total,
            'percent' => $total > 0 ? (int) floor($done * 100 / $total) : 0,
            'can_confirm_all' => $total > 0 && $count['incomplete'] === 0 && $count['flagged'] === 0 && $count['ok'] + $count['to_confirm'] === $total,
        ];
    }

    /** Session d'usurpation ou d'assistance en cours ? */
    public static function isImpersonating(): bool
    {
        foreach (self::IMPERSONATION_KEYS as $key) {
            if (!empty(Session::get($key))) {
                return true;
            }
        }

        return false;
    }

    /** Requête AJAX ou qui attend du JSON. */
    public static function isAjax(): bool
    {
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return $xhr === 'xmlhttprequest' || (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html'));
    }

    /**
     * Appelée juste après la connexion : renvoie l'adresse de la page de fiabilisation (la destination d'origine est
     * gardée en session), ou null quand la connexion doit continuer normalement. Ne bloque jamais la connexion.
     */
    public static function prepare(array $user, string $continueUrl, ?DataCampaignService $service = null): ?string
    {
        Session::forget(self::SESSION_CONTINUE);
        try {
            if (self::isImpersonating() || self::isAjax()) {
                return null;
            }
            $userId = (int) ($user['id'] ?? 0);
            $tenantId = (int) ($user['tenant_id'] ?? 0);
            // Compte invité ou sans organisation réelle, ou profil de communauté sans JNET (la page est dans JNET).
            if ($userId < 1 || $tenantId < 1 || PortalAccessChoice::isNoOrganizationContext() || !JnetAnnouncement::viewerHasJnet()) {
                return null;
            }
            $service ??= Container::get(DataCampaignService::class);
            if (!self::shouldPrompt([
                'pending' => $service->pendingBadge($tenantId, $userId) > 0,
                'hasOrganization' => true,
                'continueUrl' => $continueUrl,
            ])) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }
        Session::set(self::SESSION_CONTINUE, $continueUrl);

        return jnet_moi_url('fiabilisation', ['etape' => self::STEP]);
    }

    /** Destination d'origine gardée à la connexion (sans la consommer), ou null. */
    public static function continueUrl(): ?string
    {
        $url = trim((string) Session::get(self::SESSION_CONTINUE, ''));

        return $url !== '' ? $url : null;
    }

    /** Rend la destination d'origine (ou le tableau de bord) et l'oublie : « Plus tard » ou fin de la confirmation. */
    public static function consumeContinue(): string
    {
        $url = self::continueUrl();
        Session::forget(self::SESSION_CONTINUE);

        return $url ?? url('dashboard');
    }
}
