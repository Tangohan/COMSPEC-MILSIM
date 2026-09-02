<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Gate;
use App\Services\Portal\PortalNextStepsService;

class HomeController
{
    public function index(Request $request, array $params = []): Response
    {
        $days = 30;
        $platformKpis = [
            'communities_total' => 0,
            'users_active_total' => 0,
            'forum_posts_in_period' => 0,
            'training_completions_in_period' => 0,
            'enlistments_created_in_period' => 0,
            'usage_events_in_period' => 0,
        ];

        try {
            /** @var \App\Repositories\TenantAnalyticsRepository $analyticsRepo */
            $analyticsRepo = \App\Core\Container::get(\App\Repositories\TenantAnalyticsRepository::class);
            $raw = $analyticsRepo->getPlatformOperationalKpis($days);
            $platformKpis['communities_total'] = (int) ($raw['communities_total'] ?? 0);
            $platformKpis['users_active_total'] = (int) ($raw['users_active_total'] ?? 0);
            $platformKpis['forum_posts_in_period'] = (int) ($raw['forum_posts_in_period'] ?? 0);
            $platformKpis['training_completions_in_period'] = (int) ($raw['training_completions_in_period'] ?? 0);
            $platformKpis['enlistments_created_in_period'] = (int) ($raw['enlistments_created_in_period'] ?? 0);
            $platformKpis['usage_events_in_period'] = (int) ($raw['usage_events_in_period'] ?? 0);
        } catch (\Throwable) {
            // La home publique doit rester disponible même si les tables analytics ne sont pas prêtes.
        }

        $featuredUnits = [];
        try {
            /** @var \App\Repositories\TenantRepository $tenantRepo */
            $tenantRepo = \App\Core\Container::get(\App\Repositories\TenantRepository::class);
            foreach ($tenantRepo->listForRegistry() as $row) {
                $logo = trim((string) ($row['logo_url'] ?? ''));
                if ($logo === '') {
                    continue;
                }
                $featuredUnits[] = [
                    'name' => community_display_name($row),
                    'slug' => (string) ($row['slug'] ?? ''),
                    'logo_url' => $logo,
                    'href' => url('c/' . rawurlencode((string) ($row['slug'] ?? ''))),
                ];
                if (count($featuredUnits) >= 10) {
                    break;
                }
            }
        } catch (\Throwable) {
            $featuredUnits = [];
        }

        return Response::view('home.index', [
            'title' => 'Athena Comspec — Portail MILSIM',
            'platformKpis' => $platformKpis,
            'platformKpiDays' => $days,
            'featuredUnits' => $featuredUnits,
        ]);
    }

    /** Page d’information sur les offres (fondateurs, essai Pro, Stripe). */
    public function platformUpgrade(Request $request, array $params = []): Response
    {
        $from = trim((string) $request->query('from', ''));
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId > 0 && $from !== '') {
            try {
                \App\Core\Container::get(\App\Repositories\PlatformUsageRepository::class)->record(
                    $tenantId,
                    Session::get('user_id') ? (int) Session::get('user_id') : null,
                    'upgrade_view',
                    $from
                );
            } catch (\Throwable) {
            }
        }

        return Response::view('layout.main', [
            'title' => 'Offres',
            'content' => 'platform.upgrade',
            'feature' => 'offre',
            'planName' => 'Standard ou Pro',
            'upgradeFrom' => $from,
        ]);
    }

    public function dashboard(Request $request, array $params = []): Response
    {
        $modpack = null;
        $currentUser = null;
        $personnelExtras = null;
        $personnelProfile = null;
        $grade = null;
        $atakModDownloadUrl = null;
        $communityMemberships = [];
        $founderTrialEndsAt = null;
        $showFounderTrialBanner = false;
        $email = Session::get('email');
        if ($email) {
            $userRepo = \App\Core\Container::get(\App\Repositories\UserRepository::class);
            $allMemberships = $userRepo->listTenantsForEmail((string) $email);
            $communityMemberships = $userRepo->filterSwitchableTenantsForUser($allMemberships);
        }
        $tenantId = Session::get('tenant_id');
        if ($tenantId) {
            try {
                $usage = \App\Core\Container::get(\App\Repositories\PlatformUsageRepository::class);
                $uid = Session::get('user_id') ? (int) Session::get('user_id') : null;
                $usage->record((int) $tenantId, $uid, 'dashboard_visit', 'view');
            } catch (\Throwable) {
            }
            $modpackRepo = \App\Core\Container::get(\App\Repositories\ModpackRepository::class);
            $modpack = $modpackRepo->getPrimaryForTenant((int) $tenantId);
            $auth = \App\Core\Container::get(\App\Services\Auth\AuthService::class);
            $currentUser = $auth->user();
            if ($currentUser) {
                $extrasRepo = \App\Core\Container::get(\App\Repositories\PersonnelExtrasRepository::class);
                $gradeRepo = \App\Core\Container::get(\App\Repositories\GradeRepository::class);
                $personnelExtras = $extrasRepo->getByUserId((int) $currentUser['id']);
                $personnelProfile = \App\Core\Container::get(\App\Repositories\PersonnelProfileRepository::class)
                    ->getByUserId((int) $currentUser['id']);
                if (!empty($currentUser['grade_id'])) {
                    $grade = $gradeRepo->findById((int) $currentUser['grade_id'], (int) $tenantId);
                }
                $tenantRow = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById((int) $tenantId);
                if ($tenantRow) {
                    $status = (string) ($tenantRow['subscription_status'] ?? 'none');
                    $paid = in_array($status, ['active', 'trialing'], true);
                    $owner = (int) ($tenantRow['owner_user_id'] ?? 0) === (int) $currentUser['id'];
                    $rawS = $tenantRow['settings'] ?? null;
                    $decoded = [];
                    if (is_string($rawS) && trim($rawS) !== '') {
                        $decoded = json_decode($rawS, true);
                        if (!is_array($decoded)) {
                            $decoded = [];
                        }
                    }
                    $end = $decoded['founder_trial_ends_at'] ?? null;
                    if ($owner && ! $paid && is_string($end) && $end !== '') {
                        $ts = strtotime($end);
                        if ($ts !== false && $ts > time()) {
                            $founderTrialEndsAt = $end;
                            $showFounderTrialBanner = true;
                        }
                    }
                }
            }
            $modPath = dirname(__DIR__, 2) . '/../storage/atak-mod/' . $tenantId . '/comspec-overwatch.zip';
            if (is_file($modPath) && is_readable($modPath)) {
                $atakModDownloadUrl = url('atak/mod');
            }
        }

        $dashboardTenantLabel = null;
        $showcaseTrainingFeature = false;
        $showcaseItems = [];
        $showcaseKitFeature = false;
        $showcaseKitItems = [];
        $canManageKitPins = false;
        $myEnlistmentsPending = [];
        $staffEnlistmentsPending = [];
        $showStaffEnlistments = false;
        $dashboardPins = [];
        $dashboardAnnounceItems = [];
        $dashboardPopupItems = [];
        $dashboardMiniArticles = [];
        $followedChannels = [];
        $missionBriefing = null;
        $dashboardTesterProgram = null;
        $candidateEnlistmentTracking = [];
        $myApplicationsAll = [];
        $staffApplicationsAll = [];
        $candidateDossierNumber = null;
        $canManageInvitations = false;
        $pendingInvitationsCount = 0;
        $emailAlertsDisabledCount = 0;
        $canViewAtakOperators = false;
        $atakOperatorsLinkedCount = null;
        $dashboardIsDefaultTenant = false;
        $dashboardEffectifsRows = [];
        $canViewPersonnelDirectory = false;
        $canOpenEffectifsWorkspace = false;
        $canSeeInactiveEffectifs = false;
        $armaPlaytimeLabel = null;
        $armaPlaytimeSeconds = 0;
        $dashboardTenantType = \App\Services\Community\TenantTypeConfig::TYPE_FULL;
        $dashboardRhParcours = null;
        $dashboardPublishedOpenings = [];
        $dashboardTenantSlug = '';
        $canManageRecruitmentOffers = false;
        $dashboardElevationCatalog = ['grades' => [], 'roles' => [], 'job_roles' => [], 'units' => [], 'clearance_levels' => []];
        $canRequestSelfElevation = false;
        $elevationNoRecipients = false;
        $elevationCooldownSeconds = 0;
        $elevationHistoryMine = [];
        $rhMyMobility = [];
        $rhMobilitySchemaReady = false;
        $canPublishDashboardArticles = false;
        if ($tenantId) {
            $tid = (int) $tenantId;
            $tenantRow = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById($tid);
            if ($tenantRow) {
                $dashboardTenantLabel = community_display_name($tenantRow);
                $dashboardIsDefaultTenant = ($tenantRow['slug'] ?? '') === 'default';
                $dashboardTenantType = \App\Services\Community\TenantTypeConfig::normalizeType(
                    (string) ($tenantRow['tenant_type'] ?? 'full')
                );
            }
            $allowsTraining = \App\Services\Community\TenantTypeConfig::moduleAllowed($dashboardTenantType, 'training');
            $allowsRecruitment = \App\Services\Community\TenantTypeConfig::moduleAllowed($dashboardTenantType, 'recruitment');
            $allowsForum = \App\Services\Community\TenantTypeConfig::moduleAllowed($dashboardTenantType, 'forum');
            $allowsPersonnel = \App\Services\Community\TenantTypeConfig::moduleAllowed($dashboardTenantType, 'personnel');
            $allowsAtak = \App\Services\Community\TenantTypeConfig::moduleAllowed($dashboardTenantType, 'atak');

            $showcaseTrainingFeature = $allowsTraining
                && \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tid, 'training');
            if ($showcaseTrainingFeature) {
                $rows = \App\Core\Container::get(\App\Repositories\TrainingCourseRepository::class)->listPublishedForDashboard($tid, 20);
                $showcaseItems = self::buildTrainingShowcasePayload($rows);
            }

            $showcaseKitFeature = false;
            $showcaseKitItems = [];
            $canManageKitPins = false;
            $allowsEquipment = \App\Services\Community\TenantTypeConfig::uriAllowed($dashboardTenantType, 'equipment')
                && \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tid, 'equipment');
            if ($allowsEquipment) {
                try {
                    $showcaseKitItems = \App\Core\Container::get(\App\Services\Dashboard\DashboardWardrobeShowcaseService::class)
                        ->listForDashboard($tid);
                } catch (\Throwable) {
                    $showcaseKitItems = [];
                }
                $canManageKitPins = $currentUser !== null && \App\Authorization\DashboardPinsAccess::canManage();
                $showcaseKitFeature = $showcaseKitItems !== [] || $canManageKitPins;
            }

            if ($currentUser) {
                $uid = (int) $currentUser['id'];
                $uemail = (string) ($currentUser['email'] ?? '');
                $enlistRepo = \App\Core\Container::get(\App\Repositories\EnlistmentRepository::class);
                if ($allowsRecruitment) {
                    $myEnlistmentsPending = $enlistRepo->listPendingSubmittedForSubmitter($tid, $uid, $uemail);
                    if ($dashboardIsDefaultTenant) {
                        $candidateRows = $enlistRepo->listRecentForSubmitterAcrossTenants($uid, $uemail, 8);
                        foreach ($candidateRows as $row) {
                            $candidateTid = (int) ($row['tenant_id'] ?? 0);
                            $candidateId = (int) ($row['id'] ?? 0);
                            if ($candidateTid < 1 || $candidateId < 1) {
                                continue;
                            }
                            $token = $enlistRepo->findValidCandidatePortalTokenForEnlistment($candidateTid, $candidateId);
                            if ($token === null) {
                                $token = $enlistRepo->ensureCandidatePortalToken($candidateTid, $candidateId, 24 * 7);
                            }
                            $row['candidate_portal_href'] = $token !== null
                                ? url('enlistment/suivi/' . rawurlencode($token))
                                : null;
                            $candidateEnlistmentTracking[] = $row;
                        }
                    }

                    // Tableau complet « Mes candidatures » (tous statuts, toutes communautés) pour le dashboard.
                    try {
                        $openingRepoForMine = \App\Core\Container::get(\App\Repositories\RecruitmentOpeningRepository::class);
                        $openingsTableReady = $openingRepoForMine->tablesExist();
                        $myApplicationsRows = $enlistRepo->listRecentForSubmitterAcrossTenants($uid, $uemail, 20);
                        foreach ($myApplicationsRows as $row) {
                            $appTid = (int) ($row['tenant_id'] ?? 0);
                            $appId = (int) ($row['id'] ?? 0);
                            if ($appTid < 1 || $appId < 1) {
                                continue;
                            }
                            $appToken = $enlistRepo->findValidCandidatePortalTokenForEnlistment($appTid, $appId);
                            if ($appToken === null) {
                                $appToken = $enlistRepo->ensureCandidatePortalToken($appTid, $appId, 24 * 7);
                            }
                            $row['candidate_portal_href'] = $appToken !== null
                                ? url('enlistment/suivi/' . rawurlencode($appToken))
                                : null;
                            $row['opening_title'] = null;
                            $openingId = (int) ($row['recruitment_opening_id'] ?? 0);
                            if ($openingId > 0 && $openingsTableReady) {
                                try {
                                    $openingRow = $openingRepoForMine->findByIdForTenant($openingId, $appTid);
                                    if ($openingRow) {
                                        $row['opening_title'] = trim((string) ($openingRow['title'] ?? '')) ?: null;
                                    }
                                } catch (\Throwable) {
                                    $row['opening_title'] = null;
                                }
                            }
                            $myApplicationsAll[] = $row;
                        }
                    } catch (\Throwable) {
                        $myApplicationsAll = [];
                    }
                }

                $gate = \App\Core\Gate::getInstance();
                $roleSlug = \App\Core\Container::get(\App\Repositories\UserRepository::class)->getRoleSlugForUser($uid) ?? '';
                $staffSlugs = ['recruiter', 'community_owner', 'hr', 'tenant_admin'];
                $showStaffEnlistments = $allowsRecruitment && ($gate->allows('admin.organization') || $gate->allows('admin.access')
                    || in_array($roleSlug, $staffSlugs, true));
                if ($showStaffEnlistments) {
                    $staffEnlistmentsPending = $enlistRepo->listPendingSubmittedForTenant($tid, 25);
                    try {
                        $staffApplicationsAll = self::enrichStaffApplicationsForDashboard(
                            $enlistRepo->recentForTenantDashboard($tid, 40),
                            $tid
                        );
                    } catch (\Throwable) {
                        $staffApplicationsAll = [];
                    }
                }

                $canViewPersonnelDirectory = $allowsPersonnel && $gate->allows('personnel.profile.view');
                $canOpenEffectifsWorkspace = $allowsPersonnel && \App\Support\EffectifsLmsAccess::allows($gate);
                $canSeeInactiveEffectifs = $canOpenEffectifsWorkspace
                    || \App\Support\EffectifsLmsAccess::canManageStatus($gate);
                if ($canViewPersonnelDirectory && !$dashboardIsDefaultTenant) {
                    try {
                        $dashboardEffectifsRows = \App\Core\Container::get(\App\Repositories\UserRepository::class)
                            ->listPersonnelDirectoryRich($tid, '', 40, $canSeeInactiveEffectifs);
                    } catch (\Throwable) {
                        $dashboardEffectifsRows = [];
                    }
                }

                // Temps de mission transmis par ATAK (cumul user_arma_playtime).
                try {
                    $playtimeRepo = \App\Core\Container::get(\App\Repositories\ArmaPlaytimeRepository::class);
                    $playtimeUserIds = [];
                    foreach ($dashboardEffectifsRows as $effRow) {
                        if (is_array($effRow)) {
                            $playtimeUserIds[] = (int) ($effRow['id'] ?? 0);
                        }
                    }
                    if ($uid > 0) {
                        $playtimeUserIds[] = $uid;
                    }
                    $playtimeByUser = $playtimeRepo->summariesForUsers($tid, $playtimeUserIds);
                    foreach ($dashboardEffectifsRows as &$effRow) {
                        if (!is_array($effRow)) {
                            continue;
                        }
                        $effUid = (int) ($effRow['id'] ?? 0);
                        $secs = (int) (($playtimeByUser[$effUid]['total_seconds'] ?? 0));
                        $effRow['arma_playtime_seconds'] = $secs;
                        $effRow['arma_playtime_label'] = $secs > 0
                            ? format_arma_playtime_french($secs)
                            : null;
                    }
                    unset($effRow);
                    if ($uid > 0) {
                        $selfSecs = (int) (($playtimeByUser[$uid]['total_seconds'] ?? 0));
                        $armaPlaytimeLabel = $selfSecs > 0
                            ? format_arma_playtime_french($selfSecs)
                            : null;
                        $armaPlaytimeSeconds = $selfSecs;
                    }
                } catch (\Throwable) {
                    // Table absente ou indisponible : le dashboard reste utilisable sans cette colonne.
                }

                try {
                    $seniorityUserIds = [];
                    $enlistmentByUser = [];
                    foreach ($dashboardEffectifsRows as $effRow) {
                        if (!is_array($effRow)) {
                            continue;
                        }
                        $effUid = (int) ($effRow['id'] ?? 0);
                        if ($effUid < 1) {
                            continue;
                        }
                        $seniorityUserIds[] = $effUid;
                        $enlist = trim((string) ($effRow['enlistment_date_resolved'] ?? $effRow['enlistment_date'] ?? $effRow['date_of_enlistment'] ?? ''));
                        if ($enlist !== '') {
                            $enlistmentByUser[$effUid] = $enlist;
                        }
                    }
                    $seniorityByUser = \App\Core\Container::get(\App\Services\Personnel\SenioritySummaryService::class)
                        ->dashboardLabelsByUsers($tid, $seniorityUserIds, $enlistmentByUser);
                    foreach ($dashboardEffectifsRows as &$effRow) {
                        if (!is_array($effRow)) {
                            continue;
                        }
                        $effUid = (int) ($effRow['id'] ?? 0);
                        $pack = is_array($seniorityByUser[$effUid] ?? null) ? $seniorityByUser[$effUid] : [];
                        $effRow['seniority_days'] = (int) ($pack['days'] ?? 0);
                        $effRow['seniority_label'] = trim((string) ($pack['label'] ?? ''));
                    }
                    unset($effRow);
                } catch (\Throwable) {
                    // Module d’ancienneté absent : le tableau reste lisible sans cette colonne.
                }

                try {
                    $availUserIds = [];
                    $availJoinedAt = [];
                    foreach ($dashboardEffectifsRows as $effRow) {
                        if (!is_array($effRow)) {
                            continue;
                        }
                        $effUid = (int) ($effRow['id'] ?? 0);
                        if ($effUid < 1) {
                            continue;
                        }
                        $availUserIds[] = $effUid;
                        $enlist = trim((string) ($effRow['enlistment_date_resolved'] ?? $effRow['enlistment_date'] ?? $effRow['date_of_enlistment'] ?? ''));
                        if ($enlist !== '') {
                            $availJoinedAt[$effUid] = $enlist;
                        }
                    }
                    $availCounts = \App\Core\Container::get(\App\Repositories\CommunityEventRepository::class)
                        ->availabilityCountsForUsers(
                            $tid,
                            $availUserIds,
                            \App\Services\Personnel\MemberAvailabilityRate::WINDOW_DAYS,
                            $availJoinedAt
                        );
                    foreach ($dashboardEffectifsRows as &$effRow) {
                        if (!is_array($effRow)) {
                            continue;
                        }
                        $effUid = (int) ($effRow['id'] ?? 0);
                        $counts = is_array($availCounts[$effUid] ?? null) ? $availCounts[$effUid] : [];
                        $effRow['availability_90'] = \App\Services\Personnel\MemberAvailabilityRate::fromCounts(
                            (int) ($counts['events'] ?? 0),
                            (int) ($counts['yes'] ?? 0),
                            (int) ($counts['checked_in'] ?? 0)
                        );
                    }
                    unset($effRow);
                } catch (\Throwable) {
                    // Calendrier ou présences indisponibles : le tableau reste lisible sans cette colonne.
                }

                if ($allowsRecruitment) {
                    try {
                        $latestDossier = $enlistRepo->findLatestBySubmitter($tid, $uid);
                        $candidateDossierNumber = $latestDossier ? (int) ($latestDossier['id'] ?? 0) : null;
                        if ($candidateDossierNumber === 0) {
                            $candidateDossierNumber = null;
                        }
                    } catch (\Throwable) {
                        $candidateDossierNumber = null;
                    }
                }

                $canManageInvitations = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('invitations.send');
                if ($canManageInvitations) {
                    try {
                        $invitationRepo = \App\Core\Container::get(\App\Repositories\CommunityInvitationRepository::class);
                        $pendingInvitationsCount = count($invitationRepo->listForTenant($tid, 'pending'));
                    } catch (\Throwable) {
                        $pendingInvitationsCount = 0;
                    }
                }

                $canViewAtakOperators = $allowsAtak && ($gate->allows('admin.system')
                    || $gate->allows('admin.organization')
                    || $gate->allows('admin.access'));
                if ($canViewAtakOperators) {
                    try {
                        $mapRepo = \App\Core\Container::get(\App\Repositories\AtakMapRepository::class);
                        $defaultMap = $mapRepo->getDefaultForTenant($tid);
                        $mapId = $defaultMap ? (int) ($defaultMap['id'] ?? 1) : 1;
                        if ($mapId < 1) {
                            $mapId = 1;
                        }
                        $units = \App\Core\Container::get(\App\Repositories\AtakDataRepository::class)
                            ->getUnits($tid, $mapId);
                        $linked = 0;
                        foreach ($units as $unit) {
                            if (!is_array($unit)) {
                                continue;
                            }
                            $st = (string) ($unit['status'] ?? '');
                            if ($st === 'linked' || $st === 'delayed') {
                                ++$linked;
                            }
                        }
                        $atakOperatorsLinkedCount = $linked;
                    } catch (\Throwable) {
                        $atakOperatorsLinkedCount = null;
                    }
                }

                try {
                    $notifPrefRepo = \App\Core\Container::get(\App\Repositories\UserNotificationPreferencesRepository::class);
                    foreach ($notifPrefRepo->listForUser($uid) as $notifRow) {
                        if ((string) ($notifRow['channel'] ?? '') === 'email' && (int) ($notifRow['enabled'] ?? 1) === 0) {
                            ++$emailAlertsDisabledCount;
                        }
                    }
                } catch (\Throwable) {
                    $emailAlertsDisabledCount = 0;
                }

                try {
                    $dashboardPins = \App\Core\Container::get(\App\Services\Dashboard\TenantDashboardPinService::class)
                        ->listResolvedPinsForViewer($tid, $uid);
                } catch (\Throwable) {
                    $dashboardPins = [];
                }

                if ($allowsForum) {
                    try {
                        $followedChannelsRows = \App\Core\Container::get(\App\Repositories\ForumCategoryRepository::class)
                            ->listSubscribedForUser($uid, $tid);
                        foreach ($followedChannelsRows as $fc) {
                            $lastTopicId = (int) ($fc['last_topic_id'] ?? 0);
                            $followedChannels[] = [
                                'name' => (string) ($fc['name'] ?? ''),
                                'slug' => (string) ($fc['slug'] ?? ''),
                                'href' => url('forum/category/' . rawurlencode((string) ($fc['slug'] ?? ''))),
                                'icon' => (string) ($fc['icon'] ?? ''),
                                'unread_count' => (int) ($fc['unread_count'] ?? 0),
                                'last_topic_title' => trim((string) ($fc['last_topic_title'] ?? '')),
                                'last_topic_href' => $lastTopicId > 0 ? url('forum/topic/' . $lastTopicId) : null,
                                'last_activity_at' => $fc['last_activity_at'] ?? null,
                            ];
                        }
                    } catch (\Throwable) {
                        $followedChannels = [];
                    }
                }

                try {
                    $alertRows = \App\Core\Container::get(\App\Services\Alerts\AlertPresentationService::class)
                        ->forCurrentRequest();
                    foreach ($alertRows as $alert) {
                        $style = (string) ($alert['display_style'] ?? 'classic');
                        if (\App\Support\AlertDisplayStyle::isNavbarStyle($style)) {
                            continue;
                        }
                        if (\App\Support\AlertDisplayStyle::isPopupStyle($style)) {
                            $dashboardPopupItems[] = [
                                'scope' => (string) ($alert['scope'] ?? 'tenant'),
                                'id' => (int) ($alert['id'] ?? 0),
                                'kind' => (string) ($alert['kind'] ?? 'info'),
                                'title' => (string) ($alert['title'] ?? ''),
                                'body' => (string) ($alert['body'] ?? ''),
                                'cta_label' => $alert['cta_label'] ?? null,
                                'cta_url' => $alert['cta_url'] ?? null,
                                'accent_color' => $alert['accent_color'] ?? null,
                                'image_url' => $alert['image_url'] ?? null,
                                'banner_url' => $alert['banner_url'] ?? null,
                                'dismissible' => !array_key_exists('dismissible', $alert) || (bool) $alert['dismissible'],
                            ];

                            continue;
                        }
                        $dashboardAnnounceItems[] = [
                            'scope' => (string) ($alert['scope'] ?? 'tenant'),
                            'kind' => (string) ($alert['kind'] ?? 'info'),
                            'title' => (string) ($alert['title'] ?? ''),
                            'body' => (string) ($alert['body'] ?? ''),
                            'cta_label' => $alert['cta_label'] ?? null,
                            'cta_url' => $alert['cta_url'] ?? null,
                            'accent_color' => $alert['accent_color'] ?? null,
                            'image_url' => $alert['image_url'] ?? null,
                        ];
                    }
                } catch (\Throwable) {
                    // Les tuiles restent optionnelles si le service d’alertes est indisponible.
                }

                foreach ($dashboardPins as $pin) {
                    if ((string) ($pin['kind'] ?? '') !== 'notice') {
                        continue;
                    }
                    $noticeBody = trim((string) ($pin['notice_text'] ?? ''));
                    if ($noticeBody === '') {
                        continue;
                    }
                    $dashboardAnnounceItems[] = [
                        'kind' => 'notice',
                        'category' => 'Annonce',
                        'title' => (string) ($pin['label'] ?? 'Annonce'),
                        'body' => $noticeBody,
                        'cta_label' => null,
                        'cta_url' => null,
                    ];
                }

                if ($allowsForum) {
                    try {
                        /** @var \App\Repositories\ForumTopicRepository $forumTopicRepo */
                        $forumTopicRepo = \App\Core\Container::get(\App\Repositories\ForumTopicRepository::class);
                        $forumDashPins = $forumTopicRepo->listPinnedOnDashboardForTenant($tid, 8);
                        foreach ($forumDashPins as $ftPin) {
                            $title = trim((string) ($ftPin['title'] ?? ''));
                            if ($title === '') {
                                continue;
                            }
                            $rawBody = trim((string) ($ftPin['first_post_body'] ?? ''));
                            $excerpt = self::plainTextExcerpt($rawBody, 220);
                            $topicId = (int) ($ftPin['id'] ?? 0);
                            $dashboardAnnounceItems[] = [
                                'kind' => 'forum_pin',
                                'category' => 'Message épinglé',
                                'title' => $title,
                                'body' => $excerpt,
                                'cta_label' => 'Ouvrir le message',
                                'cta_url' => $topicId > 0 ? url('forum/topic/' . $topicId) : null,
                                'scope' => 'tenant',
                            ];
                        }
                    } catch (\Throwable) {
                        // Optionnel si le schéma forum n’est pas prêt.
                    }
                }

                if ($dashboardTenantType === \App\Services\Community\TenantTypeConfig::TYPE_FULL) {
                    try {
                        $missionBriefing = \App\Core\Container::get(\App\Services\Dashboard\MemberMissionBriefingService::class)
                            ->buildForViewer($tid, $uid, $modpack, $dashboardPins, $showcaseTrainingFeature);
                    } catch (\Throwable) {
                        $missionBriefing = null;
                    }
                }

                try {
                    $releaseRepo = \App\Core\Container::get(\App\Repositories\PlatformModuleReleaseRepository::class);
                    if ($releaseRepo->schemaReady()) {
                        $testerCommunities = $releaseRepo->listActiveTesterCommunitiesForUser($uid);
                        if ($testerCommunities !== []) {
                            $modRows = $releaseRepo->listModuleAccessRowsForUserTesterCommunities($uid);
                            $dashboardTesterProgram = [
                                'communities' => $testerCommunities,
                                'modules' => self::buildDashboardTesterProgramModules($modRows),
                            ];
                        }
                    }
                } catch (\Throwable) {
                    $dashboardTesterProgram = null;
                }

                if (!$dashboardIsDefaultTenant && ($allowsPersonnel || $allowsRecruitment)) {
                    try {
                        $tenantSlug = is_array($tenantRow) ? (string) ($tenantRow['slug'] ?? '') : '';
                        $dashboardRhParcours = \App\Support\DashboardRhParcours::build(
                            $tid,
                            $uid,
                            $tenantSlug,
                            $allowsPersonnel,
                            $allowsRecruitment
                        );
                    } catch (\Throwable) {
                        $dashboardRhParcours = null;
                    }
                }

                if (!$dashboardIsDefaultTenant && $allowsRecruitment) {
                    try {
                        $openingRepo = \App\Core\Container::get(\App\Repositories\RecruitmentOpeningRepository::class);
                        $dashboardPublishedOpenings = $openingRepo->listPublishedForTenant($tid);
                        $dashboardTenantSlug = is_array($tenantRow)
                            ? trim((string) ($tenantRow['slug'] ?? ''))
                            : '';
                        $canManageRecruitmentOffers = $gate->allows('organization.recruitment.openings.manage')
                            || $gate->allows('organization.recruitment.manage')
                            || $gate->allows('admin.organization')
                            || $gate->allows('admin.access');
                    } catch (\Throwable) {
                        $dashboardPublishedOpenings = [];
                    }
                }

                if (!$dashboardIsDefaultTenant && $allowsPersonnel) {
                    try {
                        $staffAlert = \App\Core\Container::get(\App\Services\Effectifs\EffectifsStaffAlertService::class);
                        $elevationRecipients = $staffAlert->listElevationRecipients($tid, $uid);
                        $canRequestSelfElevation = $elevationRecipients !== [];
                        $elevationNoRecipients = $elevationRecipients === [];
                        $wait = $staffAlert->secondsBeforeNextElevationRequest($uid, $uid);
                        $elevationCooldownSeconds = $wait !== null ? (int) $wait : 0;
                        $elevationRepo = \App\Core\Container::get(\App\Repositories\ElevationRequestRepository::class);
                        $elevationHistoryMine = $elevationRepo->listForTarget($tid, $uid, 8);
                        $catalogService = \App\Core\Container::get(\App\Services\Effectifs\ElevationCatalogService::class);
                        $dashboardElevationCatalog = $catalogService->catalogForTenant($tid);
                    } catch (\Throwable) {
                        $canRequestSelfElevation = false;
                        $elevationNoRecipients = true;
                    }
                    try {
                        $mobilityRepo = \App\Core\Container::get(\App\Repositories\PersonnelMobilityRequestRepository::class);
                        $rhMobilitySchemaReady = $mobilityRepo->tableExists();
                        $rhMyMobility = $rhMobilitySchemaReady
                            ? $mobilityRepo->listForUser($tid, $uid, 8)
                            : [];
                    } catch (\Throwable) {
                        $rhMobilitySchemaReady = false;
                        $rhMyMobility = [];
                    }
                }

                $canPublishDashboardArticles = $gate->allows('admin.organization')
                    || $gate->allows('admin.access')
                    || $gate->allows('site.support');

                try {
                    $miniRows = \App\Core\Container::get(\App\Repositories\TenantMiniArticleRepository::class)
                        ->listPublishedForTenant($tid, 6);
                    foreach ($miniRows as $miniRow) {
                        $tags = [];
                        $rawTags = $miniRow['tags_json'] ?? null;
                        if (is_string($rawTags) && $rawTags !== '') {
                            $decoded = json_decode($rawTags, true);
                            if (is_array($decoded)) {
                                foreach ($decoded as $t) {
                                    $t = trim((string) $t);
                                    if ($t !== '') {
                                        $tags[] = $t;
                                    }
                                }
                            }
                        }
                        $dashboardMiniArticles[] = [
                            'id' => (int) ($miniRow['id'] ?? 0),
                            'title' => (string) ($miniRow['title'] ?? ''),
                            'slug' => (string) ($miniRow['slug'] ?? ''),
                            'excerpt' => trim((string) ($miniRow['excerpt'] ?? '')),
                            'tags' => $tags,
                            'cover_url' => \App\Support\MiniArticleHtml::publicUrl(
                                isset($miniRow['cover_path']) ? (string) $miniRow['cover_path'] : null
                            ),
                            'pinned' => !empty($miniRow['pinned']),
                            'published_at' => isset($miniRow['published_at']) ? (string) $miniRow['published_at'] : null,
                            'href' => url('articles/' . rawurlencode((string) ($miniRow['slug'] ?? ''))),
                        ];
                    }
                } catch (\Throwable) {
                    $dashboardMiniArticles = [];
                }
            }
        }

        $dashboardNextSteps = [];
        if ($tenantId && $currentUser) {
            $gate = Gate::getInstance();
            $dashboardNextSteps = PortalNextStepsService::forDashboard(
                $gate,
                $myEnlistmentsPending !== [],
                $showStaffEnlistments && $staffEnlistmentsPending !== [],
                $showcaseTrainingFeature
            );
        }

        return Response::view(\App\Services\Community\TenantTypeConfig::dashboardView($dashboardTenantType), [
            'title' => match ($dashboardTenantType) {
                \App\Services\Community\TenantTypeConfig::TYPE_ATAK => 'Carte ATAK — Athena',
                \App\Services\Community\TenantTypeConfig::TYPE_EFFECTIFS => 'Bureau des effectifs — Athena',
                default => 'Dashboard — Athena',
            },
            'modpack' => $modpack,
            'currentUser' => $currentUser,
            'personnelExtras' => $personnelExtras,
            'personnelProfile' => $personnelProfile,
            'grade' => $grade,
            'atakModDownloadUrl' => $atakModDownloadUrl,
            'communityMemberships' => $communityMemberships,
            'founder_trial_ends_at' => $founderTrialEndsAt,
            'show_founder_trial_banner' => $showFounderTrialBanner,
            'dashboard_tenant_label' => $dashboardTenantLabel,
            'dashboard_is_default_tenant' => $dashboardIsDefaultTenant,
            'dashboard_tenant_type' => $dashboardTenantType,
            'showcase_training_feature' => $showcaseTrainingFeature,
            'showcase_items' => $showcaseItems,
            'showcase_kit_feature' => $showcaseKitFeature,
            'showcase_kit_items' => $showcaseKitItems,
            'can_manage_kit_pins' => $canManageKitPins,
            'my_enlistments_pending' => $myEnlistmentsPending,
            'staff_enlistments_pending' => $staffEnlistmentsPending,
            'show_staff_enlistments' => $showStaffEnlistments,
            'dashboard_pins' => $dashboardPins,
            'dashboard_announce_items' => $dashboardAnnounceItems,
            'dashboard_popup_items' => $dashboardPopupItems,
            'followed_channels' => $followedChannels,
            'mission_briefing' => $missionBriefing,
            'dashboard_tester_program' => $dashboardTesterProgram,
            'dashboard_next_steps' => $dashboardNextSteps,
            'candidate_enlistment_tracking' => $candidateEnlistmentTracking,
            'my_applications_all' => $myApplicationsAll,
            'staff_applications_all' => $staffApplicationsAll,
            'candidate_dossier_number' => $candidateDossierNumber,
            'can_manage_invitations' => $canManageInvitations,
            'pending_invitations_count' => $pendingInvitationsCount,
            'email_alerts_disabled_count' => $emailAlertsDisabledCount,
            'can_view_atak_operators' => $canViewAtakOperators,
            'atak_operators_linked_count' => $atakOperatorsLinkedCount,
            'dashboard_effectifs_rows' => $dashboardEffectifsRows,
            'can_view_personnel_directory' => $canViewPersonnelDirectory,
            'can_open_effectifs_workspace' => $canOpenEffectifsWorkspace,
            'can_see_inactive_effectifs' => $canSeeInactiveEffectifs,
            'arma_playtime_label' => $armaPlaytimeLabel,
            'arma_playtime_seconds' => $armaPlaytimeSeconds,
            'dashboard_rh_parcours' => $dashboardRhParcours,
            'dashboard_published_openings' => $dashboardPublishedOpenings,
            'dashboard_tenant_slug' => $dashboardTenantSlug,
            'can_manage_recruitment_offers' => $canManageRecruitmentOffers,
            'dashboard_elevation_catalog' => $dashboardElevationCatalog,
            'can_request_self_elevation' => $canRequestSelfElevation,
            'elevation_no_recipients' => $elevationNoRecipients,
            'elevation_cooldown_seconds' => $elevationCooldownSeconds,
            'elevation_history_mine' => $elevationHistoryMine,
            'rh_my_mobility' => $rhMyMobility,
            'rh_mobility_schema_ready' => $rhMobilitySchemaReady,
            'can_publish_dashboard_articles' => $canPublishDashboardArticles,
            'dashboard_mini_articles' => $dashboardMiniArticles,
        ]);
    }

    /**
     * Extrait texte brut pour tuiles dashboard (messages forum épinglés).
     */
    private static function plainTextExcerpt(string $raw, int $maxLen = 220): string
    {
        $text = trim(strip_tags($raw));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) > $maxLen) {
                return rtrim(mb_substr($text, 0, $maxLen - 1)) . '…';
            }

            return $text;
        }
        if (strlen($text) > $maxLen) {
            return rtrim(substr($text, 0, $maxLen - 1)) . '…';
        }

        return $text;
    }

    /**
     * Enrichit les candidatures « tableau de bord staff » avec les mêmes signaux que le bureau
     * recrutement (délai/alerte, instructeur, affectation prévue, bilan) — sans rien inventer :
     * uniquement des données déjà présentes en base ou déjà calculées côté back-office.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function enrichStaffApplicationsForDashboard(array $rows, int $tenantId): array
    {
        if ($rows === []) {
            return [];
        }

        $slaHours = 72;
        try {
            $tenantSettings = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->getSettings($tenantId);
            $slaHours = \App\Services\Recruitment\TenantRecruitmentSettings::enlistmentSlaHoursFromSettings($tenantSettings);
        } catch (\Throwable) {
            $slaHours = 72;
        }

        $reviewerIds = [];
        $openingIds = [];
        $enlistmentIds = [];
        foreach ($rows as $r) {
            $rid = (int) ($r['reviewed_by'] ?? 0);
            if ($rid > 0) {
                $reviewerIds[$rid] = $rid;
            }
            $oid = (int) ($r['recruitment_opening_id'] ?? 0);
            if ($oid > 0) {
                $openingIds[$oid] = $oid;
            }
            $eid = (int) ($r['id'] ?? 0);
            if ($eid > 0) {
                $enlistmentIds[] = $eid;
            }
        }

        $reviewerUsers = [];
        try {
            if ($reviewerIds !== []) {
                $reviewerUsers = \App\Core\Container::get(\App\Repositories\UserRepository::class)
                    ->findByIdsForTenant($tenantId, array_values($reviewerIds));
            }
        } catch (\Throwable) {
            $reviewerUsers = [];
        }

        $openingsById = [];
        $jobRoleLabels = [];
        try {
            $openingRepo = \App\Core\Container::get(\App\Repositories\RecruitmentOpeningRepository::class);
            if ($openingIds !== [] && $openingRepo->tablesExist()) {
                $jobRoleRepo = new \App\Repositories\PersonnelJobRoleRepository();
                foreach ($openingIds as $oid) {
                    $orow = $openingRepo->findByIdForTenant((int) $oid, $tenantId);
                    if (!$orow) {
                        continue;
                    }
                    $openingsById[(int) $oid] = $orow;
                    $jrid = (int) ($orow['personnel_job_role_id'] ?? 0);
                    if ($jrid > 0 && !isset($jobRoleLabels[$jrid])) {
                        $jr = $jobRoleRepo->findRoleById($jrid, $tenantId);
                        $lab = trim((string) (($jr ?? [])['name'] ?? ''));
                        if ($lab === '') {
                            $lab = trim((string) (($jr ?? [])['title'] ?? ''));
                        }
                        $jobRoleLabels[$jrid] = $lab;
                    }
                }
            }
        } catch (\Throwable) {
            $openingsById = [];
            $jobRoleLabels = [];
        }

        $staffRetroDoneMap = [];
        $retroTableReady = false;
        try {
            $engagementRepo = \App\Core\Container::get(\App\Repositories\EnlistmentRecruitmentEngagementRepository::class);
            $retroTableReady = $engagementRepo->retroTableExists();
            if ($retroTableReady && $enlistmentIds !== []) {
                $staffRetroDoneMap = $engagementRepo->mapStaffRetroDoneIds($tenantId, $enlistmentIds);
            }
        } catch (\Throwable) {
            $staffRetroDoneMap = [];
            $retroTableReady = false;
        }

        foreach ($rows as &$row) {
            $isSubmitted = ((string) ($row['status'] ?? '')) === 'submitted';
            $ageHours = self::enlistmentSubmittedAgeHours($row);
            $row['submitted_age_hours'] = $ageHours;
            $row['submitted_sla_breached'] = $isSubmitted && $ageHours !== null && $ageHours > $slaHours;
            $row['enlistment_sla_hours'] = $slaHours;

            $lastAction = trim((string) ($row['updated_at'] ?? ''));
            if ($lastAction === '') {
                $lastAction = trim((string) ($row['created_at'] ?? ''));
            }
            $row['last_action_at'] = $lastAction !== '' ? $lastAction : null;

            $ageDays = self::enlistmentAgeDaysFromRow($row);
            $row['enlistment_age_days'] = $ageDays;

            $eid = (int) ($row['id'] ?? 0);
            $doneAt = $eid > 0 && array_key_exists($eid, $staffRetroDoneMap)
                ? trim((string) $staffRetroDoneMap[$eid])
                : null;
            $row['staff_retro_done_at'] = $doneAt !== null && $doneAt !== '' ? $doneAt : null;
            $statusForRetro = (string) ($row['status'] ?? '');
            if ($doneAt !== null) {
                $row['staff_retro_status'] = 'done';
            } elseif (\App\Repositories\EnlistmentRecruitmentEngagementRepository::isRetroExcludedStatus($statusForRetro)) {
                $row['staff_retro_status'] = 'not_applicable';
            } elseif (!$retroTableReady) {
                $row['staff_retro_status'] = 'unavailable';
            } elseif ($ageDays !== null && $ageDays >= 30) {
                $row['staff_retro_status'] = 'due';
            } else {
                $row['staff_retro_status'] = 'waiting';
            }

            $reviewedById = (int) ($row['reviewed_by'] ?? 0);
            $row['instructor_user_id'] = $reviewedById > 0 ? $reviewedById : null;
            $row['instructor_label'] = $reviewedById > 0
                ? self::displayLabelForTenantUserRow($reviewerUsers[$reviewedById] ?? null, $reviewedById)
                : '';

            $oid = (int) ($row['recruitment_opening_id'] ?? 0);
            $opening = $oid > 0 ? ($openingsById[$oid] ?? null) : null;
            $unitLabel = $opening ? trim((string) ($opening['unit_name'] ?? '')) : '';
            $jrid = $opening ? (int) ($opening['personnel_job_role_id'] ?? 0) : 0;
            $roleLabel = $jrid > 0 ? trim((string) ($jobRoleLabels[$jrid] ?? '')) : '';
            if ($roleLabel === '') {
                $roleLabel = trim((string) ($row['specialty'] ?? ''));
            }
            $row['assignment_unit_label'] = $unitLabel;
            $row['assignment_role_label'] = $roleLabel;
            $row['assignment_opening_title'] = $opening ? trim((string) ($opening['title'] ?? '')) : '';
            $row['assignment_define_url'] = url('back-office/recruitments/' . $eid . '?dossier=1#coordination-dossier');
        }
        unset($row);

        return $rows;
    }

    private static function enlistmentSubmittedAgeHours(array $enlistment): ?int
    {
        $base = trim((string) ($enlistment['updated_at'] ?? ''));
        if ($base === '') {
            $base = trim((string) ($enlistment['created_at'] ?? ''));
        }
        if ($base === '') {
            return null;
        }
        $ts = strtotime($base);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        $delta = time() - $ts;
        if ($delta < 0) {
            return 0;
        }

        return (int) floor($delta / 3600);
    }

    private static function enlistmentAgeDaysFromRow(array $enlistment): ?int
    {
        $base = trim((string) ($enlistment['created_at'] ?? ''));
        if ($base === '') {
            return null;
        }
        $ts = strtotime($base);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        try {
            $start = (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $end = new \DateTimeImmutable('now');
            $startDay = $start->setTime(0, 0, 0);
            $endDay = $end->setTime(0, 0, 0);

            return max(0, (int) $startDay->diff($endDay)->days);
        } catch (\Throwable) {
            return max(0, (int) floor((time() - $ts) / 86400));
        }
    }

    private static function displayLabelForTenantUserRow(?array $urow, int $userId): string
    {
        if ($userId < 1) {
            return '';
        }
        if (!is_array($urow)) {
            return 'Membre n°' . $userId;
        }
        $lab = trim((string) ($urow['display_name'] ?? ''));
        if ($lab === '') {
            $lab = trim((string) ($urow['callsign'] ?? ''));
        }
        if ($lab === '') {
            $lab = trim((string) ($urow['email'] ?? ''));
        }

        return $lab !== '' ? $lab : ('Membre n°' . $userId);
    }

    /**
     * @param list<array<string, mixed>> $rawRows
     * @return list<array{name: string, code: string, notice: ?string, links: list<array{label: string, href: string}>}>
     */
    private static function buildDashboardTesterProgramModules(array $rawRows): array
    {
        $byId = [];
        foreach ($rawRows as $row) {
            $id = (int) ($row['module_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if (!isset($byId[$id])) {
                $byId[$id] = [
                    'name' => (string) ($row['module_name'] ?? ''),
                    'code' => strtoupper(trim((string) ($row['module_code'] ?? ''))),
                    'allows' => false,
                    'denies' => false,
                ];
            }
            $rt = (string) ($row['rule_type'] ?? '');
            if ($rt === 'allow_community') {
                $byId[$id]['allows'] = true;
            }
            if ($rt === 'deny_community') {
                $byId[$id]['denies'] = true;
            }
        }
        $out = [];
        foreach ($byId as $blob) {
            $code = $blob['code'];
            $name = trim((string) $blob['name']) !== '' ? trim((string) $blob['name']) : 'Fonctionnalité concernée';
            $notice = null;
            if ($blob['denies'] && !$blob['allows']) {
                $notice = 'Des limitations peuvent s’appliquer selon les règles définies pour le programme.';
            } elseif ($blob['denies'] && $blob['allows']) {
                $notice = 'Accès partiel : certaines actions peuvent rester restreintes.';
            }
            $links = $blob['allows']
                ? self::testerProgramPortalLinksForModuleCode($code)
                : [
                    ['label' => 'Centre opérationnel', 'href' => url('hub')],
                    ['label' => 'Espace RH et formations', 'href' => url('personnel/mon-espace-rh')],
                ];
            if ($links === []) {
                $links = [['label' => 'Centre opérationnel', 'href' => url('hub')]];
            }
            $out[] = [
                'name' => $name,
                'code' => $code,
                'notice' => $notice,
                'links' => $links,
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        return $out;
    }

    /**
     * @return list<array{label: string, href: string}>
     */
    private static function testerProgramPortalLinksForModuleCode(string $code): array
    {
        return match ($code) {
            'TRAINING' => [
                ['label' => 'Catalogue des formations', 'href' => url('formations')],
                ['label' => 'Mes parcours', 'href' => url('formations/mes-formations')],
            ],
            'RH' => [
                ['label' => 'Espace RH et formations', 'href' => url('personnel/mon-espace-rh')],
                ['label' => 'Ma fiche personnelle', 'href' => url('personnel/me')],
            ],
            'SIRH' => [
                ['label' => 'Annuaire du personnel', 'href' => url('personnel')],
                ['label' => 'Organigramme', 'href' => url('orbat')],
            ],
            default => [
                ['label' => 'Centre opérationnel', 'href' => url('hub')],
                ['label' => 'Espace RH et formations', 'href' => url('personnel/mon-espace-rh')],
            ],
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function buildTrainingShowcasePayload(array $rows): array
    {
        $monthsFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $out = [];
        foreach ($rows as $c) {
            $cardLine = '';
            $cycleDisplay = '—';
            $rawDate = $c['showcase_cycle_date'] ?? null;
            if (is_string($rawDate) && $rawDate !== '') {
                $ts = strtotime($rawDate);
                if ($ts !== false) {
                    $cardLine = (int) date('j', $ts) . ' ' . $monthsFr[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
                    $cycleDisplay = date('d.m.Y', $ts);
                }
            }
            $loc = trim((string) ($c['showcase_location'] ?? ''));
            if ($cardLine !== '' && $loc !== '') {
                $cardLine .= ' • ' . $loc;
            } elseif ($cardLine === '' && $loc !== '') {
                $cardLine = $loc;
            }
            $badge = (string) ($c['showcase_badge'] ?? 'open');
            $meta = training_showcase_badge_meta($badge);
            $desc = strip_tags((string) ($c['description'] ?? $c['short_description'] ?? ''));
            $desc = preg_replace('/\s+/u', ' ', $desc) ?? '';
            if (function_exists('mb_strlen') && mb_strlen($desc) > 600) {
                $desc = mb_substr($desc, 0, 597) . '…';
            } elseif (strlen($desc) > 600) {
                $desc = substr($desc, 0, 597) . '…';
            }
            $slug = (string) ($c['slug'] ?? '');
            $out[] = [
                'id' => (int) $c['id'],
                'title' => (string) ($c['title'] ?? ''),
                'slug' => $slug,
                'thumb' => training_media_url($c['thumbnail_path'] ?? null),
                'banner' => training_media_url(!empty($c['banner_path']) ? $c['banner_path'] : ($c['thumbnail_path'] ?? null)),
                'card_line' => $cardLine !== '' ? $cardLine : 'Date à confirmer',
                'badge_label' => $meta['label'],
                'badge_classes' => $meta['classes'],
                'card_style' => (string) ($c['showcase_card_style'] ?? 'default'),
                'cycle_display' => $cycleDisplay,
                'location_display' => $loc !== '' ? $loc : '—',
                'description' => $desc,
                'course_url' => url('formations/' . $slug),
            ];
        }

        return $out;
    }

    public function enlistment(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', ['content' => 'enlistment', 'title' => 'Enrôlement']);
    }

    public function recrutement(Request $request, array $params = []): Response
    {
        return Response::view('layout.main', [
            'title' => 'Recrutement',
            'content' => 'portal.recruitment_global',
        ]);
    }

    public function equipement(Request $request, array $params = []): Response
    {
        return Response::view('equipement');
    }

    public function tacmap(Request $request, array $params = []): Response
    {
        $maint = $this->atakMaintenanceResponseIfBlocked();
        if ($maint !== null) {
            return $maint;
        }

        return Response::view('tacmap', array_merge($this->buildOperationalMapPageData(), [
            'title' => 'TACMAP — Athena',
        ]));
    }

    public function overwatch(Request $request, array $params = []): Response
    {
        $maint = $this->atakMaintenanceResponseIfBlocked();
        if ($maint !== null) {
            return $maint;
        }

        return Response::view('overwatch.index', array_merge($this->buildOperationalMapPageData(), [
            'title' => 'COMSPEC Overwatch — Situation & commandement',
        ]));
    }

    private function atakMaintenanceResponseIfBlocked(): ?Response
    {
        $tenantId = (int) Session::get('tenant_id');
        if ($tenantId < 1) {
            return null;
        }
        $canAdmin = function_exists('can') && can('admin.access');
        if ($canAdmin) {
            return null;
        }
        try {
            $repo = \App\Core\Container::get(\App\Repositories\TenantAtakConfigRepository::class);
        } catch (\Throwable) {
            $repo = new \App\Repositories\TenantAtakConfigRepository();
        }
        if (!$repo->isMaintenanceEnabled($tenantId)) {
            return null;
        }

        return Response::view('atak-maintenance', [
            'maintenanceMessage' => $repo->getMaintenanceMessage($tenantId),
            'canAccessAdminAtakConfig' => false,
        ]);
    }

    /**
     * Données communes aux vues carte opérationnelle (Overwatch, TACMAP) : cartes ATAK, workspaces, contexte API.
     *
     * @return array<string, mixed>
     */
    private function buildOperationalMapPageData(): array
    {
        $tenantId = (int) Session::get('tenant_id');
        $atakMapRepo = \App\Core\Container::get(\App\Repositories\AtakMapRepository::class);
        $atakMapsList = $atakMapRepo->getAll();
        $defaultMap = $tenantId ? $atakMapRepo->getDefaultForTenant($tenantId) : $atakMapRepo->getBySlug('altis');
        $defaultMap = $defaultMap ?? $atakMapRepo->getBySlug('altis');
        $defaultMapId = $defaultMap ? (int) $defaultMap['id'] : 1;
        $defaultMapSlug = $defaultMap['slug'] ?? 'world';
        $defaultMapLabel = $defaultMap['label'] ?? 'Principal';

        // Si aucune carte Arma n’a de motif de tuiles, démarrer sur le fond monde (évite carte vide).
        $hasUsableArma = false;
        foreach ($atakMapsList as $m) {
            if (trim((string) ($m['tile_pattern'] ?? '')) !== '') {
                $hasUsableArma = true;
                break;
            }
        }
        if (!$hasUsableArma) {
            $defaultMapSlug = 'world';
            $defaultMapLabel = 'Vue du monde';
        } elseif ($defaultMap && trim((string) ($defaultMap['tile_pattern'] ?? '')) === '') {
            $defaultMapSlug = 'world';
            $defaultMapLabel = 'Vue du monde';
        }

        $overwatchMapsList = [
            ['slug' => 'world', 'label' => 'Vue du monde', 'type' => 'world'],
            ['slug' => 'world_relief', 'label' => 'Relief mondial', 'type' => 'world'],
        ];
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $slug = (string) ($m['slug'] ?? '');
            $overwatchMapsList[] = [
                'id' => (int) $m['id'],
                'slug' => $slug,
                'label' => $m['label'] ?? $slug,
                'type' => 'arma',
                'tilePattern' => atak_resolve_tile_pattern((string) ($m['tile_pattern'] ?? ''), $slug !== '' ? $slug : 'altis'),
                'hasCustomCrs' => ! empty($c['crs']),
            ];
        }

        $customMaps = [];
        if ($tenantId > 0) {
            try {
                $customRepo = \App\Core\Container::get(\App\Repositories\TenantCustomMapRepository::class);
                $customMaps = $customRepo->listActiveForTenant($tenantId);
            } catch (\Throwable) {
                $customMaps = [];
            }
        }

        $overwatchWorkspaces = [];
        foreach ($atakMapsList as $m) {
            $overwatchWorkspaces[] = [
                'mapId' => (int) $m['id'],
                'label' => $m['label'] ?? $m['slug'],
                'slug' => $m['slug'],
                'isDefault' => ($m['slug'] ?? '') === $defaultMapSlug,
                'type' => 'arma',
            ];
        }

        $overwatchMapsConfigs = [];
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $slug = (string) ($m['slug'] ?? '');
            $pattern = trim((string) ($m['tile_pattern'] ?? ''));
            if ($pattern === '' && $slug === '') {
                continue;
            }
            $resolved = atak_resolve_tile_pattern($pattern, $slug !== '' ? $slug : 'altis');
            $overwatchMapsConfigs[$slug !== '' ? $slug : 'altis'] = [
                'mapId' => (int) $m['id'],
                'slug' => $slug !== '' ? $slug : 'altis',
                'label' => $m['label'] ?? $slug,
                'type' => 'arma',
                'tilePattern' => $resolved,
                'center' => $c['center'] ?? [15000, 15000],
                'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
                'minZoom' => (int) ($c['minZoom'] ?? 0),
                'maxZoom' => (int) ($c['maxZoom'] ?? 6),
                'bounds' => $c['bounds'] ?? null,
                'crs' => $c['crs'] ?? null,
                'config' => $c,
            ];
        }

        $userId = (int) Session::get('user_id');
        $gate = \App\Core\Gate::getInstance();
        $canAdminMaps = $gate->allows('admin.organization') || $gate->allows('admin.access');

        foreach ($customMaps as $cm) {
            $w = (int) ($cm['image_width'] ?? 0);
            $h = (int) ($cm['image_height'] ?? 0);
            $slug = (string) ($cm['slug'] ?? '');
            $mapId = (int) ($cm['map_id'] ?? 0);
            $path = (string) ($cm['image_path'] ?? '');
            if ($slug === '' || $mapId <= 0 || $path === '' || $w < 1 || $h < 1) {
                continue;
            }
            $imageUrl = \App\Services\Maps\TenantCustomMapStorage::publicUrl($path);
            $label = (string) ($cm['label'] ?? 'Carte');
            $createdBy = (int) ($cm['created_by'] ?? 0);
            $overwatchMapsList[] = [
                'id' => (int) ($cm['id'] ?? 0),
                'mapId' => $mapId,
                'slug' => $slug,
                'label' => $label,
                'type' => 'image',
                'canManage' => $createdBy === $userId || $canAdminMaps,
            ];
            $overwatchWorkspaces[] = [
                'mapId' => $mapId,
                'label' => $label,
                'slug' => $slug,
                'isDefault' => $slug === $defaultMapSlug,
                'type' => 'image',
            ];
            $overwatchMapsConfigs[$slug] = [
                'mapId' => $mapId,
                'slug' => $slug,
                'label' => $label,
                'type' => 'image',
                'imageUrl' => $imageUrl,
                'imageWidth' => $w,
                'imageHeight' => $h,
                'center' => [$h / 2, $w / 2],
                'bounds' => [[0, 0], [$h, $w]],
                'defaultZoom' => 0,
                'minZoom' => -2,
                'maxZoom' => 4,
            ];
        }

        if (empty($overwatchWorkspaces)) {
            $overwatchWorkspaces[] = ['mapId' => $defaultMapId, 'label' => $defaultMapLabel, 'slug' => $defaultMapSlug === 'world' ? 'altis' : $defaultMapSlug, 'isDefault' => true, 'type' => 'arma'];
        }

        $baseUrl = rtrim(url(''), '/');
        $overwatchContext = [
            'tenantId' => $tenantId,
            'defaultMapId' => $defaultMapId,
            'defaultMapSlug' => $defaultMapSlug,
            'defaultMissionId' => "mission_{$tenantId}_map_{$defaultMapId}",
            'apiBase' => $baseUrl . '/api',
            'syncIntervalMs' => 8000,
            'assetBase' => $baseUrl,
            'csrfToken' => \App\Core\Csrf::token(),
        ];

        return [
            'overwatchMapsList' => $overwatchMapsList,
            'overwatchWorkspaces' => $overwatchWorkspaces,
            'overwatchMapsConfigs' => $overwatchMapsConfigs,
            'overwatchDefaultMapId' => $defaultMapId,
            'overwatchDefaultMapSlug' => $defaultMapSlug,
            'overwatchDefaultWorkspace' => [
                'mapId' => $defaultMapId,
                'label' => $defaultMapLabel,
                'slug' => $defaultMapSlug,
            ],
            'overwatchContext' => $overwatchContext,
            'overwatchCanCreateCustomMaps' => $tenantId > 0 && $userId > 0,
        ];
    }
}
