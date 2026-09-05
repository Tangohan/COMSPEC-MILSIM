<?php
declare(strict_types=1);

/**
 * Aside back-office ATHENA — navigation alignée sur Back-Office.dc.html.
 */

$p = function_exists('back_office_path_suffix') ? back_office_path_suffix() : '';
$gate = \App\Core\Gate::getInstance();
$canTraining = \App\Support\TrainingLmsStaffAccess::allows($gate);
$canMediaBo = \App\Support\CommunityMediaStaffAccess::allows($gate);
$canMemberModeration = $gate->allows('admin.members.moderate');

$tenantLabel = '';
$tenantType = \App\Services\Community\TenantTypeConfig::TYPE_FULL;
try {
    $tid = (int) \App\Core\Session::get('tenant_id');
    if ($tid > 0) {
        $tr = (new \App\Repositories\TenantRepository())->findById($tid);
        if ($tr !== null) {
            $tenantLabel = trim((string) ($tr['name'] ?? ''));
            $tenantType = \App\Services\Community\TenantTypeConfig::normalizeType(
                (string) ($tr['tenant_type'] ?? 'full')
            );
        }
    }
} catch (\Throwable) {
}

$boHrefAllowed = static function (string $href) use ($tenantType): bool {
    $path = function_exists('back_office_nav_href_to_path')
        ? back_office_nav_href_to_path($href)
        : trim((string) $href, '/');
    if ($path !== '' && !\App\Services\Community\TenantTypeConfig::uriAllowed($tenantType, $path)) {
        $tenantOk = false;
        foreach (['back-office/', 'admin/', 'formation/', 'formations/', 'documents/', 'forum/', 'atak/', 'personnel/', 'enlistment/', 'jnet/'] as $needle) {
            $pos = strpos($path, $needle);
            if ($pos !== false) {
                $tenantOk = \App\Services\Community\TenantTypeConfig::uriAllowed($tenantType, substr($path, $pos));
                break;
            }
        }
        if (!$tenantOk) {
            return false;
        }
    }

    // Masquer les entrées sans droit (Gate), pas seulement filtrer par type de communauté.
    if (function_exists('back_office_nav_href_permission_allowed')) {
        return back_office_nav_href_permission_allowed($href);
    }

    return true;
};

$canIntegrationsBo = false;
try {
    $tidNav = (int) \App\Core\Session::get('tenant_id');
    if ($tidNav > 1 && ($gate->allows('admin.organization') || $gate->allows('admin.access'))) {
        $canIntegrationsBo = true;
    }
} catch (\Throwable) {
}

$boBadges = [
    'recruitments_submitted' => 0,
    'forum_moderation_total' => 0,
    'personal_inbox' => 0,
    'show_staff_recruitment' => false,
];
try {
    $boTid = (int) \App\Core\Session::get('tenant_id');
    $boUid = (int) \App\Core\Session::get('user_id');
    if ($boTid > 0 && $boUid > 0) {
        $uBo = \App\Core\Container::get(\App\Repositories\UserRepository::class)->findById($boUid, $boTid);
        $boEmail = trim((string) ($uBo['email'] ?? (string) (\App\Core\Session::get('email') ?? '')));
        $boBadges = \App\Core\Container::get(\App\Services\Portal\BackOfficeSidebarBadgeService::class)->build($boTid, $boUid, $boEmail, $gate);
    }
} catch (\Throwable) {
}

$rwPath = function_exists('recruitment_workspace_path') ? recruitment_workspace_path() : 'back-office/ressources/recrutement';
$legacyLmsBo = 'back-office/ressources/training';
$studioPath = function_exists('training_studio_path') ? training_studio_path() : 'formation/studio';
$legacyStudioPath = $legacyLmsBo . '/studio';
$lmsResPath = function_exists('training_lms_admin_path') ? training_lms_admin_path() : 'formation';
$ewPath = function_exists('effectifs_workspace_path') ? effectifs_workspace_path() : 'back-office/ressources/effectifs';

$boNavHome = $p === 'back-office';
$boNavUsers = $p === 'back-office/users' || str_starts_with($p, 'back-office/users/');
$boNavRec = str_starts_with($p, 'back-office/recruitments');
$boNavRecSettings = str_starts_with($p, 'back-office/recruitments/settings');
$boNavRecMessages = str_starts_with($p, 'back-office/recruitments/messages-prefaits');
$boNavRolesPresets = str_starts_with($p, 'back-office/roles/presets');
$boNavRolesPermissions = str_starts_with($p, 'back-office/roles-permissions');
$boNavRoles = ($p === 'back-office/roles' || str_starts_with($p, 'back-office/roles/')) && !$boNavRolesPresets && !$boNavRolesPermissions;
$boNavRolesFx = $p === 'back-office/roles-functions' || str_starts_with($p, 'back-office/roles-functions/');
$boNavPjr = str_starts_with($p, 'back-office/personnel-job-roles');
$boNavRoleplayDeadlines = $p === 'back-office/roleplay-followup/echeances'
    || str_starts_with($p, 'back-office/roleplay-followup/echeances/');
$boNavRoleplayFollowup = (str_starts_with($p, 'back-office/roleplay-followup') && !$boNavRoleplayDeadlines);
$boNavRoleplayImmersion = str_starts_with($p, 'back-office/roleplay/immersion');
$boNavRoleplaySection = $boNavRoleplayFollowup || $boNavRoleplayDeadlines || $boNavRoleplayImmersion || str_starts_with($p, 'back-office/roleplay/');
$boNavEff = str_starts_with($p, 'back-office/organisation-effectifs');
$boNavCatalog = $p === 'back-office/organisation/catalogue' || str_starts_with($p, 'back-office/organisation/catalogue/');
$boNavEffWorkspace = $p === $ewPath || str_starts_with($p, $ewPath . '/');
$boNavOrgSettings = str_starts_with($p, 'back-office/organisation/parametres') || $p === 'back-office/community';
$boNavInitialSetup = $p === 'back-office/configuration-initiale' || str_starts_with($p, 'back-office/configuration-initiale/');
$boNavCommPres = str_starts_with($p, 'back-office/community/presentation');
$boNavCommInscription = str_starts_with($p, 'back-office/community/inscription');
$boNavMedia = $p === 'back-office/media' || str_starts_with($p, 'back-office/media/');
$boNavInteg = str_starts_with($p, 'back-office/integrations');
$boNavAlerts = str_starts_with($p, 'back-office/alerts');
$boNavArticles = str_starts_with($p, 'back-office/articles');
$boNavConfig = $p === 'back-office/configuration' || str_starts_with($p, 'back-office/configuration/');
$boNavAnalytics = $p === 'back-office/analytics';
$boNavOnbMembers = str_starts_with($p, 'back-office/onboarding-members') || str_starts_with($p, 'back-office/integration-membres');
$boNavAudit = str_starts_with($p, 'back-office/audit');
$boNavMod = str_starts_with($p, 'back-office/moderation');
$boNavEventInsights = str_starts_with($p, 'back-office/events/insights');
$boNavAar = str_starts_with($p, 'back-office/atak/comptes-rendus');
$boNavPlanning = str_starts_with($p, 'back-office/planification');
$boNavMissionsPortal = $p === 'back-office/missions' || str_starts_with($p, 'back-office/missions/');
$boNavCooperation = $p === 'back-office/cooperation' || str_starts_with($p, 'back-office/cooperation/');
$boNavCooperationMissions = $p === 'back-office/cooperation/missions' || str_starts_with($p, 'back-office/cooperation/missions/');
$boNavCooperationCreate = $p === 'back-office/cooperation/missions/create';
$boNavCooperationCatalog = $p === 'back-office/cooperation/catalog' || str_starts_with($p, 'back-office/cooperation/catalog/');
$boNavCooperationAnnouncements = $p === 'back-office/cooperation/announcements' || str_starts_with($p, 'back-office/cooperation/announcements/');
$boNavEvents = str_starts_with($p, 'back-office/events') && !$boNavEventInsights;
$boNavJnet = $p === 'jnet' || str_starts_with($p, 'jnet/');
$boNavJnetHome = $p === 'jnet';
$boNavJnetUnit = $p === 'jnet/unite' || str_starts_with($p, 'jnet/unite/');
$boNavJnetPersonnel = $p === 'jnet/personnel' || str_starts_with($p, 'jnet/personnel/');
$boNavJnetOps = $p === 'jnet/operations' || str_starts_with($p, 'jnet/operations/');
$boNavJnetIntel = $p === 'jnet/renseignement' || str_starts_with($p, 'jnet/renseignement/');
$boNavJnetTargets = $p === 'jnet/cibles' || str_starts_with($p, 'jnet/cibles/');
$boNavJnetExploit = $p === 'jnet/exploitation' || str_starts_with($p, 'jnet/exploitation/');
$boNavJnetLibrary = $p === 'jnet/bibliotheque' || str_starts_with($p, 'jnet/bibliotheque/');
$boNavJnetMail = $p === 'jnet/courrier' || str_starts_with($p, 'jnet/courrier/');
$boNavJnetSystem = $p === 'jnet/systeme' || str_starts_with($p, 'jnet/systeme/');
$boNavPortalOpsBoard = $p === 'tableau-operationnel' || str_starts_with($p, 'tableau-operationnel/');
$boNavOpsBoard = str_starts_with($p, 'back-office/tableau-operationnel');
$boNavStudioActive = str_starts_with($p, $studioPath . '/') || $p === $studioPath
    || str_starts_with($p, $legacyStudioPath . '/') || $p === $legacyStudioPath;
$boNavLmsRes = $p === $lmsResPath || str_starts_with($p, $lmsResPath . '/') || $p === $legacyLmsBo || str_starts_with($p, $legacyLmsBo . '/');
$boNavHrCharter = str_starts_with($p, $lmsResPath . '/charte-rh') || str_starts_with($p, $legacyLmsBo . '/charte-rh');
$boNavLmsFeedback = str_starts_with($p, $lmsResPath . '/feedback') || str_starts_with($p, $legacyLmsBo . '/feedback');
$boNavLmsEnrollments = str_starts_with($p, $lmsResPath . '/enrollments') || str_starts_with($p, $legacyLmsBo . '/enrollments');
$boNavLmsReports = str_starts_with($p, $lmsResPath . '/reports') || str_starts_with($p, $legacyLmsBo . '/reports');
$boNavLmsCertificates = str_starts_with($p, $lmsResPath . '/certificates') || str_starts_with($p, $legacyLmsBo . '/certificates');
$boNavLmsAuditTrail = str_starts_with($p, $lmsResPath . '/audit') || str_starts_with($p, $legacyLmsBo . '/audit');
$boNavLmsCompetences = str_starts_with($p, $lmsResPath . '/competences') || str_starts_with($p, $legacyLmsBo . '/competences');
$boNavPjrAssignments = str_starts_with($p, 'back-office/personnel-job-roles/assignments');
$boNavPjrKits = str_starts_with($p, 'back-office/personnel-job-roles/kits');
$boNavAtakOperators = $p === 'back-office/atak/operateurs' || str_starts_with($p, 'back-office/atak/operateurs/');
$canMurOperationnel = $gate->allows('operational.board.view')
    || $gate->allows('operational.board.edit')
    || $gate->allows('admin.organization')
    || $gate->allows('admin.access')
    || $gate->allows('site.support');
$boNavLmsSubPage = $boNavHrCharter || $boNavLmsFeedback || $boNavStudioActive
    || $boNavLmsEnrollments || $boNavLmsReports || $boNavLmsCertificates || $boNavLmsAuditTrail || $boNavLmsCompetences;

$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$boRecN = (int) ($boBadges['recruitments_submitted'] ?? 0);

$boUserName = '';
$boUserGradeLine = '';
$boUserInitials = 'A';
try {
    $boUidSide = (int) \App\Core\Session::get('user_id');
    $boTidSide = (int) \App\Core\Session::get('tenant_id');
    $boUserName = trim((string) (\App\Core\Session::get('display_name') ?? \App\Core\Session::get('callsign') ?? ''));
    if ($boUserName === '') {
        $boUserName = 'Administrateur';
    }
    $parts = preg_split('/\s+/u', $boUserName) ?: [];
    if (count($parts) >= 2) {
        $boUserInitials = mb_strtoupper(mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[1], 0, 1, 'UTF-8'), 'UTF-8');
    } elseif ($boUserName !== '') {
        $boUserInitials = mb_strtoupper(mb_substr($boUserName, 0, 2, 'UTF-8'), 'UTF-8');
    }
    if ($boTidSide > 0 && $boUidSide > 0) {
        $uSide = \App\Core\Container::get(\App\Repositories\UserRepository::class)->findById($boUidSide, $boTidSide);
        if (is_array($uSide) && !empty($uSide['grade_id'])) {
            $grSide = \App\Core\Container::get(\App\Repositories\GradeRepository::class)
                ->findById((int) $uSide['grade_id'], $boTidSide);
            if (is_array($grSide)) {
                $ppSide = null;
                try {
                    $ppSide = \App\Core\Container::get(\App\Repositories\PersonnelProfileRepository::class)
                        ->getByUserId($boUidSide);
                } catch (\Throwable) {
                    $ppSide = null;
                }
                $gradeDisplay = \App\Core\Container::get(\App\Services\GradeDisplayService::class);
                $gradeLong = trim($gradeDisplay->headerTitle($grSide, is_array($ppSide) ? $ppSide : null));
                $gradeOtan = trim((string) ($gradeDisplay->headerShortCode($grSide, is_array($ppSide) ? $ppSide : null) ?? ''));
                if ($gradeLong !== '' && $gradeOtan !== '') {
                    $boUserGradeLine = $gradeLong . ' · ' . $gradeOtan;
                } elseif ($gradeLong !== '') {
                    $boUserGradeLine = $gradeLong;
                } elseif ($gradeOtan !== '') {
                    $boUserGradeLine = $gradeOtan;
                }
            }
        }
    }
} catch (\Throwable) {
}

$boTakHost = '';
$boTakOnline = false;
try {
    $boTakTid = (int) \App\Core\Session::get('tenant_id');
    if ($boTakTid > 0) {
        $atakCfg = \App\Core\Container::get(\App\Repositories\TenantAtakConfigRepository::class)->getByTenantId($boTakTid);
        if (is_array($atakCfg)) {
            $host = trim((string) ($atakCfg['cot_host'] ?? $atakCfg['arma_server_host'] ?? ''));
            $port = trim((string) ($atakCfg['cot_port'] ?? $atakCfg['arma_server_port'] ?? ''));
            if ($host !== '') {
                $boTakHost = $host . ($port !== '' ? ':' . $port : '');
            }
            $atakRepo = \App\Core\Container::get(\App\Repositories\TenantAtakConfigRepository::class);
            $boTakOnline = !$atakRepo->isMaintenanceEnabled($boTakTid);
        }
    }
} catch (\Throwable) {
}

$tenantShort = $tenantLabel !== '' ? mb_strtoupper($tenantLabel, 'UTF-8') : 'ADMINISTRATION';

require __DIR__ . '/ath_sidebar_nav.php';

?>
<nav class="ath-sidebar" id="ath-sidebar" aria-label="Navigation back-office">
    <div class="ath-sidebar__head">
        <a href="<?= $h(url('dashboard')) ?>" class="ath-sidebar__logo" aria-label="Retour au tableau de bord" title="Retour au tableau de bord">A</a>
        <div class="ath-sidebar__brand">
            <a href="<?= $h(url('dashboard')) ?>" class="ath-sidebar__brand-name" title="Retour au tableau de bord">ATHENA<span>.</span></a>
            <div class="ath-sidebar__brand-sub">ADMINISTRATION · <?= $h($tenantShort) ?></div>
        </div>
        <button type="button" class="ath-sidebar__toggle" data-ath-sidebar-toggle title="Plier le menu" aria-label="Plier le menu">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path></svg>
        </button>
    </div>

    <div class="ath-sidebar__nav" id="ath-sidebar-nav">
        <?php foreach ($athNavGroups as $navGroup): ?>
            <div class="ath-sidebar__group is-open" data-ath-nav-group="<?= $h((string) $navGroup['key']) ?>">
                <button type="button" class="ath-sidebar__group-head" data-ath-group-toggle aria-expanded="true">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#4b524e" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
                    <span class="ath-sidebar__group-label"><?= $h((string) $navGroup['label']) ?></span>
                </button>
                <div class="ath-sidebar__group-body">
                    <?php foreach ($navGroup['items'] as $navItem): ?>
                        <?php
                        $searchBits = [(string) ($navItem['label'] ?? '')];
                        foreach (($navItem['children'] ?? []) as $child) {
                            if (is_array($child)) {
                                $searchBits[] = (string) ($child['label'] ?? '');
                            }
                        }
                        $searchBlob = mb_strtolower(trim(preg_replace('/\s+/u', ' ', implode(' ', $searchBits)) ?? ''), 'UTF-8');
                        ?>
                        <div data-ath-nav-item data-ath-search="<?= $h($searchBlob) ?>">
                            <?php $renderAthNavItem($navItem); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($boTakHost !== ''): ?>
    <div class="ath-sidebar__tak">
        <div class="ath-sweep" style="position:absolute;inset:0;pointer-events:none;" aria-hidden="true"></div>
        <div class="ath-sidebar__tak-label">SERVEUR TAK</div>
        <div class="ath-sidebar__tak-status">
            <div class="ath-sidebar__tak-dot<?= $boTakOnline ? '' : ' ath-sidebar__tak-dot--offline' ?><?= $boTakOnline ? ' ath-pulse' : '' ?>" aria-hidden="true"></div>
            <span class="ath-sidebar__tak-text"><?= $boTakOnline ? 'Opérationnel' : 'Maintenance' ?></span>
        </div>
        <div class="ath-sidebar__tak-host"><?= $h($boTakHost) ?></div>
    </div>
    <?php endif; ?>

    <a href="<?= $h(url('dashboard')) ?>" class="ath-sidebar__portal" title="Retour au tableau de bord">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"></path></svg>
        <span class="ath-sidebar__portal-label">Retour au tableau de bord</span>
    </a>

    <div class="ath-sidebar__foot">
        <div class="ath-sidebar__avatar" aria-hidden="true"><?= $h($boUserInitials) ?></div>
        <div class="ath-sidebar__user-meta">
            <div class="ath-sidebar__user-name"><?= $h(mb_strtoupper($boUserName, 'UTF-8')) ?></div>
            <?php if ($boUserGradeLine !== ''): ?>
            <div class="ath-sidebar__user-role"><?= $h(mb_strtoupper($boUserGradeLine, 'UTF-8')) ?></div>
            <?php endif; ?>
        </div>
    </div>
</nav>
