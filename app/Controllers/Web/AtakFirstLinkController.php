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

        return Response::view('layout.main', [
            'title' => 'Première liaison ATAK',
            'content' => 'atak.first_link',
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
                'can_view_operators' => function_exists('can') && (
                    can('admin.system') || can('admin.organization') || can('admin.access')
                ),
            ],
            'steps' => [],
        ]);
    }
}
