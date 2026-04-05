<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Organization;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Services\Auth\AuthService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\Community\TenantOnboardingHealthService;
use App\Services\Community\TenantSlugService;

final class OrganizationCommunityController
{
    public function __construct(
        private AuthService $authService,
        private TenantRepository $tenantRepository,
        private TenantCommunityProfileService $communityProfileService
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

    public function presentation(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];

        return Response::view('layout.main', [
            'title' => 'Fiche registre & contact',
            'content' => 'admin.organization.community_presentation',
            'tenant' => $tenant,
            'community' => $community,
        ]);
    }

    public function presentationUpdate(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/community/presentation'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $settings = $this->tenantRepository->getSettings($tenantId);
        $existing = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $built = $this->communityProfileService->normalizeFromRequest($request, $existing);
        $this->tenantRepository->mergeSettings($tenantId, ['community' => $built]);
        Session::flash('success', 'Fiche registre et contact enregistrées.');

        return Response::redirect(url('back-office/community/presentation'));
    }

    public function settingsUpdate(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/community'));
        }
        $tenantId = (int) Session::get('tenant_id');
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            return Response::redirect(url('dashboard'));
        }
        $newName = trim((string) $request->input('tenant_name'));
        if ($newName === '') {
            Session::flash('error', 'Le nom affiché est obligatoire.');

            return Response::redirect(url('back-office/community'));
        }
        $this->tenantRepository->updateName($tenantId, $newName);

        $newSlug = strtolower(trim((string) $request->input('tenant_slug')));
        $oldSlug = (string) ($tenant['slug'] ?? '');
        if ($newSlug === '') {
            Session::flash('error', 'Le slug URL est obligatoire.');

            return Response::redirect(url('back-office/community'));
        }
        if ($newSlug !== $oldSlug) {
            if (!TenantSlugService::isValidFormat($newSlug)) {
                Session::flash('error', 'Le slug URL est invalide (lettres minuscules, chiffres, tirets, max. 50 caractères).');

                return Response::redirect(url('back-office/community'));
            }
            if (TenantSlugService::isReserved($newSlug)) {
                Session::flash('error', 'Ce slug URL est réservé.');

                return Response::redirect(url('back-office/community'));
            }
            if ($this->tenantRepository->isSlugTakenByOther($tenantId, $newSlug)) {
                Session::flash('error', 'Ce slug URL est déjà utilisé par une autre communauté.');

                return Response::redirect(url('back-office/community'));
            }
            $this->tenantRepository->updateSlug($tenantId, $newSlug);
        }

        $raw = trim((string) $request->input('community_code'));
        if ($raw === '') {
            $this->tenantRepository->updateCommunityCode($tenantId, null);
            Session::flash('success', 'Paramètres enregistrés.');

            return Response::redirect(url('back-office/community'));
        }
        $norm = TenantRepository::normalizeCommunityCode($raw);
        if (strlen($norm) < 3 || strlen($norm) > 64) {
            Session::flash('error', 'Le code doit faire entre 3 et 64 caractères (lettres, chiffres, tirets).');

            return Response::redirect(url('back-office/community'));
        }
        if ($this->isReservedCommunityCode($norm)) {
            Session::flash('error', 'Ce code est réservé.');

            return Response::redirect(url('back-office/community'));
        }
        if ($this->tenantRepository->isCommunityCodeTaken($norm, $tenantId)) {
            Session::flash('error', 'Ce code est déjà utilisé.');

            return Response::redirect(url('back-office/community'));
        }
        $this->tenantRepository->updateCommunityCode($tenantId, $norm);
        Session::flash('success', 'Paramètres enregistrés.');

        return Response::redirect(url('back-office/community'));
    }

    /** Assistant de rattrapage onboarding (communautés créées avant le wizard v2). */
    public function onboardingRecovery(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('dashboard'));
        }
        $health = (new TenantOnboardingHealthService($this->tenantRepository))->analyze($tenantId);

        return Response::view('layout.main', [
            'title' => 'Rattrapage configuration',
            'content' => 'admin.organization.onboarding_recovery',
            'health' => $health,
        ]);
    }

    public function onboardingRecoveryApply(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('back-office/onboarding-recovery'));
        }
        $tenantId = (int) Session::get('tenant_id');
        if (!$tenantId) {
            return Response::redirect(url('dashboard'));
        }
        try {
            (new TenantOnboardingHealthService($this->tenantRepository))->applyFrDefaults($tenantId);
            $this->tenantRepository->mergeSettings($tenantId, [
                'onboarding_wizard_version' => 2,
            ]);
            Session::flash('success', 'Valeurs par défaut appliquées (référentiel FR, ORBAT minimal si vide). Les données existantes n’ont pas été supprimées.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        return Response::redirect(url('back-office/onboarding-recovery'));
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
