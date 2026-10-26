<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAlertRepository;
use App\Services\Integrations\ModUpdateDiscordNotifier;
use App\Support\AtakReleaseInfo;
use App\Support\SteamWorkshop;

/**
 * Back-office › Ressources › Mods (Steam Workshop).
 * Le site n'héberge plus d'archive : le mod Athena et le modset sont servis par Steam. Cette page affiche les
 * objets Workshop, enregistre la collection Steam et la consigne de la communauté, annonce une mise à jour
 * (annonce du portail + Discord) et permet d'effacer l'ancienne archive encore stockée.
 */
class AdminAtakModController
{
    private const BASE = 'back-office/ressources/atak-mod';
    private const MAX_CHANGELOG_NOTES = 4000;

    public function __construct(
        private ?ModUpdateDiscordNotifier $modDiscord = null,
    ) {
        $this->modDiscord ??= new ModUpdateDiscordNotifier();
    }

    /** Ancienne archive hébergée encore présente (à supprimer). */
    public function hasModForTenant(int $tenantId): bool
    {
        return is_file(SteamWorkshop::legacyArchivePath($tenantId));
    }

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        $legacy = SteamWorkshop::legacyArchivePath($tenantId);
        $release = AtakReleaseInfo::latest();

        return Response::view('layout.main', [
            'content' => 'admin.atak-mod.index',
            'title' => 'Mods (Steam Workshop)',
            'backOfficePageCss' => ['back-office-atak-mod.css'],
            'modItems' => SteamWorkshop::items(),
            'modTenant' => SteamWorkshop::tenant($tenantId),
            'modVersion' => (string) ($release['atak_native'] ?? ''),
            'modVersionName' => (string) (AtakReleaseInfo::versionName() ?? ''),
            'modLinkVersion' => (string) ($release['link'] ?? ''),
            'modLegacyArchive' => is_file($legacy) ? ['size' => (int) filesize($legacy), 'at' => date('d/m/Y H:i', (int) filemtime($legacy))] : null,
            'memberPageUrl' => url('atak/mod'),
            'discordRelayReady' => $this->modDiscord->hasWebhookConfigured($tenantId),
            'organizationSettingsUrl' => url('back-office/organisation/parametres'),
            'csrfToken' => Csrf::token(),
        ]);
    }

    /** Collection Steam (modset) et consigne affichées aux membres sur /atak/mod. */
    public function saveSteam(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $res = SteamWorkshop::saveTenant($tenantId, [
            'collection_id' => (string) $request->input('collection_id', ''),
            'collection_name' => (string) $request->input('collection_name', ''),
            'notes' => (string) $request->input('notes', ''),
        ]);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Mods de la communauté enregistrés. Les membres les voient sur la page d’installation.' : (string) $res['error']);

        return Response::redirect(url(self::BASE));
    }

    /** Annonce de mise à jour : annonce du portail (14 jours) et, si demandé, relais Discord. */
    public function announce(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $version = trim((string) $request->input('version', ''));
        $version = preg_match('/^[0-9]+(\.[0-9]+){1,3}$/', $version) === 1 ? $version : null;
        $notes = mb_substr(trim((string) $request->input('changelog_notes', '')), 0, self::MAX_CHANGELOG_NOTES);
        $what = trim((string) $request->input('what', 'mod')) === 'collection' ? 'collection' : 'mod';
        $title = $what === 'collection' ? 'Les mods de la communauté ont changé' : 'Mise à jour du mod Athena';
        $body = $what === 'collection'
            ? 'La collection Steam de la communauté a été mise à jour : ouvrez la page d’installation et abonnez-vous aux nouveaux mods.'
            : 'Le mod Athena' . ($version ? ' ' . $version : '') . ' est disponible : Steam le met à jour automatiquement, relancez Arma pour en profiter.';
        try {
            (new TenantAlertRepository())->insert($tenantId, [
                'kind' => 'info',
                'display_style' => 'classic',
                'title' => $title,
                'body' => $notes !== '' ? $body . "\n\n" . mb_substr($notes, 0, 600) : $body,
                'cta_label' => 'Installer / mettre à jour',
                'cta_url' => url('atak/mod'),
                'coupon_code' => null,
                'accent_color' => '#059669',
                'icon_key' => 'megaphone',
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => date('Y-m-d H:i:s', time() + 14 * 24 * 3600),
                'sort_order' => 0,
                'is_active' => 1,
            ]);
        } catch (\Throwable) {
            Session::flash('error', 'L’annonce n’a pas pu être publiée. Réessayez.');

            return Response::redirect(url(self::BASE));
        }
        $suffix = '';
        if ((string) $request->input('notify_discord', '') !== '') {
            $r = $this->modDiscord->notifyModUpdate($tenantId, $version, url('atak/mod'), $notes !== '' ? $notes : null, null);
            $suffix = $r['sent'] ? ' Relayée sur Discord.' : ($r['skipped'] ? ' Aucun relais Discord configuré.' : ' Le relais Discord a échoué.');
        }
        Session::flash('success', 'Annonce publiée pour la communauté (14 jours).' . $suffix);

        return Response::redirect(url(self::BASE));
    }

    /** Supprime l'ancienne archive hébergée par le site (avant le passage au Workshop). */
    public function delete(Request $request, array $params = []): Response
    {
        $tenantId = $this->guard($request);
        if ($tenantId instanceof Response) {
            return $tenantId;
        }
        $path = SteamWorkshop::legacyArchivePath($tenantId);
        if (is_file($path)) {
            @unlink($path);
        }
        Session::flash('success', 'Ancienne archive supprimée du serveur.');

        return Response::redirect(url(self::BASE));
    }

    private function guard(Request $request): int|Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('login'));
        }
        if ($request->method() !== 'POST' || !Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Requête invalide. Réessayez.');

            return Response::redirect(url(self::BASE));
        }

        return $tenantId;
    }
}
