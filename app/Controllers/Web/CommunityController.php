<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Services\Community\TenantBootstrapService;
use App\Services\Rbac\RbacService;

class CommunityController
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private AuthService $authService,
        private TenantBootstrapService $bootstrapService,
        private RbacService $rbacService
    ) {}

    /** Page publique d’une communauté (slug). */
    public function show(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            return Response::view('errors.404', ['title' => 'Communauté introuvable'])->setStatusCode(404);
        }
        $settings = [];
        $rawSettings = $tenant['settings'] ?? null;
        if (is_string($rawSettings) && trim($rawSettings) !== '') {
            $decoded = json_decode($rawSettings, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        $communityConfig = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $memberships = [];
        if ($this->authService->check()) {
            $email = Session::get('email');
            if ($email) {
                $memberships = $this->userRepository->listTenantsForEmail((string) $email);
            }
        }

        return Response::view('layout.main', [
            'title' => $tenant['name'],
            'content' => 'community.show',
            'tenant' => $tenant,
            'memberships' => $memberships,
            'communityConfig' => $communityConfig,
        ]);
    }

    /**
     * Accès forum dans le contexte d’une communauté : bascule la session si l’utilisateur y a un compte.
     */
    public function enterForum(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            Session::flash('error', 'Communauté introuvable.');
            return Response::redirect(url(''));
        }
        if (!$this->authService->check()) {
            Session::flash('error', 'Connectez-vous pour accéder au forum de cette communauté.');
            return Response::redirect(url('login') . '?tenant_slug=' . rawurlencode($slug));
        }
        if (!$this->authService->switchToTenant((int) $tenant['id'])) {
            Session::flash('error', 'Vous n’avez pas de compte dans cette communauté. Demandez une invitation ou créez une communauté.');
            return Response::redirect(url('c/' . $slug));
        }
        $user = $this->authService->user();
        if ($user && !empty($user['role_id'])) {
            $this->rbacService->setPermissionsForGate((int) $user['role_id']);
        }
        return Response::redirect(url('forum'));
    }

    public function createForm(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            Session::flash('error', 'Connexion requise pour créer une communauté.');
            return Response::redirect(url('login'));
        }
        return Response::view('layout.main', [
            'title' => 'Créer une communauté',
            'content' => 'community.create',
        ]);
    }

    public function create(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('communities/create'));
        }
        $name = trim((string) $request->input('name'));
        $slug = trim((string) $request->input('slug'));
        if ($name === '' || $slug === '') {
            Session::flash('error', 'Nom et slug requis.');
            return Response::redirect(url('communities/create'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        try {
            $result = $this->bootstrapService->createCommunity((int) $user['id'], $name, $slug, [
                'registration_mode' => (string) $request->input('registration_mode', 'milsim'),
                'community_locked' => $request->input('community_locked') ? true : false,
                'require_ai_ack' => $request->input('require_ai_ack') ? true : false,
                'plan_slug' => (string) $request->input('plan_slug', 'free'),
                'welcome_text' => trim((string) $request->input('welcome_text')),
            ]);
            $newUserId = (int) $result['user_id'];
            $tenantId = (int) $result['tenant_id'];
            $u = $this->userRepository->findById($newUserId, $tenantId);
            if ($u) {
                $this->authService->loginUser($u);
                $this->rbacService->setPermissionsForGate((int) ($u['role_id'] ?? 0));
            }
            $audit = \App\Core\Container::get(AuditService::class);
            $audit->log(AuditAction::TENANT_CREATED, $tenantId, $newUserId, 'tenant', $tenantId, null, (string) $name);
            Session::flash('success', 'Communauté créée. Complétez la configuration ci-dessous.');
            $t = $this->tenantRepository->findById($tenantId);
            $newSlug = $t['slug'] ?? $slug;

            return Response::redirect(url('c/' . rawurlencode((string) $newSlug) . '/setup'));
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect(url('communities/create'));
        }
    }

    public function switchTenant(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');
            return Response::redirect(url('dashboard'));
        }
        $tenantId = (int) $request->input('tenant_id');
        if ($tenantId < 1) {
            Session::flash('error', 'Requête invalide.');
            return Response::redirect(url('dashboard'));
        }
        if (!$this->authService->switchToTenant($tenantId)) {
            Session::flash('error', 'Impossible de basculer vers cette communauté.');
            return Response::redirect(url('dashboard'));
        }
        $user = $this->authService->user();
        if ($user && !empty($user['role_id'])) {
            $this->rbacService->setPermissionsForGate((int) $user['role_id']);
        }
        Session::flash('success', 'Communauté active mise à jour.');
        return Response::redirect(url('dashboard'));
    }

    /** Assistant post-création (fuseau, finalisation). */
    public function setupForm(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            Session::flash('error', 'Connexion requise.');

            return Response::redirect(url('login'));
        }
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            return Response::view('errors.404', ['title' => 'Communauté introuvable'])->setStatusCode(404);
        }
        if (!$this->authService->switchToTenant((int) $tenant['id'])) {
            Session::flash('error', 'Vous n’avez pas accès à cette communauté.');

            return Response::redirect(url('c/' . $slug));
        }
        $user = $this->authService->user();
        if ($user && !empty($user['role_id'])) {
            $this->rbacService->setPermissionsForGate((int) $user['role_id']);
        }
        $settings = [];
        if (!empty($tenant['settings'])) {
            $decoded = json_decode((string) $tenant['settings'], true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        if (!empty($settings['onboarding_completed_at'])) {
            return Response::redirect(url('dashboard'));
        }

        return Response::view('layout.main', [
            'title' => 'Configurer la communauté',
            'content' => 'community.setup',
            'tenant' => $tenant,
            'settings' => $settings,
        ]);
    }

    public function setupStore(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url('dashboard'));
        }
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            return Response::redirect(url(''));
        }
        if (!$this->authService->switchToTenant((int) $tenant['id'])) {
            Session::flash('error', 'Accès refusé.');

            return Response::redirect(url('c/' . $slug));
        }
        $tz = trim((string) $request->input('timezone'));
        if ($tz === '') {
            $tz = 'Europe/Paris';
        }
        $this->tenantRepository->mergeSettings((int) $tenant['id'], [
            'timezone' => $tz,
            'onboarding_completed_at' => date('c'),
        ]);
        $user = $this->authService->user();
        if ($user) {
            $audit = \App\Core\Container::get(AuditService::class);
            $audit->log(
                AuditAction::TENANT_SETUP_COMPLETED,
                (int) $tenant['id'],
                (int) $user['id'],
                'tenant',
                (int) $tenant['id'],
                null,
                $tz
            );
        }
        Session::flash('success', 'Configuration enregistrée.');

        return Response::redirect(url('dashboard'));
    }
}
