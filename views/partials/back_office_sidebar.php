<?php
declare(strict_types=1);

/**
 * Aside back-office — rail à tuiles (pattern dashboard) + panneaux drill.
 * Navigation regroupée par thèmes métier.
 */

$p = function_exists('back_office_path_suffix') ? back_office_path_suffix() : '';
$gate = \App\Core\Gate::getInstance();
$canInv = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('invitations.send');
$canDocs = $gate->allows('documents.upload') || $gate->allows('admin.access');
$canTraining = \App\Support\TrainingLmsStaffAccess::allows($gate);
$canTenantModules = $gate->allows('admin.system') || $gate->allows('admin.organization') || $gate->allows('admin.access');
$canForumModConsole = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
$canStructureRecruitmentHub = $gate->allows('organization.orbat.view')
    || $gate->allows('organization.orbat.manage')
    || $gate->allows('admin.organization')
    || $gate->allows('admin.access')
    || $gate->allows('site.support');

$tenantLabel = '';
try {
    $tid = (int) \App\Core\Session::get('tenant_id');
    if ($tid > 0) {
        $tr = (new \App\Repositories\TenantRepository())->findById($tid);
        if ($tr !== null) {
            $tenantLabel = trim((string) ($tr['name'] ?? ''));
        }
    }
} catch (\Throwable) {
}

$canIntegrationsBo = false;
try {
    $tidNav = (int) \App\Core\Session::get('tenant_id');
    if ($tidNav > 1 && ($gate->allows('admin.organization') || $gate->allows('admin.access'))) {
        $canIntegrationsBo = \App\Core\Container::get(\App\Services\Platform\FeatureGateService::class)->allows($tidNav, 'advanced_integrations');
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

$boNavHome = $p === 'back-office';
$boNavUsers = $p === 'back-office/users' || str_starts_with($p, 'back-office/users/');
$boNavInv = $p === 'back-office/invitations' || str_starts_with($p, 'back-office/invitations/');
$boNavInvCompose = $p === 'back-office/invitations';
$boNavInvSent = str_starts_with($p, 'back-office/invitations/envoyees');
$boNavRec = str_starts_with($p, 'back-office/recruitments');
$boNavRecWorkspaceDash = $p === $rwPath;
$boNavRecWorkspaceAnalytics = $p === $rwPath . '/analyses';
$boNavRecSettings = str_starts_with($p, 'back-office/recruitments/settings');
$boNavRecMessages = str_starts_with($p, 'back-office/recruitments/messages-prefaits');
$boNavRecOfferNew = str_starts_with($p, 'back-office/recruitment/offers/create');
$boNavRecOffers = (str_starts_with($p, 'back-office/recruitment/offers') && !$boNavRecOfferNew) || str_starts_with($p, 'back-office/recruitment/reference-format');
$canRecOffers = $gate->allows('organization.recruitment.openings.manage') || $gate->allows('organization.recruitment.manage');
$boNavRolesPresets = str_starts_with($p, 'back-office/roles/presets');
$boNavAccessMgmt = str_starts_with($p, 'back-office/access-management');
$boNavRoles = ($p === 'back-office/roles' || str_starts_with($p, 'back-office/roles/')) && !$boNavRolesPresets;
$boNavRolesFx = $p === 'back-office/roles-functions' || str_starts_with($p, 'back-office/roles-functions/');
$boNavPjr = str_starts_with($p, 'back-office/personnel-job-roles');
$boNavPersonnelDeployment = str_starts_with($p, 'deploiement');
$boNavRoleplayFollowup = str_starts_with($p, 'back-office/roleplay-followup');
$boNavEff = str_starts_with($p, 'back-office/organisation-effectifs');
$ewPath = function_exists('effectifs_workspace_path') ? effectifs_workspace_path() : 'back-office/ressources/effectifs';
$boNavEffWorkspace = $p === $ewPath || str_starts_with($p, $ewPath . '/');
$boNavStructureHub = str_starts_with($p, 'back-office/organisation/structure');
$boNavGroups = str_starts_with($p, 'back-office/groups');
$boNavCommunications = str_starts_with($p, 'back-office/communications');
$boNavCommsHistory = str_starts_with($p, 'back-office/communications/history');
$boNavCommsTemplates = str_starts_with($p, 'back-office/communications/templates');
$boNavCommsGroups = str_starts_with($p, 'back-office/communications/groups');
$canCommsSection = $gate->allows('comms.email.send.orbat')
    || $gate->allows('comms.email.send.mission')
    || $gate->allows('comms.email.send.activity')
    || $gate->allows('comms.email.send.custom')
    || $gate->allows('comms.email.broadcast')
    || $gate->allows('comms.email_templates.manage')
    || $gate->allows('comms.notifications.history.view');
$boNavTeams = str_starts_with($p, 'back-office/teams');
$boNavCats = str_starts_with($p, 'back-office/categories');
$boNavGrades = str_starts_with($p, 'back-office/referentiels/grades');
$boNavSeniority = str_starts_with($p, 'back-office/organisation/anciennete');
$boNavOrgSettings = str_starts_with($p, 'back-office/organisation/parametres') || $p === 'back-office/community';
$boNavInitialSetup = $p === 'back-office/configuration-initiale' || str_starts_with($p, 'back-office/configuration-initiale/');
$boNavCommPres = str_starts_with($p, 'back-office/community/presentation');
$boNavMedia = $p === 'back-office/media' || str_starts_with($p, 'back-office/media/');
$canMediaBo = \App\Support\CommunityMediaStaffAccess::allows($gate);
$boNavInteg = str_starts_with($p, 'back-office/integrations');
$boNavAlerts = str_starts_with($p, 'back-office/alerts');
$boNavConfig = $p === 'back-office/configuration' || str_starts_with($p, 'back-office/configuration/');
$boNavAnalytics = $p === 'back-office/analytics';
$boNavAnalyticsConversion = str_starts_with($p, 'back-office/analytics/conversion');
$boNavPins = str_starts_with($p, 'back-office/dashboard-pins');
$boNavCoop = str_starts_with($p, 'back-office/cooperation/');
$boNavCoopCatalog = str_starts_with($p, 'back-office/cooperation/catalog');
$boNavCoopAnnouncements = str_starts_with($p, 'back-office/cooperation/announcements');
$boNavForumMissionPriority = str_starts_with($p, 'back-office/forum/priorite-mission/');
$boNavOnb = str_starts_with($p, 'back-office/onboarding-recovery');
$boNavOnbMembers = str_starts_with($p, 'back-office/onboarding-members');
$boNavAudit = str_starts_with($p, 'back-office/audit');
$boNavMod = str_starts_with($p, 'back-office/moderation');
$boNavSecurityIndicators = str_starts_with($p, 'back-office/security-indicators');
$canMemberModeration = $gate->allows('admin.members.moderate');
$canTenantSecurityIndicators = $gate->allows('admin.organization')
    || $gate->allows('admin.access')
    || $gate->allows('admin.members.moderate')
    || $gate->allows('organization.recruitment.manage');
$boNavEventInsights = str_starts_with($p, 'back-office/events/insights');
$boNavEvents = str_starts_with($p, 'back-office/events') && !$boNavEventInsights;
$boNavCourrierTrace = str_starts_with($p, 'back-office/courrier/traceabilite');
$boNavPortalOpsBoard = $p === 'tableau-operationnel' || str_starts_with($p, 'tableau-operationnel/');
$boNavOpsBoard = str_starts_with($p, 'back-office/tableau-operationnel');
$boNavOpsAdmin = str_starts_with($p, 'back-office/centre-operations') || str_starts_with($p, 'back-office/operations-admin');
$boNavPositions = str_starts_with($p, 'back-office/positions');
$boNavConformite = str_starts_with($p, 'back-office/conformite');
$boNavDoctrine = str_starts_with($p, 'back-office/doctrine');
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
$boNavPlatformShell = function_exists('is_platform_site_admin_shell_request') && is_platform_site_admin_shell_request();
$boNavForumMod = str_starts_with($p, 'back-office/forum-moderation');
$boNavContentMod = str_starts_with($p, 'admin/content-moderation');
$boNavDocs = str_starts_with($p, 'documents/gestion') || $p === 'documents/gestion';
$boNavModpacks = str_starts_with($p, 'admin/modpacks');
$boNavForumConfig = str_starts_with($p, 'admin/forum-config');
$boNavAtak = str_starts_with($p, 'admin/atak-config')
    || str_starts_with($p, 'back-office/atak/fire-teams')
    || str_starts_with($p, 'back-office/atak/operateurs')
    || str_starts_with($p, 'back-office/atak/briefing-slides');
$boNavAtakOperators = str_starts_with($p, 'back-office/atak/operateurs');
$canMurOperationnel = $gate->allows('operational.board.view')
    || $gate->allows('operational.board.edit')
    || $gate->allows('admin.organization')
    || $gate->allows('admin.access')
    || $gate->allows('site.support');
$boNavLmsSubPage = $boNavHrCharter || $boNavLmsFeedback || $boNavStudioActive
    || $boNavLmsEnrollments || $boNavLmsReports || $boNavLmsCertificates || $boNavLmsAuditTrail || $boNavLmsCompetences;
$canOrgStructure = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
$canAccessManagementBo = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('admin.access.manage');
$canDoctrineBo = $gate->allows('admin.system') || $gate->allows('admin.organization') || $gate->allows('admin.access');
$canCoopCatalog = function_exists('can') && (can('cooperation.catalog.manage') || can('cooperation.announcements.manage'));

$h = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$n = 0;
$num = static function () use (&$n): string {
    $n++;

    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
};

$icon = static function (string $key): string {
    $icons = [
        'overview' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9.5" cy="8" r="3.2"/><path stroke-linecap="round" d="M3.5 20v-1.3c0-2.7 2.7-4.8 6-4.8s6 2.1 6 4.8V20"/><path stroke-linecap="round" d="M16.5 7.5a2.4 2.4 0 1 1 0 4.8M20.5 20v-1c0-1.8-1.2-3.2-3-3.7"/></svg>',
        'recruitment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9.5" cy="8" r="3.2"/><path stroke-linecap="round" d="M3.5 20v-1.3c0-2.7 2.7-4.8 6-4.8s6 2.1 6 4.8V20"/><path stroke-linecap="round" d="M18.5 6.5v5M16 9h5"/></svg>',
        'access' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5 19 6.5v5.4c0 4.6-3 7.7-7 8.6-4-.9-7-4-7-8.6V6.5L12 3.5Z"/><path stroke-linecap="round" d="M9.5 12.2 11.2 14l3.5-4"/></svg>',
        'structure' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.2"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.2"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.2"/></svg>',
        'comms' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5.5" width="17" height="13" rx="1.6"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 7 6.7 5a2 2 0 0 0 2.1 0l6.7-5"/></svg>',
        'community' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7Z"/><path stroke-linecap="round" d="M4 8.5 12 13l8-4.5M12 13v7"/></svg>',
        'ops' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 14.25 2.25 12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"/></svg>',
        'events' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="5" width="17" height="15.5" rx="1.5"/><path stroke-linecap="round" d="M3.5 9.5h17M8 3v3.2M16 3v3.2"/></svg>',
        'training' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3.75 9 5-9 5-9-5 9-5Zm0 10.5 6.16-3.422a12 12 0 0 1-.84 1.54L12 18.75l-5.32-3.04a12 12 0 0 1-.84-1.54L12 14.25Z"/></svg>',
        'documents' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6.5A1.5 1.5 0 0 1 5.5 5h4l1.6 2h8.4A1.5 1.5 0 0 1 21 8.5v9A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5v-9"/></svg>',
        'tools' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.5 7-12a7 7 0 1 0-14 0c0 5.5 7 12 7 12Z"/><circle cx="12" cy="9" r="2.4"/></svg>',
        'moderation' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5 19 6.5v5.4c0 4.6-3 7.7-7 8.6-4-.9-7-4-7-8.6V6.5L12 3.5Z"/><path stroke-linecap="round" d="M12 8v4.5M12 15.2h.01"/></svg>',
        'admin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>',
    ];

    return $icons[$key] ?? '';
};

/**
 * @param list<array{label:string,href:string,hint?:string,active?:bool,badge?:int|null,badgeTone?:string}|null> $items
 * @return list<array{label:string,href:string,hint?:string,active?:bool,badge?:int|null,badgeTone?:string}>
 */
$links = static function (array $items): array {
    $out = [];
    foreach ($items as $item) {
        if (is_array($item) && isset($item['label'], $item['href']) && $item['href'] !== '') {
            $out[] = $item;
        }
    }

    return $out;
};

/**
 * @param list<array{label:string,href:string,hint?:string,active?:bool,badge?:int|null,badgeTone?:string}> $tileLinks
 */
$tile = static function (
    string $id,
    string $label,
    string $hint,
    string $variant,
    ?string $badge,
    array $tileLinks,
    string $iconKey = '',
    bool $active = false,
    string $keywords = ''
): array {
    return [
        'id' => $id,
        'label' => $label,
        'hint' => $hint,
        'variant' => $variant,
        'badge' => $badge,
        'active' => $active,
        'links' => $tileLinks,
        'icon' => $iconKey,
        'keywords' => $keywords,
    ];
};

$boRecN = (int) ($boBadges['recruitments_submitted'] ?? 0);
$boRecBadge = !empty($boBadges['show_staff_recruitment']) && $boRecN > 0 ? $boRecN : null;
$boModN = (int) ($boBadges['forum_moderation_total'] ?? 0);
$boModBadge = $boModN > 0 ? $boModN : null;
$boPersN = (int) ($boBadges['personal_inbox'] ?? 0);
$boPersBadge = $boPersN > 0 ? $boPersN : null;

$coreTiles = [];
$opsTiles = [];
$resourceTiles = [];
$adminTiles = [];

$coreTiles[] = $tile('overview', 'Vue d’ensemble', 'Accueil de l’administration', 'default', null, $links([
    ['label' => 'Tableau de bord', 'href' => url('back-office'), 'hint' => 'Synthèse et raccourcis', 'active' => $boNavHome],
]), 'overview', $boNavHome, 'pilotage centre dashboard synthèse indicateurs kpi home accueil');

$coreTiles[] = $tile('members', 'Membres', 'Comptes et invitations', 'default', null, $links([
    ['label' => 'Bureau effectifs', 'href' => url($ewPath), 'hint' => 'Tableur RH nominatif', 'active' => $boNavEffWorkspace],
    ['label' => 'Utilisateurs', 'href' => url('back-office/users'), 'hint' => 'Comptes de la communauté', 'active' => $boNavUsers],
    $canInv
        ? ['label' => 'Nouvelle invitation', 'href' => url('back-office/invitations'), 'hint' => 'Envoyer un accès', 'active' => $boNavInvCompose]
        : null,
    $canInv
        ? ['label' => 'Invitations envoyées', 'href' => url('back-office/invitations/envoyees'), 'hint' => 'Suivi tableur', 'active' => $boNavInvSent]
        : null,
]), 'users', $boNavUsers || $boNavInv || $boNavEffWorkspace, 'utilisateurs comptes membres invitation code accès join effectifs rh bureau');

$coreTiles[] = $tile(
    'recruitment',
    'Recrutement',
    'Dossiers et offres',
    'bo',
    $boRecBadge !== null ? (string) $boRecBadge : null,
    $links([
        ['label' => 'Bureau recrutement', 'href' => url($rwPath), 'hint' => 'Pilotage des candidatures', 'active' => $boNavRecWorkspaceDash],
        [
            'label' => 'File des dossiers',
            'href' => url('back-office/recruitments'),
            'hint' => 'À instruire',
            'active' => $boNavRec && !$boNavRecSettings && !$boNavRecMessages,
            'badge' => $boRecBadge,
        ],
        ['label' => 'Analyses candidatures', 'href' => url($rwPath . '/analyses'), 'hint' => 'Indicateurs', 'active' => $boNavRecWorkspaceAnalytics],
        ['label' => 'Délais de traitement', 'href' => url('back-office/recruitments/settings'), 'hint' => 'Objectifs d’instruction', 'active' => $boNavRecSettings],
        ['label' => 'Messages préfaits', 'href' => url('back-office/recruitments/messages-prefaits'), 'hint' => 'Réponses types', 'active' => $boNavRecMessages],
        $canRecOffers
            ? ['label' => 'Offres publiées', 'href' => url('back-office/recruitment/offers'), 'hint' => 'Avis d’ouverture', 'active' => $boNavRecOffers]
            : null,
        $canRecOffers
            ? ['label' => 'Nouvelle offre', 'href' => url('back-office/recruitment/offers/create'), 'hint' => 'Créer une ouverture', 'active' => $boNavRecOfferNew]
            : null,
    ]),
    'recruitment',
    $boNavRecWorkspaceDash || $boNavRecWorkspaceAnalytics || ($boNavRec && !$boNavRecSettings && !$boNavRecMessages) || $boNavRecSettings || $boNavRecMessages || $boNavRecOffers || $boNavRecOfferNew,
    'candidature dossier recrutement offre postuler enrôlement rh bureau'
);

$coreTiles[] = $tile('access', 'Droits & emplois', 'Rôles, accès et missions', 'default', null, $links([
    ['label' => 'Rôles communautaires', 'href' => url('back-office/roles'), 'hint' => 'Profils de droits', 'active' => $boNavRoles],
    $canAccessManagementBo
        ? ['label' => 'Gestion des accès', 'href' => url('back-office/access-management'), 'hint' => 'Règles et tests d’accès', 'active' => $boNavAccessMgmt]
        : null,
    ['label' => 'Profils & kits de rôles', 'href' => url('back-office/roles/presets'), 'hint' => 'Modèles prêts à l’emploi', 'active' => $boNavRolesPresets],
    ['label' => 'Doctrine des fonctions', 'href' => url('back-office/roles-functions'), 'hint' => 'Cellule S1', 'active' => $boNavRolesFx],
    ['label' => 'Emplois & missions', 'href' => url('back-office/personnel-job-roles'), 'hint' => 'Référentiel métier', 'active' => $boNavPjr && !$boNavPjrAssignments],
    $canOrgStructure
        ? ['label' => 'Affectations emplois', 'href' => url('back-office/personnel-job-roles/assignments'), 'hint' => 'Qui tient quel emploi', 'active' => $boNavPjrAssignments]
        : null,
    ['label' => 'Déploiement personnel', 'href' => url('deploiement'), 'hint' => 'Affectations terrain', 'active' => $boNavPersonnelDeployment],
    ['label' => 'Suivi roleplay', 'href' => url('back-office/roleplay-followup'), 'hint' => 'Suivi narratif', 'active' => $boNavRoleplayFollowup],
]), 'access', $boNavRoles || $boNavAccessMgmt || $boNavRolesPresets || $boNavRolesFx || ($boNavPjr && !$boNavPjrAssignments) || $boNavPjrAssignments || $boNavPersonnelDeployment || $boNavRoleplayFollowup, 'rôles permissions droits s1 emplois missions affectation grade doctrine');

$coreTiles[] = $tile('organisation', 'Organisation', 'Structure et référentiels', 'default', null, $links([
    ['label' => 'Bureau effectifs', 'href' => url($ewPath), 'hint' => 'Tableur RH nominatif', 'active' => $boNavEffWorkspace],
    ['label' => 'Structure & grades', 'href' => url('back-office/organisation-effectifs'), 'hint' => 'Organigramme, non nominatif', 'active' => $boNavEff],
    $canStructureRecruitmentHub
        ? ['label' => 'Structure & recrutement', 'href' => url('back-office/organisation/structure'), 'hint' => 'Liens recrutement / postes', 'active' => $boNavStructureHub]
        : null,
    ['label' => 'Groupes', 'href' => url('back-office/groups'), 'hint' => 'Regroupements', 'active' => $boNavGroups],
    ['label' => 'Équipes', 'href' => url('back-office/teams'), 'hint' => 'Équipes opérationnelles', 'active' => $boNavTeams],
    ['label' => 'Équipes de feu', 'href' => url('back-office/atak/fire-teams'), 'hint' => 'Mission ATAK & organigramme', 'active' => str_starts_with($p, 'back-office/atak/fire-teams')],
    ['label' => 'Effectifs en liaison', 'href' => url('back-office/atak/operateurs'), 'hint' => 'Tableur opérateurs connectés', 'active' => $boNavAtakOperators],
    ['label' => 'Catégories', 'href' => url('back-office/categories'), 'hint' => 'Rubriques du forum', 'active' => $boNavCats],
    ['label' => 'Référentiel des grades', 'href' => url('back-office/referentiels/grades'), 'hint' => 'Grades et insignes', 'active' => $boNavGrades],
    $canOrgStructure
        ? ['label' => 'Postes & fonctions', 'href' => url('back-office/positions'), 'hint' => 'État-major', 'active' => $boNavPositions]
        : null,
    ($canTenantModules || $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support'))
        ? ['label' => 'Ancienneté', 'href' => url('back-office/organisation/anciennete'), 'hint' => 'Fiches et RH', 'active' => $boNavSeniority]
        : null,
]), 'structure', $boNavEff || $boNavEffWorkspace || $boNavStructureHub || $boNavGroups || $boNavTeams || $boNavAtakOperators || $boNavCats || $boNavGrades || $boNavPositions || $boNavSeniority, 'orbat effectifs structure équipes groupes grades postes ancienneté organigramme bureau rh liaison atak');

if ($canCommsSection) {
    $coreTiles[] = $tile('comms', 'Communications', 'Messages et diffusion', 'default', null, $links([
        ['label' => 'Nouveau message', 'href' => url('back-office/communications'), 'hint' => 'Composer un envoi', 'active' => $boNavCommunications && !$boNavCommsHistory && !$boNavCommsTemplates && !$boNavCommsGroups],
        ['label' => 'Historique des envois', 'href' => url('back-office/communications/history'), 'hint' => 'Journal des messages', 'active' => $boNavCommsHistory],
        ['label' => 'Modèles d’e-mail', 'href' => url('back-office/communications/templates'), 'hint' => 'Gabarit réutilisables', 'active' => $boNavCommsTemplates],
        ['label' => 'Groupes de diffusion', 'href' => url('back-office/communications/groups'), 'hint' => 'Listes de destinataires', 'active' => $boNavCommsGroups],
    ]), 'comms', $boNavCommunications || $boNavCommsHistory || $boNavCommsTemplates || $boNavCommsGroups, 'message email mail diffusion newsletter communication modèle template');
}

$coreTiles[] = $tile('community', 'Communauté', 'Identité et portail', 'default', null, $links([
    ['label' => 'Configuration initiale', 'href' => url('back-office/configuration-initiale'), 'hint' => 'Assistant de démarrage', 'active' => $boNavInitialSetup],
    ['label' => 'Paramètres de la communauté', 'href' => url('back-office/community'), 'hint' => 'Identité et options', 'active' => $boNavOrgSettings],
    ['label' => 'Page d’accueil publique', 'href' => url('back-office/community/presentation'), 'hint' => 'Vitrine publique', 'active' => $boNavCommPres],
    $canMediaBo
        ? ['label' => 'Médias de la communauté', 'href' => url('back-office/media'), 'hint' => 'Images et vidéos', 'active' => $boNavMedia]
        : null,
    ['label' => 'Annonces & alertes', 'href' => url('back-office/alerts'), 'hint' => 'Messages portail', 'active' => $boNavAlerts],
    ['label' => 'Paramètres avancés', 'href' => url('back-office/configuration'), 'hint' => 'Réglages fins', 'active' => $boNavConfig],
    $canIntegrationsBo
        ? ['label' => 'Intégrations externes', 'href' => url('back-office/integrations'), 'hint' => 'Services connectés', 'active' => $boNavInteg]
        : null,
    ['label' => 'Indicateurs d’usage', 'href' => url('back-office/analytics'), 'hint' => 'Fréquentation', 'active' => $boNavAnalytics],
    ['label' => 'Conversion communautés', 'href' => url('back-office/analytics/conversion'), 'hint' => 'Passage à l’adhésion', 'active' => $boNavAnalyticsConversion],
    ['label' => 'Raccourcis du portail', 'href' => url('back-office/dashboard-pins'), 'hint' => 'Épingles tableau de bord', 'active' => $boNavPins],
    ['label' => 'Onboarding membres', 'href' => url('back-office/onboarding-members'), 'hint' => 'Parcours d’accueil', 'active' => $boNavOnbMembers],
    ['label' => 'Aide après inscription', 'href' => url('back-office/onboarding-recovery'), 'hint' => 'Relances et assistance', 'active' => $boNavOnb],
]), 'community', $boNavInitialSetup || $boNavOrgSettings || $boNavCommPres || $boNavMedia || $boNavAlerts || $boNavConfig || $boNavInteg || $boNavAnalytics || $boNavAnalyticsConversion || $boNavPins || $boNavOnbMembers || $boNavOnb, 'communauté paramètres branding logo alerte annonce bannière setup configuration onboarding analytics épingles médias photos vidéos bibliothèque');

$opsTiles[] = $tile('pilotage', 'Pilotage', 'Opérations et conformité', 'default', null, $links([
    $canMurOperationnel
        ? ['label' => 'Mur opérationnel', 'href' => url('tableau-operationnel'), 'hint' => 'Vue membres', 'active' => $boNavPortalOpsBoard]
        : null,
    ['label' => 'Centre d’opérations', 'href' => url('back-office/centre-operations'), 'hint' => 'File actionnable', 'active' => $boNavOpsAdmin],
    ['label' => 'Pilotage du mur', 'href' => url('back-office/tableau-operationnel'), 'hint' => 'Administration du mur', 'active' => $boNavOpsBoard],
    ['label' => 'Traçabilité courrier', 'href' => url('back-office/courrier/traceabilite'), 'hint' => 'Suivi des envois', 'active' => $boNavCourrierTrace],
    $canDoctrineBo
        ? ['label' => 'Doctrine & SOP', 'href' => url('back-office/doctrine'), 'hint' => 'Référentiels internes', 'active' => $boNavDoctrine]
        : null,
    $canOrgStructure
        ? ['label' => 'Export conformité', 'href' => url('back-office/conformite/export-dossier'), 'hint' => 'Dossier exportable', 'active' => $boNavConformite]
        : null,
    ['label' => 'Journal d’activité', 'href' => url('back-office/audit'), 'hint' => 'Historique des actions', 'active' => $boNavAudit],
]), 'ops', $boNavPortalOpsBoard || $boNavOpsAdmin || $boNavOpsBoard || $boNavCourrierTrace || $boNavDoctrine || $boNavConformite || $boNavAudit, 'opération mur audit conformité doctrine sop courrier journal logs');

$opsTiles[] = $tile('events', 'Présences', 'Manœuvres et pointage', 'default', null, $links([
    ['label' => 'RSVP & pointage', 'href' => url('back-office/events'), 'hint' => 'Confirmations et présence', 'active' => $boNavEvents],
    ['label' => 'Insights présence', 'href' => url('back-office/events/insights'), 'hint' => 'Analyse des présences', 'active' => $boNavEventInsights],
]), 'events', $boNavEvents || $boNavEventInsights, 'événement manœuvre rsvp présence calendrier pointage');

$moderationLinks = $links([
    $canMemberModeration
        ? ['label' => 'Restrictions membres', 'href' => url('back-office/moderation'), 'hint' => 'Sanctions et limitations', 'active' => $boNavMod]
        : null,
    $canTenantSecurityIndicators
        ? ['label' => 'Blocages & sécurité', 'href' => url('back-office/security-indicators'), 'hint' => 'Indicateurs de sécurité', 'active' => $boNavSecurityIndicators]
        : null,
    $canForumModConsole
        ? [
            'label' => 'Console modération forum',
            'href' => url('back-office/forum-moderation'),
            'hint' => 'Signalements et file',
            'active' => $boNavForumMod,
            'badge' => $boModBadge,
            'badgeTone' => 'rose',
        ]
        : null,
    $canForumModConsole
        ? ['label' => 'Fichiers et pièces jointes', 'href' => url('admin/content-moderation'), 'hint' => 'Contrôle des médias', 'active' => $boNavContentMod]
        : null,
]);
if ($moderationLinks !== []) {
    $opsTiles[] = $tile(
        'moderation',
        'Modération',
        'Sécurité et forum',
        'admin',
        $boModBadge !== null ? (string) $boModBadge : null,
        $moderationLinks,
        'moderation',
        $boNavMod || $boNavSecurityIndicators || $boNavForumMod || $boNavContentMod,
        'modération sanction ban mute forum signalement sécurité blocage'
    );
}

if ($canTraining) {
    $resourceTiles[] = $tile('training', 'Formations', 'Parcours et suivi pédagogique', 'bo', null, $links([
        ['label' => 'Tableau de bord formations', 'href' => url($lmsResPath), 'hint' => 'Pilotage LMS', 'active' => $boNavLmsRes && !$boNavLmsSubPage],
        ['label' => 'Inscriptions & validations', 'href' => url($lmsResPath . '/enrollments'), 'hint' => 'Demandes et validations', 'active' => $boNavLmsEnrollments],
        ['label' => 'Rapports & suivis', 'href' => url($lmsResPath . '/reports'), 'hint' => 'Indicateurs pédagogiques', 'active' => $boNavLmsReports],
        ['label' => 'Certificats & attestations', 'href' => url($lmsResPath . '/certificates'), 'hint' => 'Documents délivrés', 'active' => $boNavLmsCertificates],
        ['label' => 'Journal pédagogique', 'href' => url($lmsResPath . '/audit'), 'hint' => 'Historique des actions', 'active' => $boNavLmsAuditTrail],
        ['label' => 'Compétences', 'href' => url($lmsResPath . '/competences/bureau-personnel'), 'hint' => 'Bureau personnel', 'active' => $boNavLmsCompetences],
        ['label' => 'Charte RH', 'href' => url($lmsResPath . '/charte-rh'), 'hint' => 'Cadre RH formations', 'active' => $boNavHrCharter],
        ['label' => 'Feedback post-leçon', 'href' => url($lmsResPath . '/feedback'), 'hint' => 'Retours apprenants', 'active' => $boNavLmsFeedback],
        ['label' => 'Studio des parcours', 'href' => url(training_studio_path()), 'hint' => 'Conception des modules', 'active' => $boNavStudioActive],
    ]), 'training', $boNavLmsRes || $boNavLmsSubPage, 'formation lms cours certificat compétence parcours studio leçon');
}

if ($canDocs) {
    $resourceTiles[] = $tile('documents', 'Documents', 'Bibliothèque documentaire', 'default', null, $links([
        ['label' => 'Bibliothèque documentaire', 'href' => url('documents/gestion'), 'hint' => 'Publication et classement', 'active' => $boNavDocs],
    ]), 'documents', $boNavDocs, 'document fichier bibliothèque pdf ordre publication');
}

if ($canTenantModules) {
    $resourceTiles[] = $tile('tools', 'Outils avancés', 'Forum, coopérations, carte', 'bo', null, $links([
        ['label' => 'Modpacks', 'href' => url('admin/modpacks'), 'hint' => 'Packs de mods', 'active' => $boNavModpacks],
        ['label' => 'Briefing & forum', 'href' => url('admin/forum-config'), 'hint' => 'Configuration forum', 'active' => $boNavForumConfig],
        ['label' => 'Publication priorité mission', 'href' => url('back-office/forum/priorite-mission/nouveau'), 'hint' => 'Annonce prioritaire', 'active' => $boNavForumMissionPriority],
        ['label' => 'Coopérations inter-unités', 'href' => url('back-office/cooperation/missions'), 'hint' => 'Missions partagées', 'active' => $boNavCoop && !$boNavCoopCatalog && !$boNavCoopAnnouncements],
        $canCoopCatalog
            ? ['label' => 'Types de coopération', 'href' => url('back-office/cooperation/catalog'), 'hint' => 'Catalogue', 'active' => $boNavCoopCatalog]
            : null,
        $canCoopCatalog
            ? ['label' => 'Annonces coopération', 'href' => url('back-office/cooperation/announcements'), 'hint' => 'Publications', 'active' => $boNavCoopAnnouncements]
            : null,
        ['label' => 'Cartographie & ATAK', 'href' => url('admin/atak-config'), 'hint' => 'Carte tactique', 'active' => str_starts_with($p, 'admin/atak-config')],
        ['label' => 'Effectifs en liaison', 'href' => url('back-office/atak/operateurs'), 'hint' => 'Tableur opérateurs connectés', 'active' => $boNavAtakOperators],
        ['label' => 'Équipes de feu', 'href' => url('back-office/atak/fire-teams'), 'hint' => 'Mission et organigramme', 'active' => str_starts_with($p, 'back-office/atak/fire-teams')],
        ['label' => 'Diapositives briefing', 'href' => url('back-office/atak/briefing-slides'), 'hint' => 'Briefing en jeu', 'active' => str_starts_with($p, 'back-office/atak/briefing-slides')],
    ]), 'tools', $boNavModpacks || $boNavForumConfig || $boNavForumMissionPriority || $boNavCoop || $boNavAtak, 'modpack forum coopération atak carte tacmap mission inter-unité fire team briefing opérateurs liaison');
}

if ($gate->allows('admin.system') || $gate->allows('site.support')) {
    $adminTiles[] = $tile('platform', 'Plateforme', 'Administration site', 'admin', null, $links([
        [
            'label' => $gate->allows('admin.system') ? 'Administration site' : 'Pilotage site (assistance)',
            'href' => url('admin'),
            'hint' => 'Espace plateforme',
            'active' => $boNavPlatformShell,
        ],
    ]), 'admin', $boNavPlatformShell, 'plateforme admin site système newsletter alertes globales');
}

$renderLinks = static function (array $item) use ($h): void {
    ?>
    <ul class="dash-rail__links" role="list">
        <?php foreach ($item['links'] as $link): ?>
            <?php
            $linkActive = !empty($link['active']);
            $linkBadge = isset($link['badge']) && (int) $link['badge'] > 0 ? (int) $link['badge'] : null;
            $badgeTone = (string) ($link['badgeTone'] ?? '');
            $badgeCls = 'dash-rail__link-badge' . ($badgeTone === 'rose' ? ' dash-rail__link-badge--rose' : ($badgeTone === 'sky' ? ' dash-rail__link-badge--sky' : ''));
            ?>
            <li>
                <a class="dash-rail__link<?= $linkActive ? ' is-active' : '' ?>" href="<?= $h((string) $link['href']) ?>">
                    <span class="dash-rail__link-main">
                        <span class="dash-rail__link-label"><?= $h((string) $link['label']) ?></span>
                        <?php if (!empty($link['hint'])): ?>
                            <span class="dash-rail__link-hint"><?= $h((string) $link['hint']) ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if ($linkBadge !== null): ?>
                        <span class="<?= $h($badgeCls) ?>" aria-hidden="true"><?= $h($linkBadge > 99 ? '99+' : (string) $linkBadge) ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};

$renderTile = static function (array $item) use ($num, $h, $renderLinks, $icon): void {
    $id = (string) $item['id'];
    $label = (string) $item['label'];
    $active = !empty($item['active']);
    $variant = (string) ($item['variant'] ?? 'default');
    $badge = $item['badge'] ?? null;
    $classes = ['dash-rail__tile'];
    if ($active) {
        $classes[] = 'is-active';
    }
    if ($variant === 'bo') {
        $classes[] = 'dash-rail__tile--bo';
    } elseif ($variant === 'admin') {
        $classes[] = 'dash-rail__tile--admin';
    }
    $nestedId = 'bo-rail-nested-' . $id;
    $idxLabel = $num();
    $iconMarkup = $icon((string) ($item['icon'] ?? ''));
    $searchBits = [
        $label,
        (string) ($item['hint'] ?? ''),
        (string) ($item['keywords'] ?? ''),
        $id,
    ];
    foreach (($item['links'] ?? []) as $link) {
        if (!is_array($link)) {
            continue;
        }
        $searchBits[] = (string) ($link['label'] ?? '');
        $searchBits[] = (string) ($link['hint'] ?? '');
    }
    $searchBlob = mb_strtolower(trim(preg_replace('/\s+/u', ' ', implode(' ', $searchBits)) ?? ''), 'UTF-8');
    ?>
    <div class="dash-rail__item" data-dash-rail-item="<?= $h($id) ?>" data-bo-search="<?= $h($searchBlob) ?>">
        <button
            type="button"
            class="<?= $h(implode(' ', $classes)) ?>"
            data-dash-rail-open="<?= $h($id) ?>"
            aria-expanded="false"
            aria-controls="<?= $h($nestedId) ?>"
        >
            <?php if ($iconMarkup !== ''): ?>
                <span class="dash-rail__tile-icon" aria-hidden="true"><?= $iconMarkup ?></span>
            <?php else: ?>
                <b class="dash-rail__idx"><?= $h($idxLabel) ?></b>
            <?php endif; ?>
            <span class="dash-rail__copy">
                <strong class="dash-rail__label"><?= $h($label) ?></strong>
                <em class="dash-rail__hint"><?= $h((string) $item['hint']) ?></em>
            </span>
            <?php if ($active): ?>
                <i class="dash-rail__meta dash-rail__meta--actif">Actif</i>
            <?php elseif (is_string($badge) && $badge !== ''): ?>
                <i class="dash-rail__meta dash-rail__meta--badge" aria-label="<?= $h($badge . ' élément(s)') ?>"><?= $h($badge) ?></i>
            <?php else: ?>
                <i class="dash-rail__meta dash-rail__meta--empty" aria-hidden="true">—</i>
            <?php endif; ?>
        </button>
        <div
            class="dash-rail__nested"
            id="<?= $h($nestedId) ?>"
            data-dash-rail-nested="<?= $h($id) ?>"
            data-dash-rail-title="<?= $h($label) ?>"
            data-dash-rail-lead="<?= $h((string) $item['hint']) ?>"
            hidden
        >
            <div class="dash-rail__nested-body">
                <?php $renderLinks($item); ?>
            </div>
        </div>
    </div>
    <?php
};
?>
<div
    class="bo-rail dash-rail flex h-full min-h-0 flex-col"
    id="bo-rail"
    aria-label="Navigation back-office"
    data-dash-rail
    data-dash-rail-autoload
    data-dash-rail-persist-drill
    role="navigation"
>
    <div class="dash-rail__inner">
        <div class="dash-rail__compact" data-bo-rail-compact>
            <div class="bo-rail__compact-stack" role="list" aria-label="Rubriques (vue compacte)">
                <?php
                $compactTiles = array_merge($coreTiles, $opsTiles, $resourceTiles, $adminTiles);
                foreach ($compactTiles as $ct):
                    if (!is_array($ct)) {
                        continue;
                    }
                    $ctId = (string) ($ct['id'] ?? '');
                    $ctLabel = (string) ($ct['label'] ?? '');
                    $ctHint = (string) ($ct['hint'] ?? '');
                    $ctIcon = $icon((string) ($ct['icon'] ?? ''));
                    if ($ctId === '' || $ctLabel === '') {
                        continue;
                    }
                    if ($ctIcon === '') {
                        $ctIcon = '<span class="bo-rail__compact-fallback" aria-hidden="true">' . $h(mb_strtoupper(mb_substr($ctLabel, 0, 1, 'UTF-8'), 'UTF-8')) . '</span>';
                    }
                    $ctActive = !empty($ct['active']);
                    $ctVariant = (string) ($ct['variant'] ?? 'default');
                    $ctCls = 'bo-rail__compact-ico';
                    if ($ctActive) {
                        $ctCls .= ' is-active';
                    }
                    if ($ctVariant === 'bo') {
                        $ctCls .= ' bo-rail__compact-ico--bo';
                    } elseif ($ctVariant === 'admin') {
                        $ctCls .= ' bo-rail__compact-ico--admin';
                    }
                    $ctTitle = $ctHint !== '' ? ($ctLabel . ' — ' . $ctHint) : $ctLabel;
                    $ctShort = $ctLabel;
                    if (function_exists('mb_strlen') && mb_strlen($ctShort, 'UTF-8') > 8) {
                        $ctShort = mb_substr($ctShort, 0, 7, 'UTF-8') . '…';
                    } elseif (strlen($ctShort) > 8) {
                        $ctShort = substr($ctShort, 0, 7) . '…';
                    }
                    ?>
                    <button
                        type="button"
                        class="<?= $h($ctCls) ?>"
                        data-dash-rail-open="<?= $h($ctId) ?>"
                        title="<?= $h($ctTitle) ?>"
                        aria-label="<?= $h($ctTitle) ?>"
                        aria-expanded="false"
                        role="listitem"
                    >
                        <span class="bo-rail__compact-glyph" aria-hidden="true"><?= $ctIcon ?></span>
                        <span class="bo-rail__compact-label"><?= $h($ctShort) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="dash-rail__panel">
            <div class="dash-rail__view dash-rail__view--root" data-dash-rail-root>
                <div class="dash-rail__brand">
                    <p class="dash-rail__eyebrow">Athena / État-major</p>
                    <h2 class="dash-rail__title">Back-office</h2>
                    <p class="dash-rail__unit"><?= $h($tenantLabel !== '' ? $tenantLabel : 'Administration de votre espace') ?></p>
                    <label class="bo-rail__search" for="bo-rail-search">
                        <span class="sr-only">Rechercher dans le menu</span>
                        <input
                            id="bo-rail-search"
                            type="search"
                            class="bo-rail__search-input"
                            placeholder="Rechercher une rubrique…"
                            autocomplete="off"
                            spellcheck="false"
                        >
                    </label>
                    <p class="bo-rail__search-empty" id="bo-rail-search-empty" hidden>Aucun résultat pour cette recherche.</p>
                </div>

                <nav class="dash-rail__nav" aria-label="Rubriques back-office" id="bo-rail-nav">
                    <p class="dash-rail__section" data-bo-section="gestion">Gestion</p>
                    <?php foreach ($coreTiles as $item): ?>
                        <?php $renderTile($item); ?>
                    <?php endforeach; ?>

                    <?php if ($opsTiles !== []): ?>
                        <p class="dash-rail__section dash-rail__section--ops" data-bo-section="pilotage">Pilotage</p>
                        <?php foreach ($opsTiles as $item): ?>
                            <?php $renderTile($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($resourceTiles !== []): ?>
                        <p class="dash-rail__section dash-rail__section--bo" data-bo-section="ressources">Ressources</p>
                        <?php foreach ($resourceTiles as $item): ?>
                            <?php $renderTile($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if ($adminTiles !== []): ?>
                        <p class="dash-rail__section dash-rail__section--admin" data-bo-section="plateforme">Plateforme</p>
                        <?php foreach ($adminTiles as $item): ?>
                            <?php $renderTile($item); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </nav>

                <div class="dash-rail__foot">
                    <?php
                    $boPersAria = $boPersBadge !== null
                        ? ' aria-label="Mon activité — ' . ($boPersN > 99 ? '99+' : (string) $boPersN) . ' notification(s)"'
                        : '';
                    ?>
                    <a href="<?= $h(url('activite')) ?>" class="bo-rail__foot-link"<?= $boPersAria ?>>
                        <span class="bo-rail__foot-link-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                            <span>Mon activité</span>
                        </span>
                        <?php if ($boPersBadge !== null): ?>
                            <span class="bo-rail__foot-badge" aria-hidden="true"><?= $h($boPersN > 99 ? '99+' : (string) $boPersN) ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="<?= $h(url('dashboard')) ?>" class="bo-rail__foot-link bo-rail__foot-link--portal">
                        <span class="bo-rail__foot-link-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                            <span>Retour au portail</span>
                        </span>
                    </a>
                </div>
            </div>

            <div
                class="dash-rail__view dash-rail__view--drill"
                data-dash-rail-drill
                hidden
                role="region"
                aria-labelledby="bo-rail-drill-heading"
            >
                <div class="dash-rail__drill-head">
                    <button
                        type="button"
                        class="dash-rail__back"
                        data-dash-rail-back
                        aria-label="Retour à la navigation"
                    >
                        <span aria-hidden="true">←</span>
                        <span>Retour</span>
                    </button>
                    <div class="dash-rail__drill-titles">
                        <p class="dash-rail__drill-kicker">Rubrique</p>
                        <h3 class="dash-rail__drill-title" id="bo-rail-drill-heading">Rubrique</h3>
                        <p class="dash-rail__drill-lead" data-dash-rail-drill-lead></p>
                    </div>
                </div>
                <div class="dash-rail__drill-body" data-dash-rail-drill-body></div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var root = document.getElementById('bo-rail');
  var input = document.getElementById('bo-rail-search');
  var empty = document.getElementById('bo-rail-search-empty');
  var nav = document.getElementById('bo-rail-nav');
  if (!root || !input || !nav) return;

  function normalize(s) {
    return String(s || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function applyFilter() {
    var q = normalize(input.value);
    var items = nav.querySelectorAll('.dash-rail__item[data-bo-search]');
    var visible = 0;
    items.forEach(function (el) {
      var hay = normalize(el.getAttribute('data-bo-search') || '');
      var ok = q === '' || hay.indexOf(q) !== -1 || q.split(' ').every(function (tok) {
        return tok === '' || hay.indexOf(tok) !== -1;
      });
      el.hidden = !ok;
      if (ok) visible += 1;
    });

    var sections = nav.querySelectorAll('.dash-rail__section');
    sections.forEach(function (sec) {
      var next = sec.nextElementSibling;
      var any = false;
      while (next && !next.classList.contains('dash-rail__section')) {
        if (next.classList.contains('dash-rail__item') && !next.hidden) any = true;
        next = next.nextElementSibling;
      }
      sec.hidden = q !== '' && !any;
    });

    if (empty) empty.hidden = !(q !== '' && visible === 0);
  }

  input.addEventListener('input', applyFilter);
  input.addEventListener('search', applyFilter);
})();
</script>
