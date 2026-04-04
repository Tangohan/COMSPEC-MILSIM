<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;

final class JoinController
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private AuthService $authService
    ) {}

    public function show(Request $request, array $params = []): Response
    {
        $prefill = trim((string) $request->query('code'));

        return Response::view('auth.join', [
            'title' => 'Rejoindre une communauté',
            'prefill_code' => $prefill,
        ]);
    }

    public function resolve(Request $request, array $params = []): Response
    {
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('join'));
        }
        $raw = trim((string) $request->input('community_code'));
        $norm = TenantRepository::normalizeCommunityCode($raw);
        if (strlen($norm) < 3) {
            Session::flash('error', 'Code invalide.');

            return Response::redirect(url('join'));
        }
        $tenant = $this->tenantRepository->findByCommunityCode($norm);
        if (!$tenant) {
            Session::flash('error', 'Code invalide.');

            return Response::redirect(url('join'));
        }
        $slug = (string) ($tenant['slug'] ?? '');
        if ($slug === '') {
            Session::flash('error', 'Code invalide.');

            return Response::redirect(url('join'));
        }
        if ($this->authService->check()) {
            return Response::redirect(url('c/' . rawurlencode($slug)));
        }

        return Response::redirect(
            url('register')
                . '?community_code=' . rawurlencode($norm)
                . '&tenant_slug=' . rawurlencode($slug)
        );
    }
}
