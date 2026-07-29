<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\PersonnelJobRoleRepository;
use App\Repositories\RecruitmentOpeningRepository;
use App\Repositories\CommunityEventRepository;
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
use App\Services\Billing\BillingProvider;
use App\Services\Billing\PayPalCheckoutService;
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
use App\Support\UserFacingExceptionMapper;

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
        private PayPalCheckoutService $payPalCheckoutService,
        private SubscriptionPlanRepository $subscriptionPlanRepository,
        private EmailService $emailService,
        private CommunityWizardUploadService $communityWizardUploadService,
        private RecruitmentOpeningRepository $recruitmentOpeningRepository,
        private PersonnelJobRoleRepository $personnelJobRoleRepository,
        private AnalyticsEventService $analyticsEventService,
        private CommunityMediaRepository $communityMediaRepository,
        private TenantBrandingRepository $tenantBrandingRepository,
        private CommunityEventRepository $communityEventRepository,
    ) {}

    /** Registre des unités / communautés (hors tenant placeholder). */
    public function registry(Request $request, array $params = []): Response
    {
        $tenants = $this->tenantRepository->listForRegistry();

        return Response::view('layout.main', [
            'title' => 'Communautés & unités',
            'content' => 'community.registry',
            'registryTenants' => $tenants,
            'communityRegistryPage' => true,
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
        $showcaseBackUrl = $this->resolveShowcaseBackUrl($request, (string) ($tenant['slug'] ?? ''));
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
        $publicUpcomingEvents = [];
        $tenantBranding = [
            'logo_url' => null,
            'banner_url' => null,
            'primary_color' => null,
            'accent_color' => null,
        ];
        $mediaViewerUserId = $this->authService->check() && Session::get('user_id')
            ? (int) Session::get('user_id')
            : null;
        if ($publicLayout === 'showcase') {
            $publicMediaItems = $this->communityMediaRepository->attachLikeState(
                $this->communityMediaRepository->listPublicPageItems($tid),
                $mediaViewerUserId
            );
            $publicMediaCollections = $this->communityMediaRepository->listPublicCollections($tid);
            $brandingRow = $this->tenantBrandingRepository->findByTenantId($tid);
            $tenantBranding = $this->tenantBrandingRepository->mergeWithTenantLogo($tenant, $brandingRow);
            $publicModules = is_array($communityConfig['public_modules'] ?? null)
                ? $communityConfig['public_modules']
                : [];
            if (!empty($publicModules['events'])) {
                $publicUpcomingEvents = $this->communityEventRepository->upcomingPublicForTenant($tid, 6);
            }
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
            'showcaseBackUrl' => $showcaseBackUrl,
            'recruitmentPublishedOpenings' => $recruitmentPublishedOpenings,
            'recruitmentProspectionRef' => $recruitmentProspectionRef,
            'recruitmentListUpdatedAt' => $recruitmentListUpdatedAt,
            'publicMediaItems' => $publicMediaItems,
            'publicMediaCollections' => $publicMediaCollections,
            'publicUpcomingEvents' => $publicUpcomingEvents,
            'mediaLikesEnabled' => $this->communityMediaRepository->likesTableExists(),
            'mediaViewerCanLike' => $mediaViewerUserId !== null && $mediaViewerUserId > 0,
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

    /** Mini-site public d’une unité : landing, bio, chef d’unité, sous-unités, effectif, roster. */
    public function showUnit(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $unitSlug = (string) ($params['unitSlug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            return Response::view('errors.404', ['title' => 'Communauté introuvable'])->setStatusCode(404);
        }
        $tid = (int) ($tenant['id'] ?? 0);
        $unit = $this->unitRepository->findBySlugForTenant($tid, $unitSlug);
        if (!$unit) {
            return Response::view('errors.404', ['title' => 'Unité introuvable'])->setStatusCode(404);
        }

        $isStaffViewer = $this->authService->check()
            && (int) Session::get('tenant_id') === $tid
            && (\App\Core\Gate::getInstance()->allows('admin.organization') || \App\Core\Gate::getInstance()->allows('admin.access'));
        if (empty($unit['show_on_public_page']) && !$isStaffViewer) {
            return Response::view('errors.404', ['title' => 'Unité introuvable'])->setStatusCode(404);
        }

        $uid = (int) ($unit['id'] ?? 0);
        $memberCounts = $this->unitRepository->countActiveMembersByUnitForTenant($tid);
        $memberCount = (int) ($memberCounts[$uid] ?? 0);
        $rosterByUnit = $this->unitRepository->rosterMembersByUnitForTenant($tid, 60);
        $roster = $rosterByUnit[$uid] ?? [];

        $commanderName = null;
        $cmdId = (int) ($unit['commander_user_id'] ?? 0);
        if ($cmdId > 0) {
            $cu = $this->userRepository->findById($cmdId, $tid);
            $commanderName = $cu ? (trim((string) ($cu['display_name'] ?? $cu['callsign'] ?? '')) ?: null) : null;
        }

        $children = $this->unitRepository->childrenForTenant($tid, $uid);
        $childrenCounts = [];
        foreach ($children as $c) {
            $cid = (int) ($c['id'] ?? 0);
            $childrenCounts[$cid] = (int) ($memberCounts[$cid] ?? 0);
        }

        $parentUnit = null;
        $parentId = (int) ($unit['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parentUnit = $this->unitRepository->findById($parentId, $tid);
        }

        $brandingRow = $this->tenantBrandingRepository->findByTenantId($tid);
        $tenantBranding = $this->tenantBrandingRepository->mergeWithTenantLogo($tenant, $brandingRow);

        $this->analyticsEventService->record(
            $tid,
            $this->authService->check() && Session::get('user_id') ? (int) Session::get('user_id') : null,
            AnalyticsEventCategory::TENANT_PUBLIC,
            AnalyticsEventName::TENANT_PUBLIC_VIEW,
            AnalyticsSubjectType::TENANT,
            $tid,
            null,
            ['unit_id' => $uid]
        );

        return Response::view('layout.main', [
            'title' => trim((string) ($unit['name'] ?? 'Unité')) . ' — ' . trim((string) ($tenant['name'] ?? 'Communauté')),
            'content' => 'community.unit_show',
            'tenant' => $tenant,
            'unit' => $unit,
            'unitMemberCount' => $memberCount,
            'unitRoster' => $roster,
            'unitCommanderName' => $commanderName,
            'unitChildren' => $children,
            'unitChildrenCounts' => $childrenCounts,
            'unitParent' => $parentUnit,
            'tenantBranding' => $tenantBranding,
            'unitIsPreview' => empty($unit['show_on_public_page']) && $isStaffViewer,
            'communityShowcasePage' => true,
        ]);
    }

    /** Galerie médias publique — grille / masonry avec lightbox au clic. */
    public function mediaFeed(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            return Response::view('errors.404', ['title' => 'Communauté introuvable'])->setStatusCode(404);
        }
        $tid = (int) ($tenant['id'] ?? 0);
        $viewerUserId = $this->authService->check() && Session::get('user_id')
            ? (int) Session::get('user_id')
            : null;
        $items = $this->communityMediaRepository->attachLikeState(
            $this->communityMediaRepository->listPublicPageItems($tid),
            $viewerUserId
        );
        $brandingRow = $this->tenantBrandingRepository->findByTenantId($tid);
        $tenantBranding = $this->tenantBrandingRepository->mergeWithTenantLogo($tenant, $brandingRow);

        $this->analyticsEventService->record(
            $tid,
            $viewerUserId,
            AnalyticsEventCategory::TENANT_PUBLIC,
            AnalyticsEventName::TENANT_PUBLIC_VIEW,
            AnalyticsSubjectType::TENANT,
            $tid,
            null,
            ['media_feed' => true]
        );

        return Response::view('layout.main', [
            'title' => 'Médias — ' . trim((string) ($tenant['name'] ?? 'Communauté')),
            'content' => 'community.media_feed',
            'tenant' => $tenant,
            'mediaFeedItems' => $items,
            'mediaLikesEnabled' => $this->communityMediaRepository->likesTableExists(),
            'mediaViewerCanLike' => $viewerUserId !== null && $viewerUserId > 0,
            'tenantBranding' => $tenantBranding,
            /* Charge community-landing.css + masque la nav bas (même shell que la vitrine). */
            'communityShowcasePage' => true,
        ]);
    }

    /**
     * Fil vertical public type « Reels » (un média par écran, défilement vertical).
     */
    public function reelsFeed(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            return Response::view('errors.404', ['title' => 'Communauté introuvable'])->setStatusCode(404);
        }
        $tid = (int) ($tenant['id'] ?? 0);
        $viewerUserId = $this->authService->check() && Session::get('user_id')
            ? (int) Session::get('user_id')
            : null;
        $items = $this->communityMediaRepository->attachLikeState(
            $this->communityMediaRepository->listReelsFeedItems($tid),
            $viewerUserId
        );
        $brandingRow = $this->tenantBrandingRepository->findByTenantId($tid);
        $tenantBranding = $this->tenantBrandingRepository->mergeWithTenantLogo($tenant, $brandingRow);

        $this->analyticsEventService->record(
            $tid,
            $viewerUserId,
            AnalyticsEventCategory::TENANT_PUBLIC,
            AnalyticsEventName::TENANT_PUBLIC_VIEW,
            AnalyticsSubjectType::TENANT,
            $tid,
            null,
            ['reels_feed' => true]
        );

        return Response::view('layout.main', [
            'title' => 'Fil média — ' . trim((string) ($tenant['name'] ?? 'Communauté')),
            'content' => 'community.reels_feed',
            'tenant' => $tenant,
            'reelsFeedItems' => $items,
            'mediaLikesEnabled' => $this->communityMediaRepository->likesTableExists(),
            'mediaViewerCanLike' => $viewerUserId !== null && $viewerUserId > 0,
            'tenantBranding' => $tenantBranding,
            'communityShowcasePage' => true,
            'communityReelsPage' => true,
            'showPortalFooter' => false,
        ]);
    }

    /**
     * Raccourci /reels → fil de la communauté active en session (sinon registre).
     */
    public function reelsRedirect(Request $request, array $params = []): Response
    {
        $tid = (int) (Session::get('tenant_id') ?? 0);
        if ($tid > 1) {
            $tenant = $this->tenantRepository->findById($tid);
            $slug = trim((string) ($tenant['slug'] ?? ''));
            if ($slug !== '') {
                return Response::redirect(url('c/' . rawurlencode($slug) . '/reels'));
            }
        }

        return Response::redirect(url('communities'));
    }

    /**
     * Toggle « J’aime » sur un média public publié (utilisateurs connectés uniquement).
     * Réponse JSON pour la lightbox / la grille.
     */
    public function toggleMediaLike(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $mediaId = (int) ($params['id'] ?? 0);
        $tenant = $this->tenantRepository->findBySlug($slug);
        if (!$tenant) {
            return Response::json([
                'success' => false,
                'message' => 'Cette communauté est introuvable.',
            ], 404);
        }
        if (!$this->communityMediaRepository->likesTableExists()) {
            return Response::json([
                'success' => false,
                'message' => 'Les « J’aime » ne sont pas encore disponibles pour cette galerie.',
            ], 503);
        }

        $userId = $this->authService->check() && Session::get('user_id')
            ? (int) Session::get('user_id')
            : 0;
        if ($userId < 1) {
            return Response::json([
                'success' => false,
                'needs_login' => true,
                'message' => 'Connectez-vous pour aimer ce média.',
                'login_url' => url('login'),
            ], 401);
        }

        $input = $this->readJsonOrFormInput($request);
        $csrf = (string) ($input['csrf_token'] ?? $input['_csrf_token'] ?? $request->input('_csrf_token', ''));
        if (!Csrf::validate($csrf)) {
            return Response::json([
                'success' => false,
                'message' => 'Votre session a expiré. Rechargez la page puis réessayez.',
            ], 403);
        }

        $tid = (int) ($tenant['id'] ?? 0);
        $item = $this->communityMediaRepository->findPublishedPublicItem($mediaId, $tid);
        if ($item === null) {
            return Response::json([
                'success' => false,
                'message' => 'Ce média n’est plus disponible.',
            ], 404);
        }

        $wantLike = array_key_exists('like', $input)
            ? ((int) $input['like'] === 1 || $input['like'] === true || $input['like'] === '1')
            : !$this->communityMediaRepository->isLiked($userId, $mediaId);

        $this->communityMediaRepository->setLike($tid, $userId, $mediaId, $wantLike);
        $liked = $this->communityMediaRepository->isLiked($userId, $mediaId);
        $count = $this->communityMediaRepository->countLikes($mediaId);

        return Response::json([
            'success' => true,
            'liked' => $liked,
            'likes_count' => $count,
            'message' => $liked ? 'Merci pour votre soutien.' : '« J’aime » retiré.',
        ]);
    }

    /** @return array<string, mixed> */
    private function readJsonOrFormInput(Request $request): array
    {
        $raw = $request->method() === 'POST' ? (string) file_get_contents('php://input') : '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->all();
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
        $billingConfigured = BillingProvider::anyConfigured();
        $billingProvider = BillingProvider::preferred();

        $grades = new GradeRepository();
        $gradesFr = $grades->listBySystemCode('FR_CLASSIC');
        $gradesUs = $grades->listBySystemCode('US_CLASSIC');

        return Response::view('layout.main', [
            'title' => 'Créer une communauté',
            'content' => 'community.create',
            'stripeConfigured' => $billingConfigured,
            'billingConfigured' => $billingConfigured,
            'billingProvider' => $billingProvider,
            'gradesFr' => $gradesFr,
            'gradesUs' => $gradesUs,
            'gradesFrGrouped' => $this->groupGradesByCategory($gradesFr),
            'gradesUsGrouped' => $this->groupGradesByCategory($gradesUs),
            'badgeLabels' => TenantCommunityProfileService::badgeLabels(),
            'defaultWizardUnitsJson' => json_encode($this->defaultQuickWizardUnits(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'wizardPermissionGroups' => CommunityOnboardingValidationService::wizardPermissionFieldGroups(),
            'subscriptionOfferCards' => $this->subscriptionOfferCards($billingConfigured),
            'communityCreateDraft' => Session::get('community_create_draft') ?? [],
            'onboardingStep' => Session::getFlash('onboarding_step'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subscriptionOfferCards(bool $billingConfigured): array
    {
        $rows = [];
        foreach ($this->subscriptionPlanRepository->allOrdered() as $row) {
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($slug === '') {
                continue;
            }
            $rows[$slug] = $row;
        }
        $standardInterval = $this->preferredPlanInterval($rows['standard'] ?? null);
        $proInterval = $this->preferredPlanInterval($rows['pro'] ?? null);
        $proPlusInterval = $this->preferredPlanInterval($rows['pro_plus'] ?? null);
        $billingReady = $billingConfigured;
        $unavailableHint = !$billingConfigured
            ? 'Paiement non configuré sur la plateforme.'
            : 'Identifiants de prix manquants pour cette formule (administration plateforme → Formules d’accès).';

        return [
            [
                'slug' => 'free',
                'title' => 'Quartier libre',
                'eyebrow' => 'Sans engagement',
                'description' => 'Forum, documents, messagerie, effectifs et formations de base pour démarrer sans frais.',
                'meta' => 'Gratuit',
                'limits' => ['10 places', '5 formations', '3 événements par mois', 'Courrier et ATAK non inclus'],
                'value' => 'free',
                'paid' => false,
                'available' => true,
            ],
            [
                'slug' => 'standard',
                'title' => 'Standard',
                'eyebrow' => 'Équipe',
                'description' => 'Pour une unité active : événements, courrier, mur opérationnel et carte tactique.',
                'meta' => 'Abonnement',
                'limits' => ['25 places', '25 formations', 'Événements, courrier, ATAK', 'Alertes communauté'],
                'value' => 'standard|' . $standardInterval,
                'paid' => true,
                'available' => $billingReady && $standardInterval !== '',
                'unavailable_hint' => $unavailableHint,
            ],
            [
                'slug' => 'pro',
                'title' => 'Pro',
                'eyebrow' => 'Complet',
                'description' => 'Recrutement, coopération inter-unités, analytics et plafonds relevés.',
                'meta' => 'Abonnement',
                'limits' => ['50 places', '100 formations', 'Recrutement + coopération', 'Analytics inclus'],
                'value' => 'pro|' . $proInterval,
                'paid' => true,
                'available' => $billingReady && $proInterval !== '',
                'unavailable_hint' => $unavailableHint,
            ],
            [
                'slug' => 'pro_plus',
                'title' => 'Pro+',
                'eyebrow' => 'Intégrations',
                'description' => 'Périmètre maximal avec intégrations avancées et plafonds relevés.',
                'meta' => 'Abonnement',
                'limits' => ['80 places', 'Formations illimitées', 'Intégrations avancées', 'Tout le périmètre Pro'],
                'value' => 'pro_plus|' . $proPlusInterval,
                'paid' => true,
                'available' => $billingReady && $proPlusInterval !== '',
                'unavailable_hint' => $unavailableHint,
            ],
            [
                'slug' => 'heart_support',
                'title' => 'Support du cœur à 2 €',
                'eyebrow' => 'Soutien volontaire',
                'description' => 'Même accès que Quartier libre, avec une contribution unique pour soutenir Athena.',
                'meta' => '2 € · une seule fois',
                'limits' => ['Création immédiate', 'Aucun engagement', 'Paiement sécurisé', 'Soutien au projet'],
                'value' => 'heart_support',
                'paid' => $billingConfigured,
                'heart' => true,
                'available' => true,
            ],
        ];
    }

    private function preferredPlanInterval(?array $row): string
    {
        if (!is_array($row)) {
            return '';
        }
        $provider = BillingProvider::preferred();
        if ($provider === BillingProvider::PAYPAL) {
            if (trim((string) ($row['paypal_plan_id_monthly'] ?? '')) !== '') {
                return 'monthly';
            }
            if (trim((string) ($row['paypal_plan_id_yearly'] ?? '')) !== '') {
                return 'yearly';
            }
        }
        if ($provider === BillingProvider::STRIPE || $provider === null) {
            if (trim((string) ($row['stripe_price_id_monthly'] ?? '')) !== '') {
                return 'monthly';
            }
            if (trim((string) ($row['stripe_price_id_yearly'] ?? '')) !== '') {
                return 'yearly';
            }
        }
        // Repli : n’importe quel fournisseur configuré sur la formule
        if (trim((string) ($row['paypal_plan_id_monthly'] ?? '')) !== '' || trim((string) ($row['stripe_price_id_monthly'] ?? '')) !== '') {
            return 'monthly';
        }
        if (trim((string) ($row['paypal_plan_id_yearly'] ?? '')) !== '' || trim((string) ($row['stripe_price_id_yearly'] ?? '')) !== '') {
            return 'yearly';
        }

        return '';
    }

    /**
     * Aperçu : POST enregistre le brouillon de formulaire en session, GET affiche.
     */
    public function createPreview(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            Session::flash('error', 'Connexion requise pour créer une communauté.');

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
            $uploadResult = $this->communityWizardUploadService->processUploadsWithFeedback($uid);
            foreach ($uploadResult['urls'] as $k => $v) {
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
            'registrationMode' => TenantCommunityProfileService::normalizeRegistrationMode($data['registration_mode'] ?? TenantCommunityProfileService::REGISTRATION_MODE_MILSIM),
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
            'registration_mode' => TenantCommunityProfileService::normalizeRegistrationMode($data['registration_mode'] ?? TenantCommunityProfileService::REGISTRATION_MODE_MILSIM),
            'community_locked' => !empty($data['community_locked']),
            'require_ai_ack' => !empty($data['require_ai_ack']),
            'refuse_other_community_members' => !empty($data['refuse_other_community_members']),
            'welcome_text' => trim((string) ($data['welcome_text'] ?? '')),
            'presentation_mode' => ((string) ($data['wizard_presentation_mode'] ?? 'simple')) === 'military' ? 'military' : 'simple',
            'simple_body' => trim((string) ($data['wizard_simple_body'] ?? '')),
            'public_about_body' => trim((string) ($data['wizard_public_about_body'] ?? '')),
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
            Session::flash('error', 'Connexion requise pour créer une communauté.');

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
            return $this->flashCommunityCreateErrorAndRedirect($request, 'Le nom de la communauté est requis.', 'identity');
        }
        $user = $this->authService->user();
        if (!$user) {
            Session::flash('error', 'Connexion requise pour créer une communauté.');

            return Response::redirect(url('login'));
        }
        $planChoice = trim((string) $request->input('plan_choice', 'free'));
        $wantsHeartSupport = $planChoice === 'heart_support';
        try {
            $paid = $this->parsePaidPlanChoice($planChoice);
            $referrerUserId = $this->resolveReferrerUserId((int) $user['id']);
            $tenantType = \App\Services\Community\TenantTypeConfig::normalizeType((string) $request->input('tenant_type', 'full'));
            $optionsBase = [
                'registration_mode' => TenantCommunityProfileService::normalizeRegistrationMode(
                    $request->input('registration_mode', TenantCommunityProfileService::REGISTRATION_MODE_MILSIM)
                ),
                'community_locked' => $request->input('community_locked') ? true : false,
                'require_ai_ack' => $request->input('require_ai_ack') ? true : false,
                'refuse_other_community_members' => $request->input('refuse_other_community_members') ? true : false,
                'welcome_text' => trim((string) $request->input('welcome_text')),
                'referrer_user_id' => $referrerUserId,
                'public_page_layout' => ((string) $request->input('wizard_public_page_layout', 'legacy')) === 'showcase' ? 'showcase' : 'legacy',
                'public_hero_subtitle' => trim((string) $request->input('wizard_public_hero_subtitle', '')),
                'public_about_body' => trim((string) $request->input('wizard_public_about_body', '')),
                'public_doctrine' => trim((string) $request->input('wizard_public_doctrine', '')),
                'tenant_type' => $tenantType,
            ];

            $wizardRaw = $this->buildWizardFromRequest($request);
            $uploadWarnings = is_array($wizardRaw['_upload_warnings'] ?? null) ? $wizardRaw['_upload_warnings'] : [];
            unset($wizardRaw['_upload_warnings']);
            $validator = new CommunityOnboardingValidationService();
            $v = $validator->validate($wizardRaw);
            if (!$v['ok']) {
                $msg = implode(' ', $v['errors'] ?? ['Configuration invalide.']);

                return $this->flashCommunityCreateErrorAndRedirect($request, $msg, (string) ($v['step'] ?? ''), $uploadWarnings);
            }
            $optionsBase['wizard_normalized'] = $v['normalized'];

            if ($paid !== null) {
                [$planSlug, $interval] = $paid;
                $provider = BillingProvider::preferred();
                if ($provider === null) {
                    return $this->flashCommunityCreateErrorAndRedirect($request, 'Le paiement en ligne n’est pas disponible pour le moment.', 'review');
                }
                $planRow = $this->subscriptionPlanRepository->findBySlug($planSlug);
                if (!$planRow) {
                    return $this->flashCommunityCreateErrorAndRedirect($request, 'Cette formule n’est pas disponible.', 'review');
                }
                $priceId = $provider === BillingProvider::PAYPAL
                    ? $this->paypalPlanIdForInterval($planRow, $interval)
                    : $this->stripePriceIdForInterval($planRow, $interval);
                if ($priceId === null && $provider === BillingProvider::PAYPAL) {
                    $priceId = $this->stripePriceIdForInterval($planRow, $interval);
                    if ($priceId !== null && BillingProvider::stripeConfigured()) {
                        $provider = BillingProvider::STRIPE;
                    }
                }
                if ($priceId === null && $provider === BillingProvider::STRIPE) {
                    $priceId = $this->paypalPlanIdForInterval($planRow, $interval);
                    if ($priceId !== null && BillingProvider::paypalConfigured()) {
                        $provider = BillingProvider::PAYPAL;
                    }
                }
                if ($priceId === null) {
                    return $this->flashCommunityCreateErrorAndRedirect(
                        $request,
                        'Cette formule n’est pas encore ouverte à la souscription. Choisissez Quartier libre ou Support du cœur.',
                        'review'
                    );
                }
                $payload = json_encode([
                    'name' => $name,
                    'slug' => $slug,
                    'options' => $optionsBase,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                $token = bin2hex(random_bytes(32));
                $this->pendingCommunityRepository->create($token, (int) $user['id'], $payload, $planSlug, $priceId, $provider);
                Session::forget('community_create_draft');

                return Response::redirect(url('communities/create/pay?token=' . rawurlencode($token)));
            }

            $result = $this->bootstrapService->createCommunity((int) $user['id'], $name, $slug, array_merge($optionsBase, [
                'plan_slug' => 'free',
            ]));
            Session::forget('pending_referrer_code');
            Session::forget('community_create_draft');

            return $this->finalizeFreeCommunityCreation($name, $slug, $result, $wantsHeartSupport, $uploadWarnings);
        } catch (\InvalidArgumentException $e) {
            return $this->flashCommunityCreateErrorAndRedirect($request, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[community.create] ' . $e->getMessage());

            return $this->flashCommunityCreateErrorAndRedirect(
                $request,
                UserFacingExceptionMapper::communityCreationMessage($e)
            );
        }
    }

    /** Redirection vers PayPal ou Stripe Checkout (paiement obligatoire pour Standard / Pro). */
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
        $provider = strtolower(trim((string) ($row['payment_provider'] ?? 'stripe')));
        if ($provider !== BillingProvider::PAYPAL) {
            $provider = BillingProvider::STRIPE;
        }
        try {
            $email = (string) ($user['email'] ?? Session::get('email') ?? '');
            $cancelUrl = url('communities/create');
            if ($provider === BillingProvider::PAYPAL) {
                $successUrl = url('communities/create/complete') . '?provider=paypal&token=' . rawurlencode($token);
                $session = $this->payPalCheckoutService->createSubscription(
                    (string) $row['stripe_price_id'],
                    $successUrl,
                    $cancelUrl,
                    $email !== '' ? $email : null,
                    [
                        'pct' => $token,
                        'plan' => (string) $row['plan_slug'],
                    ]
                );
                $this->pendingCommunityRepository->updatePayPalSubscriptionId($token, $session['id']);

                return Response::redirect($session['url']);
            }

            $successUrl = url('communities/create/complete') . '?session_id={CHECKOUT_SESSION_ID}';
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
            error_log('[community.pay] ' . $e->getMessage());
            Session::flash('error', 'Le paiement en ligne n’a pas pu être démarré. Réessayez ou choisissez une formule gratuite.');

            return Response::redirect(url('communities/create'));
        }
    }

    /** Après retour PayPal / Stripe : connexion au nouveau tenant et assistant de configuration. */
    public function createComplete(Request $request, array $params = []): Response
    {
        if (!$this->authService->check()) {
            return Response::redirect(url('login'));
        }
        $user = $this->authService->user();
        if (!$user) {
            return Response::redirect(url('login'));
        }

        $provider = strtolower(trim((string) $request->query('provider', '')));
        $paypalToken = trim((string) $request->query('token', ''));
        $paypalSubId = trim((string) $request->query('subscription_id', ''));

        if ($provider === BillingProvider::PAYPAL || ($paypalToken !== '' && $paypalSubId !== '')) {
            return $this->createCompletePayPal($user, $paypalToken, $paypalSubId, $request);
        }

        $sessionId = trim((string) $request->query('session_id'));
        if ($sessionId === '') {
            Session::flash('error', 'Session de paiement manquante.');
            return Response::redirect(url('communities/create'));
        }
        $pending = $this->pendingCommunityRepository->findByStripeCheckoutSessionId($sessionId);
        if (!$pending || (int) $pending['user_id'] !== (int) $user['id']) {
            Session::flash('error', 'Paiement non associé à votre compte.');
            return Response::redirect(url('communities/create'));
        }

        return $this->waitOrLoginPending($pending, (string) $user['email'], $sessionId, $request);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function createCompletePayPal(array $user, string $token, string $subscriptionId, Request $request): Response
    {
        $pending = null;
        if ($token !== '') {
            $pending = $this->pendingCommunityRepository->findByToken($token);
        }
        if ($pending === null && $subscriptionId !== '') {
            $pending = $this->pendingCommunityRepository->findByPayPalSubscriptionId($subscriptionId);
        }
        if (!$pending || (int) $pending['user_id'] !== (int) $user['id']) {
            Session::flash('error', 'Paiement non associé à votre compte.');

            return Response::redirect(url('communities/create'));
        }
        if ($subscriptionId !== '' && empty($pending['paypal_subscription_id'])) {
            $this->pendingCommunityRepository->updatePayPalSubscriptionId((string) $pending['token'], $subscriptionId);
        }
        // Si le webhook n’a pas encore tourné : activer immédiatement après retour PayPal
        if (empty($pending['tenant_id']) && $subscriptionId !== '') {
            try {
                $sub = $this->payPalCheckoutService->getSubscription($subscriptionId);
                $status = strtoupper((string) ($sub['status'] ?? ''));
                if (in_array($status, ['ACTIVE', 'APPROVED'], true)) {
                    $this->fulfillPendingCommunityFromPayPal($pending, $subscriptionId, $sub);
                    $pending = $this->pendingCommunityRepository->findByToken((string) $pending['token']) ?? $pending;
                }
            } catch (\Throwable $e) {
                error_log('[community.createComplete.paypal] ' . $e->getMessage());
            }
        }

        $lookupId = $subscriptionId !== '' ? $subscriptionId : (string) ($pending['paypal_subscription_id'] ?? $pending['token'] ?? '');

        return $this->waitOrLoginPending($pending, (string) $user['email'], $lookupId, $request, true);
    }

    /**
     * @param array<string, mixed> $pending
     * @param array<string, mixed> $subscription
     */
    private function fulfillPendingCommunityFromPayPal(array $pending, string $subscriptionId, array $subscription): void
    {
        if (!empty($pending['tenant_id'])) {
            return;
        }
        $token = (string) ($pending['token'] ?? '');
        $payload = json_decode((string) ($pending['payload_json'] ?? ''), true);
        if ($token === '' || !is_array($payload)) {
            return;
        }
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $options['plan_slug'] = (string) $pending['plan_slug'];
        $options['skip_founder_trial'] = true;
        try {
            $result = $this->bootstrapService->createCommunity((int) $pending['user_id'], $name, $slug, $options);
        } catch (\Throwable $e) {
            $this->pendingCommunityRepository->setCreationError($token, UserFacingExceptionMapper::communityCreationMessage($e));

            return;
        }
        $payerId = null;
        if (isset($subscription['subscriber']) && is_array($subscription['subscriber'])) {
            $p = $subscription['subscriber']['payer_id'] ?? null;
            $payerId = is_string($p) ? $p : null;
        }
        $periodEnd = isset($subscription['billing_info']['next_billing_time'])
            ? (string) $subscription['billing_info']['next_billing_time']
            : null;
        $this->tenantRepository->updateSubscriptionFromPayPal(
            (int) $result['tenant_id'],
            $payerId,
            $subscriptionId,
            'active',
            (string) $pending['plan_slug'],
            $periodEnd
        );
        $this->pendingCommunityRepository->setTenantIdForToken($token, (int) $result['tenant_id']);
    }

    /**
     * @param array<string, mixed> $pending
     */
    private function waitOrLoginPending(
        array $pending,
        string $email,
        string $lookupId,
        Request $request,
        bool $paypal = false
    ): Response {
        $creationError = trim((string) ($pending['creation_error'] ?? ''));
        if ($creationError !== '') {
            Session::flash('error', $creationError);

            return Response::redirect(url('communities/create'));
        }
        if (empty($pending['tenant_id'])) {
            $wait = max(0, (int) $request->query('wait', 0));
            if ($wait >= 20) {
                return Response::view('layout.main', [
                    'title' => 'Paiement en cours de validation',
                    'content' => 'community.create_pending',
                    'sessionId' => $lookupId,
                    'timedOut' => true,
                    'retryUrl' => $paypal
                        ? url('communities/create/complete') . '?provider=paypal&token=' . rawurlencode((string) $pending['token']) . '&subscription_id=' . rawurlencode($lookupId)
                        : url('communities/create/complete') . '?session_id=' . rawurlencode($lookupId),
                ]);
            }
            $nextWait = $wait + 1;
            $retryUrl = $paypal
                ? url('communities/create/complete') . '?provider=paypal&token=' . rawurlencode((string) $pending['token']) . '&subscription_id=' . rawurlencode($lookupId) . '&wait=' . $nextWait
                : url('communities/create/complete') . '?session_id=' . rawurlencode($lookupId) . '&wait=' . $nextWait;

            return Response::view('layout.main', [
                'title' => 'Paiement en cours de validation',
                'content' => 'community.create_pending',
                'sessionId' => $lookupId,
                'timedOut' => false,
                'retryUrl' => $retryUrl,
            ]);
        }

        return $this->loginAndRedirectToNewCommunity($pending, $email);
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

        return Response::redirect(url('back-office/configuration-initiale'));
    }

    /**
     * @param array{tenant_id: int, user_id: int} $result
     */
    private function finalizeFreeCommunityCreation(string $name, string $slugInput, array $result, bool $withHeartSupport = false, array $uploadWarnings = []): Response
    {
        Session::forget('pending_referrer_code');
        $newUserId = (int) $result['user_id'];
        $tenantId = (int) $result['tenant_id'];
        $t = $this->tenantRepository->findById($tenantId);
        $u = $this->userRepository->findById($newUserId, $tenantId);
        $creatorEmail = '';
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
        } else {
            Session::flash(
                'warning',
                'La communauté a été créée, mais la connexion automatique a échoué. Connectez-vous pour accéder à votre espace.'
            );

            return Response::redirect(url('login'));
        }
        $audit = \App\Core\Container::get(AuditService::class);
        $audit->log(AuditAction::TENANT_CREATED, $tenantId, $newUserId, 'tenant', $tenantId, null, (string) $name);

        $setupUrl = url('back-office/configuration-initiale');

        if ($withHeartSupport && BillingProvider::anyConfigured()) {
            try {
                $successUrl = $setupUrl . (str_contains($setupUrl, '?') ? '&' : '?') . 'soutien=merci';
                $provider = BillingProvider::preferred();
                if ($provider === BillingProvider::PAYPAL) {
                    $session = $this->payPalCheckoutService->createOrder(
                        200,
                        'EUR',
                        'Support du cœur — Athena (2 €)',
                        $successUrl,
                        $setupUrl,
                        [
                            'kind' => 'heart',
                            'uid' => (string) $newUserId,
                            'tid' => (string) $tenantId,
                        ]
                    );
                } else {
                    $session = $this->stripeCheckoutService->createPaymentCheckoutSession(
                        200,
                        'eur',
                        'Support du cœur — Athena',
                        'Contribution volontaire de 2 € pour soutenir le projet',
                        $successUrl,
                        $setupUrl,
                        $creatorEmail !== '' ? $creatorEmail : null,
                        [
                            'kind' => 'community_heart_support',
                            'user_id' => (string) $newUserId,
                            'tenant_id' => (string) $tenantId,
                            'amount_cents' => '200',
                        ]
                    );
                }
                Session::flash('success', 'Communauté créée. Merci pour votre soutien — finalisez le paiement sécurisé de 2 €.');

                return Response::redirect($session['url']);
            } catch (\Throwable) {
                Session::flash(
                    'success',
                    'Communauté créée. Merci pour votre intention de soutien ; le paiement n’est pas disponible pour le moment. Finalisez les derniers réglages essentiels.'
                );

                return Response::redirect($setupUrl);
            }
        }

        if ($withHeartSupport) {
            Session::flash(
                'success',
                'Communauté créée. Merci pour votre intention de soutien. Finalisez les derniers réglages essentiels.'
            );
        } else {
            Session::flash('success', 'Communauté créée. Finalisez les derniers réglages essentiels.');
        }
        if ($uploadWarnings !== []) {
            Session::flash('warning', implode(' ', $uploadWarnings));
        }

        return Response::redirect($setupUrl);
    }

    /**
     * @param list<string> $uploadWarnings
     */
    private function flashCommunityCreateErrorAndRedirect(
        Request $request,
        string $message,
        string $wizardStep = '',
        array $uploadWarnings = []
    ): Response {
        Session::flash('error', $message);
        if ($wizardStep !== '') {
            Session::flash('onboarding_step', $wizardStep);
        }
        if ($uploadWarnings !== []) {
            Session::flash('warning', implode(' ', $uploadWarnings));
        }
        $draft = $request->all();
        unset($draft['_csrf_token']);
        Session::set('community_create_draft', $draft);

        return Response::redirect(url('communities/create'));
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

    /** @return array{0: string, 1: string}|null null = gratuit / soutien volontaire */
    private function parsePaidPlanChoice(string $planChoice): ?array
    {
        if ($planChoice === 'free' || $planChoice === 'heart_support') {
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

    private function paypalPlanIdForInterval(array $planRow, string $interval): ?string
    {
        if ($interval === 'monthly') {
            $id = trim((string) ($planRow['paypal_plan_id_monthly'] ?? ''));

            return $id !== '' ? $id : null;
        }
        if ($interval === 'yearly') {
            $id = trim((string) ($planRow['paypal_plan_id_yearly'] ?? ''));

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
                if (!is_array($decoded)) {
                    throw new \InvalidArgumentException('La structure des unités n’est pas valide. Vérifiez l’organigramme à l’étape Organisation.');
                }
                $units = $decoded;
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
        $uploadResult = $this->communityWizardUploadService->processUploadsWithFeedback($uid);
        foreach ($uploadResult['urls'] as $k => $v) {
            $wizard[$k] = $v;
        }
        if ($uploadResult['warnings'] !== []) {
            $wizard['_upload_warnings'] = $uploadResult['warnings'];
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

    /**
     * Destination « Retour » pour la vitrine publique.
     * Priorité : Referer interne sûr → registre public (/communities).
     */
    private function resolveShowcaseBackUrl(Request $request, string $slug): string
    {
        $base = rtrim(url(''), '/');
        $selfPath = '/c/' . rawurlencode($slug);
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer !== '' && str_starts_with($referer, $base)) {
            $pathWithQuery = substr($referer, strlen($base));
            if ($pathWithQuery === false || $pathWithQuery === '') {
                $pathWithQuery = '/';
            }
            $pathOnly = parse_url($pathWithQuery, PHP_URL_PATH);
            $pathOnly = is_string($pathOnly) ? $pathOnly : $pathWithQuery;
            $pathOnly = '/' . ltrim($pathOnly, '/');
            $isSelf = $pathOnly === $selfPath
                || str_starts_with($pathOnly, $selfPath . '/');
            if (!$isSelf && !str_starts_with($pathWithQuery, '//') && !str_contains($pathWithQuery, '://')) {
                if ($pathWithQuery === '/') {
                    return url('');
                }

                return url(ltrim($pathWithQuery, '/'));
            }
        }

        return url('communities');
    }
}
