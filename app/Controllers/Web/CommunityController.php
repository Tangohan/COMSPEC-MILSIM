<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\CommunityMediaRepository;
use App\Repositories\TenantBrandingRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UnitRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\AuthService;
use App\Services\Audit\AuditAction;
use App\Services\Audit\AuditService;
use App\Repositories\GradeRepository;
use App\Repositories\PendingCommunityCreateRepository;
use App\Repositories\ReferralRepository;
use App\Repositories\SubscriptionPlanRepository;
use App\Services\Billing\StripeCheckoutService;
use App\Services\Community\CommunityOnboardingValidationService;
use App\Services\Community\CommunityWizardUploadService;
use App\Services\Community\TenantBootstrapService;
use App\Services\Community\TenantCommunityProfileService;
use App\Services\EmailService;
use App\Services\Rbac\RbacService;
use App\Services\Recruitment\TenantRecruitmentSettings;
use App\Services\Analytics\AnalyticsEventCategory;
use App\Services\Analytics\AnalyticsEventName;
use App\Services\Analytics\AnalyticsEventService;
use App\Services\Analytics\AnalyticsSubjectType;

class CommunityController
{
    public function __construct(
        private TenantRepository $tenantRepository,
        private UserRepository $userRepository,
        private UnitRepository $unitRepository,
        private AuthService $authService,
        private TenantBootstrapService $bootstrapService,
        private RbacService $rbacService,
        private ReferralRepository $referralRepository,
        private PendingCommunityCreateRepository $pendingCommunityRepository,
        private StripeCheckoutService $stripeCheckoutService,
        private SubscriptionPlanRepository $subscriptionPlanRepository,
        private EmailService $emailService,
        private CommunityWizardUploadService $communityWizardUploadService,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private AnalyticsEventService $analyticsEventService,
        private CommunityMediaRepository $communityMediaRepository,
        private TenantBrandingRepository $tenantBrandingRepository,
    ) {}

    /** Registre des unités / communautés (hors tenant placeholder). */
    public function registry(Request $request, array $params = []): Response
    {
        $tenants = $this->tenantRepository->listForRegistry();

        return Response::view('layout.main', [
            'title' => 'Unités & communautés',
            'content' => 'community.registry',
            'registryTenants' => $tenants,
        ]);
    }

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
        $hasMembershipInTenant = false;
        $tid = (int) ($tenant['id'] ?? 0);
        foreach ($memberships as $m) {
            if ((int) ($m['tenant_id'] ?? 0) === $tid) {
                $hasMembershipInTenant = true;
                break;
            }
        }
        $forumMembersOnly = !empty($communityConfig['forum_members_only']);
        $showForumCta = !$forumMembersOnly || ($this->authService->check() && $hasMembershipInTenant);
        $communityProfile = TenantCommunityProfileService::getPublicViewModel($communityConfig, (string) ($tenant['slug'] ?? ''));

        $publicLayout = TenantCommunityProfileService::resolvePublicPageLayout($communityConfig['public_page_layout'] ?? null);
        $showcaseVm = null;
        $publicUnits = [];
        $publicRosterRows = [];
        $unitMemberCounts = [];
        $commanderNames = [];

        if ($publicLayout === 'showcase') {
            $effectif = $this->userRepository->countActiveMembers($tid);
            $unitsPublic = $this->unitRepository->countPublicForTenant($tid);
            $activityPct = $this->userRepository->activityRateLast30DaysPercent($tid);
            $rosterCount = $this->userRepository->countPublicRosterOptIn($tid);
            $tz = (string) ($settings['timezone'] ?? 'Europe/Paris');
            $computed = [
                'effectif_actifs' => (string) $effectif,
                'unites_public' => (string) $unitsPublic,
                'activite_pct' => $activityPct,
                'theatre_default' => $tz,
                'roster_public_count' => $rosterCount,
            ];
            $showcaseVm = TenantCommunityProfileService::getShowcaseViewModel($communityConfig, $computed, $tenant, $settings);
            $publicUnits = $this->unitRepository->listPublicForTenant($tid);
            $unitMemberCounts = $this->unitRepository->countActiveMembersByUnitForTenant($tid);
            $cmdIds = [];
            foreach ($publicUnits as $pu) {
                if (!empty($pu['commander_user_id'])) {
                    $cmdIds[] = (int) $pu['commander_user_id'];
                }
            }
            $cmdIds = array_unique($cmdIds);
            foreach ($cmdIds as $cuid) {
                $cu = $this->userRepository->findById($cuid, $tid);
                if ($cu) {
                    $commanderNames[$cuid] = trim((string) ($cu['display_name'] ?? $cu['callsign'] ?? '')) ?: '—';
                }
            }
            if (!empty($communityConfig['public_roster_enabled'])) {
                $publicRosterRows = $this->userRepository->listPublicRosterForTenant($tid);
            }
        }

        $recruitmentPublishedOpenings = [];
        $recruitmentProspectionRef = '';
        $recruitmentListUpdatedAt = '';
        if ($publicLayout === 'showcase' && $this->recruitmentOpeningRepository->tablesExist()) {
            $recruitmentPublishedOpenings = $this->recruitmentOpeningRepository->listPublishedForTenant($tid);
            $recruitmentProspectionRef = TenantRecruitmentSettings::prospectionDocumentRef($settings);
            $recruitmentListUpdatedAt = $this->recruitmentOpeningRepository->maxUpdatedAtPublished($tid) ?? '';
        }

        $fromRegistry = trim((string) $request->query('ref', '')) === 'registry';
        $this->analyticsEventService->record(
            $tid,
            $this->authService->check() && Session::get('user_id') ? (int) Session::get('user_id') : null,
            AnalyticsEventCategory::TENANT_PUBLIC,
            AnalyticsEventName::TENANT_PUBLIC_VIEW,
            AnalyticsSubjectType::TENANT,
            $tid,
            null,
            $fromRegistry ? ['from_registry' => true] : null
        );

        $publicMediaItems = [];
        $publicMediaCollections = [];
        $tenantBranding = [
            'logo_url' => null,
            'banner_url' => null,
            'primary_color' => null,
            'accent_color' => null,
        ];
        if ($publicLayout === 'showcase') {
            $publicMediaItems = $this->communityMediaRepository->listPublicPageItems($tid);
            $publicMediaCollections = $this->communityMediaRepository->listPublicCollections($tid);
            $brandingRow = $this->tenantBrandingRepository->findByTenantId($tid);
            $tenantBranding = $this->tenantBrandingRepository->mergeWithTenantLogo($tenant, $brandingRow);
        }

        return Response::view('layout.main', [
            'title' => trim((string) ($tenant['name'] ?? 'Communauté')) . ' — Fiche publique',
            'content' => 'community.show',
            'tenant' => $tenant,
            'memberships' => $memberships,
            'communityConfig' => $communityConfig,
            'communityProfile' => $communityProfile,
            'hasMembershipInTenant' => $hasMembershipInTenant,
            'showForumCta' => $showForumCta,
            'tenantSettings' => $settings,
            'publicLayout' => $publicLayout,
            'showcaseVm' => $showcaseVm,
            'publicUnits' => $publicUnits,
            'publicRosterRows' => $publicRosterRows,
            'unitMemberCounts' => $unitMemberCounts,
            'commanderNames' => $commanderNames,
            'communityShowcasePage' => $publicLayout === 'showcase',
            'recruitmentPublishedOpenings' => $recruitmentPublishedOpenings,
            'recruitmentProspectionRef' => $recruitmentProspectionRef,
            'recruitmentListUpdatedAt' => $recruitmentListUpdatedAt,
            'publicMediaItems' => $publicMediaItems,
            'publicMediaCollections' => $publicMediaCollections,
            'tenantBranding' => $tenantBranding,
            'analyticsBeacon' => [
                'tenantId' => $tid,
                'category' => AnalyticsEventCategory::TENANT_PUBLIC,
                'durationEvent' => AnalyticsEventName::TENANT_PUBLIC_PAGE_DURATION,
                'subjectType' => AnalyticsSubjectType::TENANT,
                'subjectId' => $tid,
            ],
        ]);
    }

    /** Fiche publique « avis de vacance ». */
    public function recruitmentOpeningShow(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $avis = RecruitmentOpeningRepository::normalizePublicPageSlugFromRequest((string) ($params['avis'] ?? ''));
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant || $avis === '' || !$this->recruitmentOpeningRepository->tablesExist()) {
            return Response::view('errors.404', ['title' => 'Avis introuvable'])->setStatusCode(404);
        }
        $tid = (int) ($tenant['id'] ?? 0);
        $opening = $this->recruitmentOpeningRepository->findPublishedByPublicSlug($tid, $avis);
        if (!$opening) {
            return Response::view('errors.404', ['title' => 'Avis introuvable'])->setStatusCode(404);
        }
        $settings = [];
        $rawSettings = $tenant['settings'] ?? null;
        if (is_string($rawSettings) && trim($rawSettings) !== '') {
            $decoded = json_decode($rawSettings, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        $jobRoleName = '';
        if (!empty($opening['personnel_job_role_id'])) {
            $jr = $this->personnelJobRoleRepository->findRoleById((int) $opening['personnel_job_role_id'], $tid);
            if ($jr) {
                $jobRoleName = trim((string) ($jr['name'] ?? ''));
            }
        }
        $related = $this->recruitmentOpeningRepository->listRelatedPublished($tid, $opening, 5);
        $print = $request->query('imprimer') === '1';
        $communityConfig = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        $communityLocked = !empty($communityConfig['community_locked']);

        $oid = (int) ($opening['id'] ?? 0);
        if ($oid > 0) {
            $this->analyticsEventService->record(
                $tid,
                $this->authService->check() && Session::get('user_id') ? (int) Session::get('user_id') : null,
                AnalyticsEventCategory::RECRUITMENT,
                AnalyticsEventName::RECRUITMENT_OPENING_VIEW,
                AnalyticsSubjectType::RECRUITMENT_OPENING,
                $oid,
                null,
                null
            );
        }

        return Response::view('layout.main', [
            'title' => trim((string) ($opening['title'] ?? 'Avis')) . ' — ' . trim((string) ($tenant['name'] ?? '')),
            'content' => 'community.recruitment_opening_show',
            'tenant' => $tenant,
            'opening' => $opening,
            'jobRoleName' => $jobRoleName,
            'relatedOpenings' => $related,
            'printMode' => $print,
            'communityRecruitmentOpeningPage' => true,
            'communityLocked' => $communityLocked,
            'analyticsBeacon' => $oid > 0 ? [
                'tenantId' => $tid,
                'category' => AnalyticsEventCategory::RECRUITMENT,
                'durationEvent' => AnalyticsEventName::RECRUITMENT_OPENING_PAGE_DURATION,
                'subjectType' => AnalyticsSubjectType::RECRUITMENT_OPENING,
                'subjectId' => $oid,
            ] : null,
        ]);
    }

    /** Formulaire public « nous écrire » (e-mail vers contact_email). */
    public function contactPublic(Request $request, array $params = []): Response
    {
        if ($request->method() !== 'POST') {
            return Response::redirect(url(''));
        }
        if (!Csrf::validate($request->input('_csrf_token'))) {
            Session::flash('error', 'Session expirée.');

            return Response::redirect(url(''));
        }
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            Session::flash('error', 'Communauté introuvable.');

            return Response::redirect(url(''));
        }
        $settings = $this->tenantRepository->getSettings((int) $tenant['id']);
        $community = is_array($settings['community'] ?? null) ? $settings['community'] : [];
        if (empty($community['contact_form_enabled']) || trim((string) ($community['contact_email'] ?? '')) === '') {
            Session::flash('error', 'Formulaire de contact non activé.');

            return Response::redirect(url('c/' . rawurlencode($slug)));
        }
        $fromEmail = trim((string) $request->input('from_email', ''));
        $body = trim((string) $request->input('body', ''));
        if ($body === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Indiquez un e-mail valide et un message.');

            return Response::redirect(url('c/' . rawurlencode($slug)));
        }
        $to = trim((string) $community['contact_email']);
        $tenantName = (string) ($tenant['name'] ?? 'communauté');
        $this->emailService->sendCommunityContact($to, $tenantName, $fromEmail, $body, (int) $tenant['id']);
        Session::flash('success', 'Message envoyé. L’équipe vous répondra sur votre boîte mail.');

        return Response::redirect(url('c/' . rawurlencode($slug)));
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
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }
        return Response::redirect(url('forum'));
    }

    /**
     * Bascule la session sur la communauté cible puis redirige vers le formulaire d’enrôlement (compte Athena).
     */
    public function enterEnlistment(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            Session::flash('error', 'Communauté introuvable.');
            return Response::redirect(url(''));
        }
        if (!$this->authService->check()) {
            Session::flash('error', 'Connectez-vous pour utiliser votre compte sur cette candidature.');
            return Response::redirect(url('login'));
        }
        if (!$this->authService->switchToTenant((int) $tenant['id'])) {
            Session::flash('error', 'Vous n’avez pas de compte dans cette communauté.');
            return Response::redirect(url('c/' . $slug));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
        }

        return Response::redirect(url('c/' . $slug . '/enlistment'));
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
        $paidPlans = array_values(array_filter($plans, static fn ($p) => in_array((string) ($p['slug'] ?? ''), ['standard', 'pro', 'pro_plus'], true)));
        $stripeConfigured = (getenv('STRIPE_SECRET_KEY') ?: '') !== '';

        $grades = new GradeRepository();
        $gradesFr = $grades->listBySystemCode('FR_CLASSIC');
        $gradesUs = $grades->listBySystemCode('US_CLASSIC');

        return Response::view('layout.main', [
            'title' => 'Créer une communauté',
            'content' => 'community.create',
            'paidPlans' => $paidPlans,
            'stripeConfigured' => $stripeConfigured,
            'gradesFr' => $gradesFr,
            'gradesUs' => $gradesUs,
            'gradesFrGrouped' => $this->groupGradesByCategory($gradesFr),
            'gradesUsGrouped' => $this->groupGradesByCategory($gradesUs),
            'badgeLabels' => TenantCommunityProfileService::badgeLabels(),
            'defaultWizardUnitsJson' => json_encode($this->defaultQuickWizardUnits(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'wizardPermissionGroups' => CommunityOnboardingValidationService::wizardPermissionFieldGroups(),
        ]);
    }

    /**
     * Aperçu : POST enregistre le brouillon de formulaire en session, GET affiche.
     */
    public function createPreview(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        if ($request->isPost()) {
            if (!Csrf::validate($request->input('_csrf_token'))) {
                Session::flash('error', 'Session expirée.');

                return Response::redirect(url('communities/create'));
            }
            $data = $request->all();
            unset($data['_csrf_token']);
            $user = $this->authService->user();
            $uid = $user ? (int) ($user['id'] ?? 0) : 0;
            foreach ($this->communityWizardUploadService->processUploads($uid) as $k => $v) {
                $data[$k] = $v;
            }
            Session::set('community_create_preview', $data);

            return Response::redirect(url('communities/create/preview'));
        }
        $data = Session::get('community_create_preview');
        if (!is_array($data) || trim((string) ($data['name'] ?? '')) === '') {
            Session::flash('error', 'Utilisez « Aperçu » depuis l’assistant (nom de communauté requis).');

            return Response::redirect(url('communities/create'));
        }
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = 'apercu';
        }
        $communityPreview = $this->buildCommunityPreviewFromWizardData($data);
        $milsimPack = \App\Services\Community\EnlistmentMilsimPackService::forCommunity($communityPreview);

        return Response::view('layout.main', [
            'title' => 'Aperçu — ' . $name,
            'content' => 'community.create_preview',
            'previewName' => $name,
            'previewSlug' => $slug,
            'communityPreview' => $communityPreview,
            'milsimPackPreview' => $milsimPack,
            'registrationMode' => ((string) ($data['registration_mode'] ?? 'milsim')) === 'simple' ? 'simple' : 'milsim',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildCommunityPreviewFromWizardData(array $data): array
    {
        $badges = $data['wizard_style_badges'] ?? [];
        if (!is_array($badges)) {
            $badges = [];
        }
        $allowed = array_flip(TenantCommunityProfileService::allowedBadgeSlugs());
        $styleBadges = array_values(array_filter(array_map(static function ($s) use ($allowed) {
            $k = is_string($s) ? strtolower(trim($s)) : '';

            return isset($allowed[$k]) ? $k : null;
        }, $badges)));

        $c = [
            'registration_mode' => ((string) ($data['registration_mode'] ?? 'milsim')) === 'simple' ? 'simple' : 'milsim',
            'community_locked' => !empty($data['community_locked']),
            'require_ai_ack' => !empty($data['require_ai_ack']),
            'welcome_text' => trim((string) ($data['welcome_text'] ?? '')),
            'presentation_mode' => ((string) ($data['wizard_presentation_mode'] ?? 'simple')) === 'military' ? 'military' : 'simple',
            'simple_body' => trim((string) ($data['wizard_simple_body'] ?? '')),
            'expectations' => trim((string) ($data['wizard_expectations'] ?? '')),
            'game_label' => trim((string) ($data['wizard_game_label'] ?? '')),
            'style_badges' => $styleBadges,
            'public_banner_url' => trim((string) ($data['wizard_public_banner_url'] ?? '')),
        ];
        $wm = $data['wizard_milsim'] ?? null;
        $frag = \App\Services\Community\EnlistmentMilsimPackService::mergeWizardMilsimInput(is_array($wm) ? $wm : null);
        if ($frag !== null) {
            $c['enlistment_milsim'] = $frag;
        }
        $json = trim((string) ($data['wizard_enlistment_milsim_json'] ?? ''));
        if ($json !== '') {
            $d = json_decode($json, true);
            if (is_array($d)) {
                $c['enlistment_milsim'] = array_merge(is_array($c['enlistment_milsim'] ?? null) ? $c['enlistment_milsim'] : [], $d);
            }
        }

        return $c;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array{label: string, grades: list<array<string, mixed>>}>
     */
    private function groupGradesByCategory(array $rows): array
    {
        $order = [
            'OFFICIER' => 'Officiers',
            'SOUS_OFFICIER' => 'Sous-officiers',
            'MDR' => 'Militaires du rang',
            'CIVIL' => 'Civils',
            'HORS_GRADE' => 'Hors grades',
        ];
        $out = [];
        foreach ($order as $code => $label) {
            $out[$code] = ['label' => $label, 'grades' => []];
        }
        foreach ($rows as $r) {
            $c = (string) ($r['category_code'] ?? 'OTHER');
            if (!isset($out[$c])) {
                $out[$c] = ['label' => (string) ($r['category_label'] ?? $c), 'grades' => []];
            }
            $out[$c]['grades'][] = $r;
        }

        return $out;
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
        if (!$request->input('wizard_custom_community_slug')) {
            $slug = '';
        }
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
                'public_page_layout' => ((string) $request->input('wizard_public_page_layout', 'legacy')) === 'showcase' ? 'showcase' : 'legacy',
                'public_hero_subtitle' => trim((string) $request->input('wizard_public_hero_subtitle', '')),
                'public_doctrine' => trim((string) $request->input('wizard_public_doctrine', '')),
            ];

            $wizardRaw = $this->buildWizardFromRequest($request);
            $validator = new CommunityOnboardingValidationService();
            $v = $validator->validate($wizardRaw);
            if (!$v['ok']) {
                $msg = implode(' ', $v['errors'] ?? ['Configuration invalide.']);
                Session::flash('error', $msg);
                Session::flash('onboarding_step', $v['step'] ?? '');

                return Response::redirect(url('communities/create'));
            }
            $optionsBase['wizard_normalized'] = $v['normalized'];

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
        $this->rbacService->setPermissionsForGateFromUserRow($u, $this->userRepository);
        $creatorEmail = strtolower(trim((string) ($u['email'] ?? $email)));
        if ($creatorEmail !== '') {
            $this->emailService->sendCommunityCreationChecklist(
                $creatorEmail,
                trim((string) ($u['display_name'] ?? 'Responsable')),
                trim((string) ($tenant['name'] ?? 'Communauté')),
                url('dashboard'),
                url('back-office/configuration-initiale'),
                $tenantId
            );
        }
        $this->pendingCommunityRepository->deleteById((int) $pending['id']);
        Session::forget('pending_referrer_code');
        Session::flash('success', 'Paiement confirmé. Votre communauté est prête. Finalisez les derniers réglages.');
        $slug = (string) ($tenant['slug'] ?? '');

        return Response::redirect(url('back-office/configuration-initiale'));
    }

    /**
     * @param array{tenant_id: int, user_id: int} $result
     */
    private function finalizeFreeCommunityCreation(string $name, string $slugInput, array $result): Response
    {
        Session::forget('pending_referrer_code');
        $newUserId = (int) $result['user_id'];
        $tenantId = (int) $result['tenant_id'];
        $t = $this->tenantRepository->findById($tenantId);
        $u = $this->userRepository->findById($newUserId, $tenantId);
        if ($u) {
            $this->authService->loginUser($u);
            $this->rbacService->setPermissionsForGateFromUserRow($u, $this->userRepository);
            $creatorEmail = strtolower(trim((string) ($u['email'] ?? '')));
            if ($creatorEmail !== '') {
                $this->emailService->sendCommunityCreationChecklist(
                    $creatorEmail,
                    trim((string) ($u['display_name'] ?? 'Responsable')),
                    trim((string) (($t['name'] ?? $name))),
                    url('dashboard'),
                    url('back-office/configuration-initiale'),
                    $tenantId
                );
            }
        }
        $audit = \App\Core\Container::get(AuditService::class);
        $audit->log(AuditAction::TENANT_CREATED, $tenantId, $newUserId, 'tenant', $tenantId, null, (string) $name);
        Session::flash('success', 'Communauté créée. Finalisez les derniers réglages essentiels.');
        $newSlug = $t['slug'] ?? $slugInput;

        return Response::redirect(url('back-office/configuration-initiale'));
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
        if (!in_array($slug, ['standard', 'pro', 'pro_plus'], true) || !in_array($interval, ['monthly', 'yearly'], true)) {
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
        $targetTenant = $this->tenantRepository->findById($tenantId);
        if (!$targetTenant) {
            Session::flash('error', 'Communauté introuvable.');
            return Response::redirect(url('dashboard'));
        }
        $email = (string) Session::get('email');
        $allMemberships = $this->userRepository->listTenantsForEmail($email);
        if (($targetTenant['slug'] ?? '') === 'default' && $this->userRepository->firstNonDefaultTenantId($allMemberships) !== null) {
            Session::flash('error', 'Ce contexte n’est plus disponible tant qu’une autre communauté est active sur votre compte.');
            return Response::redirect(url('dashboard'));
        }
        if (!$this->authService->switchToTenant($tenantId)) {
            Session::flash('error', 'Impossible de basculer vers cette communauté.');
            return Response::redirect(url('dashboard'));
        }
        $user = $this->authService->user();
        if ($user) {
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
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
            $this->rbacService->setPermissionsForGateFromUserRow($user, $this->userRepository);
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
            'onboarding_wizard_version' => 1,
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

    /**
     * @return array<string, mixed>
     */
    private function buildWizardFromRequest(Request $request): array
    {
        $quick = $request->input('wizard_quick_fill') ? true : false;
        $units = [];
        if ($quick) {
            $units = $this->defaultQuickWizardUnits();
        } else {
            $raw = trim((string) $request->input('wizard_units_json', ''));
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $units = $decoded;
                }
            }
        }

        $gs = strtoupper(trim((string) $request->input('wizard_grade_system_code', 'FR_CLASSIC')));
        if (!in_array($gs, CommunityOnboardingValidationService::GRADE_SYSTEMS, true)) {
            $gs = 'FR_CLASSIC';
        }

        $founderGid = (int) $request->input('wizard_founder_grade_id', 0);
        if ($founderGid < 1) {
            $grades = new GradeRepository();
            $list = $grades->listBySystemCode($gs);
            if ($list !== []) {
                $founderGid = (int) ($list[0]['id'] ?? 0);
            }
        }

        $wizard = [
            'grade_system_code' => $gs,
            'timezone' => trim((string) $request->input('wizard_timezone', 'Europe/Paris')),
            'default_locale' => trim((string) $request->input('wizard_default_locale', 'fr')),
            'orbat_visibility' => trim((string) $request->input('wizard_orbat_visibility', 'members')),
            'founder_grade_id' => $founderGid,
            'roles_template' => trim((string) $request->input('wizard_roles_template', 'quick')),
            'units' => $units,
            'grade_overrides' => [],
            'custom_roles' => $this->parseWizardCustomRolesFromRequest($request),
        ];

        foreach ($request->all() as $k => $v) {
            if (!is_string($k) || !str_starts_with($k, 'wizard_')) {
                continue;
            }
            if ($k === 'wizard_custom_roles') {
                continue;
            }
            $wizard[$k] = $v;
        }
        $wm = $request->input('wizard_milsim');
        if (is_array($wm)) {
            $wizard['wizard_milsim'] = $wm;
        }

        $user = $this->authService->user();
        $uid = $user ? (int) ($user['id'] ?? 0) : 0;
        foreach ($this->communityWizardUploadService->processUploads($uid) as $k => $v) {
            $wizard[$k] = $v;
        }

        return $wizard;
    }

    /**
     * @return list<array{name: string, slug: string, permission_slugs: list<string>}>
     */
    private function parseWizardCustomRolesFromRequest(Request $request): array
    {
        $raw = $request->input('wizard_custom_roles');
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $slug = trim((string) ($row['slug'] ?? ''));
            $perms = $row['perms'] ?? [];
            if (!is_array($perms)) {
                $perms = [];
            }
            $slugs = [];
            foreach ($perms as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $slugs[] = trim($p);
                }
            }
            if ($name === '' && $slug === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'slug' => $slug,
                'permission_slugs' => $slugs,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultQuickWizardUnits(): array
    {
        return [
            [
                'key' => 'g1',
                'parent_key' => '',
                'name' => 'État-major',
                'slug' => 'etat-major',
                'type' => 'group',
                'display_order' => 0,
            ],
            [
                'key' => 's1',
                'parent_key' => 'g1',
                'name' => '1re section',
                'slug' => '1re-section',
                'type' => 'section',
                'display_order' => 0,
            ],
            [
                'key' => 't1',
                'parent_key' => 's1',
                'name' => '1re équipe',
                'slug' => '1re-equipe',
                'type' => 'team',
                'display_order' => 0,
            ],
        ];
    }
}
