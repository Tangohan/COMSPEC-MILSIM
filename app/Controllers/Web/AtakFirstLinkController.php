<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantAtakConfigRepository;
use App\Repositories\TacticalGameLinkRepository;
use App\Repositories\UserRepository;
use App\Services\Platform\FeatureGateService;
use App\Support\AtakReleaseInfo;

final class AtakFirstLinkController
{
    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $userId = (int) Session::get('user_id');
        if ($tenantId < 1 || $userId < 1) {
            return Response::redirect(url('login'));
        }

        /** @var FeatureGateService $gate */
        $gate = Container::get(FeatureGateService::class);
        if (!$gate->allows($tenantId, 'atak')) {
            return Response::view('layout.main', [
                'title' => 'ATAK / Overwatch',
                'content' => 'platform.upgrade',
                'feature' => 'atak',
                'planName' => 'standard',
            ]);
        }

        /** @var UserRepository $users */
        $users = Container::get(UserRepository::class);
        $user = $users->findById($userId, $tenantId) ?: [];

        $steamId = trim((string) ($user['steam_id'] ?? ''));
        $callsign = trim((string) ($user['callsign'] ?? ''));
        $displayName = trim((string) ($user['display_name'] ?? ''));
        $hasSteam = $steamId !== '';
        $hasIdentity = $callsign !== '' || $displayName !== '';

        $modPath = dirname(__DIR__, 3) . '/storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
        $hasMod = is_file($modPath) && is_readable($modPath);

        /** @var TenantAtakConfigRepository $atakConfigRepo */
        $atakConfigRepo = Container::get(TenantAtakConfigRepository::class);

        /** @var TacticalGameLinkRepository $gameLinks */
        $gameLinks = Container::get(TacticalGameLinkRepository::class);
        $gameLinkReady = $gameLinks->isReady();
        $hasAccessKey = trim($atakConfigRepo->getAccessKey($tenantId)) !== '';

        $accountReady = $hasSteam && $hasIdentity;
        $portalUrl = rtrim(url(''), '/');
        // Mêmes versions que GET /api/atak/version, lu par le mod au démarrage.
        $release = AtakReleaseInfo::latest();
        $en = function_exists('locale') && locale() === 'en';

        return Response::view('layout.main', [
            'title' => 'Première liaison ATAK',
            // Les balises meta ne passent pas par HtmlTranslator : texte choisi selon la langue ici.
            'meta_description' => $en
                ? 'Link the native ATAK phone in Arma 3 to your Athena account: install the mod, take out the phone, sign in, check the link and troubleshoot.'
                : 'Relier le téléphone ATAK natif d’Arma 3 à votre compte Athena : installer le mod, sortir le téléphone, se connecter, vérifier la liaison et dépanner.',
            'og_description' => $en
                ? 'Step-by-step guide: your in-game ATAK phone linked to Athena in five steps, with no key or address to type.'
                : 'Guide pas à pas : votre téléphone ATAK en jeu relié à Athena en cinq étapes, sans clé ni adresse à saisir.',
            'og_image' => 'assets/atak-native/capture-launch.jpg',
            'og_image_alt' => $en
                ? 'Native ATAK phone in Arma 3, apps screen'
                : 'Téléphone ATAK natif dans Arma 3, écran des applications',
            'content' => 'atak.first_link',
            'isBackOfficeShell' => str_starts_with($request->path(), '/back-office/ma-situation'),
            'boPageGroup' => 'Opérateur',
            'boPageKicker' => 'OPÉRATEUR · LIAISON',
            'boPageTitle' => 'Configurer ATAK',
            'boPageSubtitle' => 'Première liaison du téléphone ou du poste de jeu à votre compte Athena.',
            'firstLink' => [
                'display_name' => $displayName,
                'callsign' => $callsign,
                'steam_linked' => $hasSteam,
                'has_identity' => $hasIdentity,
                'account_ready' => $accountReady,
                'has_mod' => $hasMod,
                'has_access_key' => $hasAccessKey,
                'mod_page_url' => url('atak/mod'),
                'mod_download_url' => $hasMod ? url('atak/mod/download') : null,
                'tuto_url' => url('atak/tuto'),
                'guide_url' => url('atak/mod/guide'),
                'account_url' => url('account/preferences'),
                'atak_url' => url('atak'),
                'operateurs_url' => url('back-office/atak/operateurs'),
                'portal_url' => $portalUrl,
                'game_link_ready' => $gameLinkReady,
                'game_link_url' => url('atak/game-link'),
                'lier_url' => url('lier'),
                'version_api_url' => url('api/atak/version'),
                'atak_version' => (string) ($release['atak_native'] ?? ''),
                'link_version' => (string) ($release['link'] ?? ''),
                'can_view_operators' => function_exists('can') && (
                    can('admin.system') || can('admin.organization') || can('admin.access')
                ),
            ],
            'steps' => [],
        ]);
    }
}
