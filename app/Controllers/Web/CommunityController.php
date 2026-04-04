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
use App\Repositories\PendingCommunityCreateRepository;
use App\Repositories\ReferralRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Services\Billing\StripeCheckoutService;
use App\Services\Community\TenantBootstrapService;
use App\Services\Rbac\RbacService;

class CommunityController
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private AuthService $authService,
        private TenantBootstrapService $bootstrapService,
        private RbacService $rbacService,
        private ReferralRepository $referralRepository,
        private PendingCommunityCreateRepository $pendingCommunityRepository,
        private StripeCheckoutService $stripeCheckoutService,
        private SubscriptionPlanRepository $subscriptionPlanRepository
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
            return Response::redirect(url('login'));
        }
        if (!$this->authService->switchToTenant((int) $tenant['id'])) {
            Session::flash('error', 'Vous n’avez pas de compte dans cette communauté. Demandez une invitation ou créez une communauté.');
            return Response::redirect(url('c/' . $slug));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGate(
                !empty($user['role_id']) ? (int) $user['role_id'] : null,
                (string) ($user['email'] ?? '')
            );
        }
        return Response::redirect(url('forum'));
    }

    public function createForm(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            Session::flash('error', 'Connexion requise pour créer une communauté.');
            return Response::redirect(url('login'));
        }
        $ref = trim((string) $request->query('ref'));
        if ($ref !== '') {
            Session::set('pending_referrer_code', $ref);
        }
        $plans = $this->subscriptionPlanRepository->allOrdered();
        $paidPlans = array_values(array_filter($plans, static fn ($p) => in_array((string) ($p['slug'] ?? ''), ['standard', 'pro'], true)));
        $stripeConfigured = (getenv('STRIPE_SECRET_KEY') ?: '') !== '';

        return Response::view('layout.main', [
            'title' => 'Créer une communauté',
            'content' => 'community.create',
            'paidPlans' => $paidPlans,
            'stripeConfigured' => $stripeConfigured,
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
        if ($name === '') {
            Session::flash('error', 'Le nom de la communauté est requis.');
            return Response::redirect(url('communities/create'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $planChoice = trim((string) $request->input('plan_choice', 'free'));
        try {
            $paid = $this->parsePaidPlanChoice($planChoice);
            $referrerUserId = $this->resolveReferrerUserId((int) $user['id']);
            $optionsBase = [
                'registration_mode' => (string) $request->input('registration_mode', 'milsim'),
                'community_locked' => $request->input('community_locked') ? true : false,
                'require_ai_ack' => $request->input('require_ai_ack') ? true : false,
                'welcome_text' => trim((string) $request->input('welcome_text')),
                'referrer_user_id' => $referrerUserId,
            ];

            if ($paid !== null) {
                [$planSlug, $interval] = $paid;
                if ((getenv('STRIPE_SECRET_KEY') ?: '') === '') {
                    Session::flash('error', 'Paiement indisponible : STRIPE_SECRET_KEY n’est pas configuré sur le serveur.');
                    return Response::redirect(url('communities/create'));
                }
                $planRow = $this->subscriptionPlanRepository->findBySlug($planSlug);
                if (!$planRow) {
                    Session::flash('error', 'Plan d’abonnement introuvable.');
                    return Response::redirect(url('communities/create'));
                }
                $priceId = $this->stripePriceIdForInterval($planRow, $interval);
                if ($priceId === null) {
                    Session::flash('error', 'Ce prix Stripe n’est pas configuré pour cette formule (vérifiez les Price IDs en base ou en Stripe).');
                    return Response::redirect(url('communities/create'));
                }
                $payload = json_encode([
                    'name' => $name,
                    'slug' => $slug,
                    'options' => $optionsBase,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $token = bin2hex(random_bytes(32));
                $this->pendingCommunityRepository->create($token, (int) $user['id'], $payload, $planSlug, $priceId);

                return Response::redirect(url('communities/create/pay?token=' . rawurlencode($token)));
            }

            $result = $this->bootstrapService->createCommunity((int) $user['id'], $name, $slug, array_merge($optionsBase, [
                'plan_slug' => 'free',
            ]));
            Session::forget('pending_referrer_code');

            return $this->finalizeFreeCommunityCreation($name, $slug, $result);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect(url('communities/create'));
        }
    }

    /** Redirection vers Stripe Checkout (paiement obligatoire pour Standard / Pro). */
    public function pay(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $token = trim((string) $request->query('token'));
        if ($token === '') {
            Session::flash('error', 'Lien de paiement invalide.');
            return Response::redirect(url('communities/create'));
        }
        $row = $this->pendingCommunityRepository->findByToken($token);
        $user = $this->authService->user();
        if (!$row || !$user || (int) $row['user_id'] !== (int) $user['id'] || !empty($row['tenant_id'])) {
            Session::flash('error', 'Demande de paiement introuvable ou déjà traitée.');
            return Response::redirect(url('communities/create'));
        }
        try {
            $successUrl = url('communities/create/complete') . '?session_id={CHECKOUT_SESSION_ID}';
            $cancelUrl = url('communities/create');
            $email = (string) ($user['email'] ?? Session::get('email') ?? '');
            $session = $this->stripeCheckoutService->createSubscriptionCheckoutSession(
                (string) $row['stripe_price_id'],
                $successUrl,
                $cancelUrl,
                $email !== '' ? $email : null,
                [
                    'pending_community_token' => $token,
                    'plan_slug' => (string) $row['plan_slug'],
                ]
            );
            $this->pendingCommunityRepository->updateStripeSessionId($token, $session['id']);

            return Response::redirect($session['url']);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
            return Response::redirect(url('communities/create'));
        }
    }

    /** Après retour Stripe : connexion au nouveau tenant et assistant de configuration. */
    public function createComplete(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $sessionId = trim((string) $request->query('session_id'));
        if ($sessionId === '') {
            Session::flash('error', 'Session de paiement manquante.');
            return Response::redirect(url('communities/create'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }
        $pending = $this->pendingCommunityRepository->findByStripeCheckoutSessionId($sessionId);
        if (!$pending || (int) $pending['user_id'] !== (int) $user['id']) {
            Session::flash('error', 'Paiement non associé à votre compte.');
            return Response::redirect(url('communities/create'));
        }
        if (empty($pending['tenant_id'])) {
            return Response::view('layout.main', [
                'title' => 'Paiement en cours de validation',
                'content' => 'community.create_pending',
                'sessionId' => $sessionId,
            ]);
        }

        return $this->loginAndRedirectToNewCommunity($pending, (string) $user['email']);
    }

    /**
     * @param array<string, mixed> $pending
     */
    private function loginAndRedirectToNewCommunity(array $pending, string $email): Response
    {
        $tenantId = (int) $pending['tenant_id'];
        $tenant = $this->tenantRepository->findById($tenantId);
        if (!$tenant) {
            Session::flash('error', 'Communauté introuvable après paiement.');
            return Response::redirect(url('communities/create'));
        }
        $u = $this->userRepository->findByEmail($tenantId, strtolower(trim($email)));
        if (!$u) {
            Session::flash('error', 'Compte administrateur introuvable dans la nouvelle communauté.');
            return Response::redirect(url('communities/create'));
        }
        $this->authService->loginUser($u);
        $this->rbacService->setPermissionsForGate(
            !empty($u['role_id']) ? (int) $u['role_id'] : null,
            (string) ($u['email'] ?? '')
        );
        $this->pendingCommunityRepository->deleteById((int) $pending['id']);
        Session::forget('pending_referrer_code');
        Session::flash('success', 'Paiement confirmé. Complétez la configuration de votre communauté.');
        $slug = (string) ($tenant['slug'] ?? '');

        return Response::redirect(url('c/' . rawurlencode($slug) . '/setup'));
    }

    /**
     * @param array{tenant_id: int, user_id: int} $result
     */
    private function finalizeFreeCommunityCreation(string $name, string $slugInput, array $result): Response
    {
        Session::forget('pending_referrer_code');
        $newUserId = (int) $result['user_id'];
        $tenantId = (int) $result['tenant_id'];
        $u = $this->userRepository->findById($newUserId, $tenantId);
        if ($u) {
            $this->authService->loginUser($u);
            $this->rbacService->setPermissionsForGate(
                !empty($u['role_id']) ? (int) $u['role_id'] : null,
                (string) ($u['email'] ?? '')
            );
        }
        $audit = \App\Core\Container::get(AuditService::class);
        $audit->log(AuditAction::TENANT_CREATED, $tenantId, $newUserId, 'tenant', $tenantId, null, (string) $name);
        Session::flash('success', 'Communauté créée. Complétez la configuration ci-dessous.');
        $t = $this->tenantRepository->findById($tenantId);
        $newSlug = $t['slug'] ?? $slugInput;

        return Response::redirect(url('c/' . rawurlencode((string) $newSlug) . '/setup'));
    }

    private function resolveReferrerUserId(int $currentUserId): ?int
    {
        $pendingRef = trim((string) Session::get('pending_referrer_code', ''));
        if ($pendingRef === '') {
            return null;
        }
        $rid = $this->referralRepository->findUserIdByReferralCode($pendingRef);
        if ($rid !== null && $rid !== $currentUserId) {
            return $rid;
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null null = gratuit */
    private function parsePaidPlanChoice(string $planChoice): ?array
    {
        if ($planChoice === 'free') {
            return null;
        }
        $parts = explode('|', $planChoice, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Choix de formule invalide.');
        }
        $slug = strtolower(trim($parts[0]));
        $interval = strtolower(trim($parts[1]));
        if (!in_array($slug, ['standard', 'pro'], true) || !in_array($interval, ['monthly', 'yearly'], true)) {
            throw new \InvalidArgumentException('Choix de formule invalide.');
        }

        return [$slug, $interval];
    }

    /** @param array<string, mixed> $planRow */
    private function stripePriceIdForInterval(array $planRow, string $interval): ?string
    {
        if ($interval === 'monthly') {
            $id = trim((string) ($planRow['stripe_price_id_monthly'] ?? ''));

            return $id !== '' ? $id : null;
        }
        if ($interval === 'yearly') {
            $id = trim((string) ($planRow['stripe_price_id_yearly'] ?? ''));

            return $id !== '' ? $id : null;
        }

        return null;
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
        if ($user) {
            $this->rbacService->setPermissionsForGate(
                !empty($user['role_id']) ? (int) $user['role_id'] : null,
                (string) ($user['email'] ?? '')
            );
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
        if ($user) {
            $this->rbacService->setPermissionsForGate(
                !empty($user['role_id']) ? (int) $user['role_id'] : null,
                (string) ($user['email'] ?? '')
            );
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
