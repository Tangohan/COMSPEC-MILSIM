<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\BlockedIndicatorRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Moderation\IndicatorBlocklistService;

/**
 * Blocages Steam / adresse réseau appliqués au mod Arma (Overwatch / ATAK).
 */
final class AdminAtakModBlocklistController
{
    public function __construct(
        private AuthService $authService,
        private IndicatorBlocklistService $indicatorBlocklistService,
        private BlockedIndicatorRepository $blockedIndicatorRepository,
        private UserRepository $userRepository,
    ) {}

    public function index(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $rows = $tenantId > 0
            ? $this->blockedIndicatorRepository->listActiveModBlocksForTenant($tenantId, 200)
            : [];

        return Response::view('layout.main', [
            'title' => 'Restrictions d’accès au mod',
            'content' => 'admin.atak-mod-blocks.index',
            'blockRows' => $rows,
            'tenantId' => $tenantId,
        ]);
    }

    public function add(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/atak-mod-blocks'));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url('admin/atak-mod-blocks'));
        }

        $kind = trim((string) $request->input('block_kind'));
        $raw = trim((string) $request->input('block_value'));
        $reason = trim((string) $request->input('block_reason'));
        $durationMode = $request->input('block_duration_mode') === 'temporary' ? 'temporary' : 'permanent';
        $expiresAt = null;
        if ($durationMode === 'temporary') {
            $days = max(1, min(3650, (int) $request->input('block_duration_days')));
            $expiresAt = (new \DateTimeImmutable())->modify('+' . $days . ' days');
        }

        try {
            if ($kind === 'steam') {
                $this->indicatorBlocklistService->addSteamBlock(
                    (int) $actor['id'],
                    'tenant',
                    $tenantId,
                    $raw,
                    $reason !== '' ? $reason : null,
                    $expiresAt
                );
            } elseif ($kind === 'ip') {
                $this->indicatorBlocklistService->addIpBlock(
                    (int) $actor['id'],
                    'tenant',
                    $tenantId,
                    $raw,
                    $reason !== '' ? $reason : null,
                    $expiresAt
                );
            } else {
                Session::flash('error', 'Choisissez le type de restriction (Steam ou adresse réseau).');

                return Response::redirect(url('admin/atak-mod-blocks'));
            }
            Session::flash('success', 'Restriction enregistrée. Le mod refusera désormais cet accès pour votre communauté.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('admin/atak-mod-blocks'));
    }

    public function revoke(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée. Réessayez.');

            return Response::redirect(url('admin/atak-mod-blocks'));
        }
        $actor = $this->authService->user();
        if (!$actor) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $id = (int) $request->input('indicator_id');
        if ($id < 1 || $tenantId < 1) {
            Session::flash('error', 'Entrée invalide.');

            return Response::redirect(url('admin/atak-mod-blocks'));
        }

        $row = $this->blockedIndicatorRepository->findById($id);
        if (!is_array($row)) {
            Session::flash('error', 'Entrée introuvable.');

            return Response::redirect(url('admin/atak-mod-blocks'));
        }
        $type = (string) ($row['indicator_type'] ?? '');
        if (!in_array($type, ['steam', 'ip'], true)) {
            Session::flash('error', 'Cette entrée ne concerne pas le mod.');

            return Response::redirect(url('admin/atak-mod-blocks'));
        }
        // Les admins communauté ne lèvent que leurs entrées locales (pas les globales plateforme).
        if ((string) ($row['scope'] ?? '') !== 'tenant' || (int) ($row['tenant_id'] ?? 0) !== $tenantId) {
            Session::flash('error', 'Vous ne pouvez lever que les restrictions de votre communauté. Contactez l’équipe site pour une restriction globale.');

            return Response::redirect(url('admin/atak-mod-blocks'));
        }

        if ($this->indicatorBlocklistService->revokeIndicator((int) $actor['id'], $id, $tenantId)) {
            Session::flash('success', 'Restriction levée.');
        } else {
            Session::flash('error', 'Impossible de lever cette restriction (déjà close).');
        }

        return Response::redirect(url('admin/atak-mod-blocks'));
    }

    public function searchMembers(Request $request, array $params = []): Response
    {
        $tenantId = (int) Session::get('tenant_id');
        $rows = $this->userRepository->searchMembersForModBlocklist(
            $tenantId,
            (string) $request->query('q', ''),
            20
        );
        $users = [];
        foreach ($rows as $u) {
            $steam = trim((string) ($u['steam_id'] ?? ''));
            $users[] = [
                'id' => (int) ($u['id'] ?? 0),
                'display_name' => trim((string) ($u['display_name'] ?? '')),
                'callsign' => trim((string) ($u['callsign'] ?? '')),
                'email' => (string) ($u['email'] ?? ''),
                'steam_id' => $steam,
                'has_steam' => $steam !== '',
            ];
        }

        return Response::json(['users' => $users]);
    }
}
