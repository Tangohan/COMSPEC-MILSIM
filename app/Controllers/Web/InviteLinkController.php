<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\EnlistmentRepository;
use App\Repositories\InviteLinkRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Rbac\RbacService;
use App\Services\Recruitment\InviteLinkJoinService;
use App\Services\Recruitment\InviteLinkService;
use App\Support\PendingInviteLink;

/**
 * Page publique d'un lien d'invitation (/i/<code>) : vitrine de la communauté puis, selon le visiteur,
 * « Candidater » / « Rejoindre » (connecté) ou « Créer mon compte » / « J'ai déjà un compte » (le code suit
 * l'inscription, la confirmation de l'adresse et la connexion, puis ramène ici).
 */
final class InviteLinkController
{
    /** Clics comptés une fois par session et par lien. */
    private const SESSION_CLICKED = 'invite_links_clicked';

    public function __construct(
        private InviteLinkRepository $links,
        private TenantRepository $tenants,
        private TenantBrandingRepository $branding,
        private UserRepository $users,
        private EnlistmentRepository $enlistments,
        private AuthService $authService,
        private RbacService $rbacService,
        private InviteLinkJoinService $joinService,
    ) {
    }

    public function show(Request $request, array $params = []): Response
    {
        $code = strtolower(trim((string) ($params['code'] ?? '')));
        $link = InviteLinkService::isWellFormedCode($code) ? $this->links->findByPublicCode($code) : null;
        $tenant = $link !== null ? $this->tenants->findById((int) $link['tenant_id']) : null;
        if ($link === null || !is_array($tenant)) {
            if ($link === null && InviteLinkService::isWellFormedCode($code) && $this->links->isDeletedPublicCode($code)) {
                if (PendingInviteLink::code() === $code) {
                    PendingInviteLink::forget();
                }

                return $this->unusable(InviteLinkService::unusableMessage(InviteLinkService::STATUS_DELETED), null, 410);
            }

            return $this->unusable(InviteLinkService::unusableMessage('unknown'), null, 404);
        }
        $status = InviteLinkService::status($link);
        if ($status !== InviteLinkService::STATUS_ACTIVE) {
            if (PendingInviteLink::code() === $code) {
                PendingInviteLink::forget();
            }

            return $this->unusable(InviteLinkService::unusableMessage($status), $tenant, 410);
        }

        $this->countClick($link, $request->ip());
        $loggedIn = $this->authService->check();
        $user = $loggedIn ? $this->authService->user() : null;
        $isApply = (string) ($link['link_action'] ?? '') === InviteLinkRepository::ACTION_APPLY;
        $formUrl = $this->enlistmentFormUrl($link, $tenant);

        $memberState = null;
        if (is_array($user)) {
            $memberState = $this->memberState($user, (int) $tenant['id']);
            // Retour après inscription ou connexion : le relais de session a servi ; une candidature reprend
            // directement sur le formulaire, une adhésion attend le clic sur « Rejoindre ».
            if (PendingInviteLink::code() === $code) {
                PendingInviteLink::forget();
                if ($isApply && $memberState === null) {
                    return Response::redirect($formUrl);
                }
            }
        } else {
            PendingInviteLink::remember($code);
        }

        return Response::view('invite_link.landing', [
            'title' => 'Invitation — ' . (string) ($tenant['name'] ?? ''),
            'link' => $link,
            'linkCode' => $code,
            'isApply' => $isApply,
            'community' => $this->showcase($tenant),
            'loggedIn' => is_array($user),
            'memberState' => $memberState,
            'formUrl' => $formUrl,
            'actionUrl' => InviteLinkService::publicUrl($code),
            'registerUrl' => url('register') . '?invite=' . rawurlencode($code),
            'loginUrl' => url('login') . '?redirect=' . rawurlencode('i/' . $code),
        ]);
    }

    /** « Rejoindre » / « Demander à rejoindre » d'un compte connecté. */
    public function act(Request $request, array $params = []): Response
    {
        $code = strtolower(trim((string) ($params['code'] ?? '')));
        $back = InviteLinkService::isWellFormedCode($code) ? InviteLinkService::publicUrl($code) : url('');
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect($back);
        }
        if (!$this->authService->check()) {
            PendingInviteLink::remember($code);

            return Response::redirect(url('login') . '?redirect=' . rawurlencode('i/' . $code));
        }
        $link = InviteLinkService::isWellFormedCode($code) ? $this->links->findByPublicCode($code) : null;
        $tenant = $link !== null ? $this->tenants->findById((int) $link['tenant_id']) : null;
        if ($link === null || !is_array($tenant)) {
            return Response::redirect($back);
        }
        PendingInviteLink::forget();
        if ((string) ($link['link_action'] ?? '') === InviteLinkRepository::ACTION_APPLY) {
            return Response::redirect($this->enlistmentFormUrl($link, $tenant));
        }

        $user = $this->authService->user();
        $result = $this->joinService->join($link, is_array($user) ? $user : [], trim($request->ip()));
        switch ($result['result']) {
            case InviteLinkJoinService::RESULT_JOINED:
            case InviteLinkJoinService::RESULT_ALREADY_MEMBER:
                if ($this->authService->switchToTenant((int) $tenant['id'])) {
                    $fresh = $this->authService->user();
                    if (is_array($fresh)) {
                        $this->rbacService->setPermissionsForGateFromUserRow($fresh, $this->users);
                    }
                }
                Session::flash($result['result'] === InviteLinkJoinService::RESULT_JOINED ? 'success' : 'info', $result['message']);

                return Response::redirect(url('dashboard'));
            case InviteLinkJoinService::RESULT_REQUESTED:
            case InviteLinkJoinService::RESULT_ALREADY_REQUESTED:
                Session::flash('success', $result['message']);

                return Response::redirect($back);
            default:
                Session::flash('error', $result['message']);

                return Response::redirect($back);
        }
    }

    /**
     * @param array<string, mixed> $user
     * @return 'member'|'pending'|null
     */
    private function memberState(array $user, int $tenantId): ?string
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        foreach ($email !== '' ? $this->users->listTenantsForEmail($email) : [] as $row) {
            if ((int) ($row['tenant_id'] ?? 0) === $tenantId) {
                return 'member';
            }
        }
        $uid = (int) ($user['id'] ?? 0);
        if ($uid > 0 && $this->enlistments->findOngoingSubmittedForAccount($tenantId, $uid, $email) !== null) {
            return 'pending';
        }

        return null;
    }

    /** @param array<string, mixed> $link */
    private function countClick(array $link, string $ip): void
    {
        $seen = Session::get(self::SESSION_CLICKED);
        $seen = is_array($seen) ? $seen : [];
        $id = (int) $link['id'];
        if (in_array($id, $seen, true)) {
            return;
        }
        $seen[] = $id;
        Session::set(self::SESSION_CLICKED, array_slice($seen, -50));
        $uid = (int) (Session::get('user_id') ?? 0);
        $this->links->recordEvent((int) $link['tenant_id'], $id, InviteLinkRepository::EVENT_CLICK, $uid > 0 ? $uid : null, null, trim($ip));
    }

    /**
     * @param array<string, mixed> $link
     * @param array<string, mixed> $tenant
     */
    private function enlistmentFormUrl(array $link, array $tenant): string
    {
        $slug = trim((string) ($tenant['slug'] ?? ''));
        $base = $slug !== '' ? url('c/' . rawurlencode($slug) . '/enlistment') : url('enlistment');
        $query = ['lien' => (string) $link['public_code']];
        $opening = (int) ($link['assign_to_opening_id'] ?? 0);
        if ($opening > 0) {
            $query['ouverture'] = $opening;
        }

        return $base . '?' . http_build_query($query) . '#candidature';
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array{name: string, slug: string, logo: string, banner: string, game: string, tagline: string, members: int, public_url: string}
     */
    private function showcase(array $tenant): array
    {
        $tid = (int) $tenant['id'];
        $settings = json_decode((string) ($tenant['settings'] ?? ''), true);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $brand = $this->branding->mergeWithTenantLogo($tenant, $this->branding->findByTenantId($tid));
        $meta = TenantCommunityProfileService::registryCardMeta($community);
        $members = 0;
        try {
            $members = $this->users->countActiveMembers($tid);
        } catch (\Throwable) {
        }
        $slug = trim((string) ($tenant['slug'] ?? ''));
        $name = trim((string) ($tenant['name'] ?? ''));

        return [
            'name' => function_exists('name_mask_text') ? name_mask_text($name) : $name,
            'slug' => $slug,
            'logo' => self::mediaUrl((string) ($brand['logo_url'] ?? '')),
            'banner' => self::mediaUrl((string) ($brand['banner_url'] ?? '')),
            'game' => trim((string) ($community['game_label'] ?? '')),
            'tagline' => (string) ($meta['tagline'] ?? ''),
            'members' => $members,
            'public_url' => $slug !== '' ? url('c/' . rawurlencode($slug)) : '',
        ];
    }

    /** Image de marque : adresse complète gardée, chemin du site résolu (comme la vitrine publique). */
    private static function mediaUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || preg_match('#^(https?:)?//#i', $raw) === 1) {
            return $raw;
        }

        return asset_url(ltrim($raw, '/'));
    }

    /** @param array<string, mixed>|null $tenant */
    private function unusable(string $message, ?array $tenant, int $status): Response
    {
        return Response::view('invite_link.landing', [
            'title' => 'Lien d’invitation',
            'unusableMessage' => $message,
            'community' => $tenant !== null ? $this->showcase($tenant) : null,
        ])->setStatusCode($status);
    }
}
