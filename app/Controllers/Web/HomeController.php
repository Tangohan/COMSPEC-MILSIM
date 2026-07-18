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
            'title' => 'Athena Compsec — Portail MILSIM',
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
                $atakModDownloadUrl = url('atak/mod/download');
            }
        }

        $dashboardTenantLabel = null;
        $showcaseTrainingFeature = false;
        $showcaseItems = [];
        $myEnlistmentsPending = [];
        $staffEnlistmentsPending = [];
        $showStaffEnlistments = false;
        $dashboardPins = [];
        $missionBriefing = null;
        $dashboardTesterProgram = null;
        $candidateEnlistmentTracking = [];
        if ($tenantId) {
            $tid = (int) $tenantId;
            $tenantRow = \App\Core\Container::get(\App\Repositories\TenantRepository::class)->findById($tid);
            if ($tenantRow) {
                $dashboardTenantLabel = community_display_name($tenantRow);
            }
            $showcaseTrainingFeature = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tid, 'training');
            if ($showcaseTrainingFeature) {
                $rows = \App\Core\Container::get(\App\Repositories\TrainingCourseRepository::class)->listPublishedForDashboard($tid, 20);
                $showcaseItems = self::buildTrainingShowcasePayload($rows);
            }

            if ($currentUser) {
                $uid = (int) $currentUser['id'];
                $uemail = (string) ($currentUser['email'] ?? '');
                $enlistRepo = \App\Core\Container::get(\App\Repositories\EnlistmentRepository::class);
                $myEnlistmentsPending = $enlistRepo->listPendingSubmittedForSubmitter($tid, $uid, $uemail);
                if ($tid === 1) {
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

                $gate = \App\Core\Gate::getInstance();
                $roleSlug = \App\Core\Container::get(\App\Repositories\UserRepository::class)->getRoleSlugForUser($uid) ?? '';
                $staffSlugs = ['recruiter', 'community_owner', 'hr', 'tenant_admin'];
                $showStaffEnlistments = $gate->allows('admin.organization') || $gate->allows('admin.access')
                    || in_array($roleSlug, $staffSlugs, true);
                if ($showStaffEnlistments) {
                    $staffEnlistmentsPending = $enlistRepo->listPendingSubmittedForTenant($tid, 25);
                }

                try {
                    $dashboardPins = \App\Core\Container::get(\App\Services\Dashboard\TenantDashboardPinService::class)
                        ->listResolvedPinsForViewer($tid, $uid);
                } catch (\Throwable) {
                    $dashboardPins = [];
                }

                try {
                    $missionBriefing = \App\Core\Container::get(\App\Services\Dashboard\MemberMissionBriefingService::class)
                        ->buildForViewer($tid, $uid, $modpack, $dashboardPins, $showcaseTrainingFeature);
                } catch (\Throwable) {
                    $missionBriefing = null;
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

        return Response::view('dashboard', [
            'title' => 'Dashboard — Athena',
            'modpack' => $modpack,
            'currentUser' => $currentUser,
            'personnelExtras' => $personnelExtras,
            'grade' => $grade,
            'atakModDownloadUrl' => $atakModDownloadUrl,
            'communityMemberships' => $communityMemberships,
            'founder_trial_ends_at' => $founderTrialEndsAt,
            'show_founder_trial_banner' => $showFounderTrialBanner,
            'dashboard_tenant_label' => $dashboardTenantLabel,
            'showcase_training_feature' => $showcaseTrainingFeature,
            'showcase_items' => $showcaseItems,
            'my_enlistments_pending' => $myEnlistmentsPending,
            'staff_enlistments_pending' => $staffEnlistmentsPending,
            'show_staff_enlistments' => $showStaffEnlistments,
            'dashboard_pins' => $dashboardPins,
            'mission_briefing' => $missionBriefing,
            'dashboard_tester_program' => $dashboardTesterProgram,
            'dashboard_next_steps' => $dashboardNextSteps,
            'candidate_enlistment_tracking' => $candidateEnlistmentTracking,
        ]);
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
        return Response::view('tacmap', array_merge($this->buildOperationalMapPageData(), [
            'title' => 'TACMAP — Athena',
        ]));
    }

    public function overwatch(Request $request, array $params = []): Response
    {
        return Response::view('overwatch.index', array_merge($this->buildOperationalMapPageData(), [
            'title' => 'COMSPEC Overwatch — C2',
        ]));
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
        $defaultMapSlug = $defaultMap['slug'] ?? 'altis';
        $defaultMapLabel = $defaultMap['label'] ?? 'Principal';

        $overwatchMapsList = [['slug' => 'world', 'label' => 'Monde (OpenStreetMap)', 'type' => 'world']];
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $overwatchMapsList[] = [
                'id' => (int) $m['id'],
                'slug' => $m['slug'],
                'label' => $m['label'] ?? $m['slug'],
                'type' => 'arma',
                'tilePattern' => $m['tile_pattern'] ?? '',
                'hasCustomCrs' => ! empty($c['crs']),
            ];
        }

        $overwatchWorkspaces = [];
        foreach ($atakMapsList as $m) {
            $overwatchWorkspaces[] = [
                'mapId' => (int) $m['id'],
                'label' => $m['label'] ?? $m['slug'],
                'slug' => $m['slug'],
                'isDefault' => ($m['slug'] ?? '') === $defaultMapSlug,
            ];
        }
        if (empty($overwatchWorkspaces)) {
            $overwatchWorkspaces[] = ['mapId' => $defaultMapId, 'label' => $defaultMapLabel, 'slug' => $defaultMapSlug, 'isDefault' => true];
        }

        $baseUrl = rtrim(url(''), '/');
        $overwatchMapsConfigs = [];
        foreach ($atakMapsList as $m) {
            $c = $m['config'] ?? [];
            $overwatchMapsConfigs[$m['slug']] = [
                'mapId' => (int) $m['id'],
                'slug' => $m['slug'],
                'label' => $m['label'] ?? $m['slug'],
                'tilePattern' => $baseUrl . ($m['tile_pattern'] ?? ''),
                'center' => $c['center'] ?? [15000, 15000],
                'defaultZoom' => (int) ($c['defaultZoom'] ?? 3),
                'minZoom' => (int) ($c['minZoom'] ?? 0),
                'maxZoom' => (int) ($c['maxZoom'] ?? 6),
                'bounds' => $c['bounds'] ?? null,
                'crs' => $c['crs'] ?? null,
                'config' => $c,
            ];
        }

        $overwatchContext = [
            'tenantId' => $tenantId,
            'defaultMapId' => $defaultMapId,
            'defaultMapSlug' => $defaultMapSlug,
            'defaultMissionId' => "mission_{$tenantId}_map_{$defaultMapId}",
            'apiBase' => $baseUrl . '/api',
            'syncIntervalMs' => 8000,
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
        ];
    }
}
