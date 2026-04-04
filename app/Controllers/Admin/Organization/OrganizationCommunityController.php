<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\TenantSlugService;

final class OrganizationCommunityController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository
    ) {}

    public function settings(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'title' => 'Identité communauté',
            'content' => 'admin.organization.community_code',
            'tenant' => $tenant,
        ]);
    }

    public function settingsUpdate(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('admin/organization/community'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $newName = trim((string) $request->input('tenant_name'));
        if ($newName === '') {
            Session::flash('error', 'Le nom affiché est obligatoire.');

            return Response::redirect(url('admin/organization/community'));
        }
        $this->tenantRepository->updateName($tenantId, $newName);

        $newSlug = strtolower(trim((string) $request->input('tenant_slug')));
        $oldSlug = (string) ($tenant['slug'] ?? '');
        if ($newSlug === '') {
            Session::flash('error', 'Le slug URL est obligatoire.');

            return Response::redirect(url('admin/organization/community'));
        }
        if ($newSlug !== $oldSlug) {
            if (!TenantSlugService::isValidFormat($newSlug)) {
                Session::flash('error', 'Le slug URL est invalide (lettres minuscules, chiffres, tirets, max. 50 caractères).');

                return Response::redirect(url('admin/organization/community'));
            }
            if (TenantSlugService::isReserved($newSlug)) {
                Session::flash('error', 'Ce slug URL est réservé.');

                return Response::redirect(url('admin/organization/community'));
            }
            if ($this->tenantRepository->isSlugTakenByOther($tenantId, $newSlug)) {
                Session::flash('error', 'Ce slug URL est déjà utilisé par une autre communauté.');

                return Response::redirect(url('admin/organization/community'));
            }
            $this->tenantRepository->updateSlug($tenantId, $newSlug);
        }

        $raw = trim((string) $request->input('community_code'));
        if ($raw === '') {
            $this->tenantRepository->updateCommunityCode($tenantId, null);
            Session::flash('success', 'Paramètres enregistrés.');

            return Response::redirect(url('admin/organization/community'));
        }
        $norm = TenantRepository::normalizeCommunityCode($raw);
        if (strlen($norm) < 3 || strlen($norm) > 64) {
            Session::flash('error', 'Le code doit faire entre 3 et 64 caractères (lettres, chiffres, tirets).');

            return Response::redirect(url('admin/organization/community'));
        }
        if ($this->isReservedCommunityCode($norm)) {
            Session::flash('error', 'Ce code est réservé.');

            return Response::redirect(url('admin/organization/community'));
        }
        if ($this->tenantRepository->isCommunityCodeTaken($norm, $tenantId)) {
            Session::flash('error', 'Ce code est déjà utilisé.');

            return Response::redirect(url('admin/organization/community'));
        }
        $this->tenantRepository->updateCommunityCode($tenantId, $norm);
        Session::flash('success', 'Paramètres enregistrés.');

        return Response::redirect(url('admin/organization/community'));
    }

    private function isReservedCommunityCode(string $normalized): bool
    {
        $reserved = [
            'JOIN', 'LOGIN', 'REGISTER', 'API', 'ADMIN', 'C', 'DASHBOARD', 'HUB', 'FORUM', 'SYSTEM',
            'DEFAULT', 'WWW', 'ENLISTMENT', 'COMMUNITIES', 'INVITATIONS', 'LOGOUT', 'ACCOUNT', 'ATAK',
        ];

        return in_array($normalized, $reserved, true);
    }
}
