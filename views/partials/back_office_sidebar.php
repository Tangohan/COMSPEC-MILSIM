<?php
declare(strict_types=1);
?>
<style>
    .bo-side-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
    .bo-side-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 999px; }
    .bo-side-scroll::-webkit-scrollbar-track { background: transparent; }
</style>
<?php
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

$boLink = static function (string $path, string $label, bool $active, ?int $badge = null, ?string $badgeTone = null): void {
    $cls = $active
        ? 'flex w-full min-w-0 items-center justify-between gap-2 rounded-lg bg-slate-800 px-3 py-2.5 text-sm font-semibold text-white shadow-sm ring-1 ring-white/10'
        : 'flex w-full min-w-0 items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800/80 hover:text-white';
    $tone = $badgeTone ?? 'rose';
    $bg = match ($tone) {
        'emerald' => 'bg-emerald-600',
        'sky' => 'bg-sky-600',
        default => 'bg-rose-500',
    };
    $pill = '';
    $ariaExtra = '';
    if ($badge !== null && $badge > 0) {
        $t = $badge > 99 ? '99+' : (string) $badge;
        $pill = '<span class="inline-flex min-w-[1.35rem] shrink-0 justify-center rounded-full ' . $bg . ' px-1.5 py-0.5 text-[10px] font-black leading-none text-white" aria-hidden="true">' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>';
        $ariaN = $badge > 99 ? '99+' : (string) $badge;
        $ariaExtra = ' aria-label="' . htmlspecialchars($label . ' — ' . $ariaN . ' notification(s)', ENT_QUOTES, 'UTF-8') . '"';
    }
    echo '<a href="' . htmlspecialchars(url($path), ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '"' . $ariaExtra
        . '><span class="min-w-0 flex-1 truncate">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>' . $pill . '</a>';
};

$boSection = static function (string $title): void {
    echo '<p class="mt-6 mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 first:mt-0">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
};

/* Préfixe boNav* : ne pas utiliser $groups, $users, etc. — ce sont des noms de données vues écrasés avant le require du contenu. */
$boNavHome = $p === 'back-office';
$boNavUsers = $p === 'back-office/users' || str_starts_with($p, 'back-office/users/');
$boNavInv = str_starts_with($p, 'back-office/invitations');
$boNavRec = str_starts_with($p, 'back-office/recruitments');
$rwPath = function_exists('recruitment_workspace_path') ? recruitment_workspace_path() : 'back-office/ressources/recrutement';
$boNavRecWorkspaceDash = $p === $rwPath;
$boNavRecWorkspaceAnalytics = $p === $rwPath . '/analyses';
$boNavRecSettings = str_starts_with($p, 'back-office/recruitments/settings');
$boNavRecMessages = str_starts_with($p, 'back-office/recruitments/messages-prefaits');
$boNavRecOfferNew = str_starts_with($p, 'back-office/recruitment/offers/create');
$boNavRecOffers = (str_starts_with($p, 'back-office/recruitment/offers') && !$boNavRecOfferNew) || str_starts_with($p, 'back-office/recruitment/reference-format');
$canRecOffers = $gate->allows('organization.recruitment.openings.manage') || $gate->allows('organization.recruitment.manage');
$boNavRolesPresets = str_starts_with($p, 'back-office/roles/presets');
$boNavRoles = ($p === 'back-office/roles' || str_starts_with($p, 'back-office/roles/')) && !$boNavRolesPresets;
$boNavRolesFx = $p === 'back-office/roles-functions' || str_starts_with($p, 'back-office/roles-functions/');
$boNavPjr = str_starts_with($p, 'back-office/personnel-job-roles');
$boNavPersonnelDeployment = str_starts_with($p, 'deploiement');
$boNavRoleplayFollowup = str_starts_with($p, 'back-office/roleplay-followup');
$boNavEff = str_starts_with($p, 'back-office/organisation-effectifs');
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
$boNavCommCode = $p === 'back-office/community';
$boNavCommPres = str_starts_with($p, 'back-office/community/presentation');
$boNavInteg = str_starts_with($p, 'back-office/integrations');
$boNavAlerts = str_starts_with($p, 'back-office/alerts');
$boNavConfig = str_starts_with($p, 'back-office/configuration');
$boNavAnalytics = $p === 'back-office/analytics';
$boNavAnalyticsConversion = str_starts_with($p, 'back-office/analytics/conversion');
$boNavPins = str_starts_with($p, 'back-office/dashboard-pins');
$boNavCoop = str_starts_with($p, 'back-office/cooperation/');
$boNavForumMissionPriority = str_starts_with($p, 'back-office/forum/priorite-mission/');
$boNavOnb = str_starts_with($p, 'back-office/onboarding-recovery');
$boNavOnbMembers = str_starts_with($p, 'back-office/onboarding-members');
$boNavAudit = str_starts_with($p, 'back-office/audit');
$boNavMod = str_starts_with($p, 'back-office/moderation');
$canMemberModeration = $gate->allows('admin.members.moderate');
$boNavEventInsights = str_starts_with($p, 'back-office/events/insights');
$boNavEvents = str_starts_with($p, 'back-office/events') && !$boNavEventInsights;
$boNavCourrierTrace = str_starts_with($p, 'back-office/courrier/traceabilite');
$boNavPortalOpsBoard = $p === 'tableau-operationnel' || str_starts_with($p, 'tableau-operationnel/');
$boNavOpsBoard = str_starts_with($p, 'back-office/tableau-operationnel');
$boNavOpsAdmin = str_starts_with($p, 'back-office/centre-operations') || str_starts_with($p, 'back-office/operations-admin');
$boNavPositions = str_starts_with($p, 'back-office/positions');
$boNavConformite = str_starts_with($p, 'back-office/conformite');
$studioPath = function_exists('training_studio_path') ? training_studio_path() : 'back-office/ressources/training/studio';
$boNavStudioActive = str_starts_with($p, $studioPath . '/') || $p === $studioPath;
$lmsResPath = function_exists('training_lms_admin_path') ? training_lms_admin_path() : 'back-office/ressources/training';
$boNavLmsRes = $p === $lmsResPath || str_starts_with($p, $lmsResPath . '/');
$boNavHrCharter = str_starts_with($p, 'back-office/ressources/training/charte-rh');
$boNavLmsFeedback = str_starts_with($p, 'back-office/ressources/training/feedback');
$boNavLmsEnrollments = str_starts_with($p, 'back-office/ressources/training/enrollments');
$boNavLmsReports = str_starts_with($p, 'back-office/ressources/training/reports');
$boNavLmsCertificates = str_starts_with($p, 'back-office/ressources/training/certificates');
$boNavLmsAuditTrail = str_starts_with($p, 'back-office/ressources/training/audit');
$boNavLmsCompetences = str_starts_with($p, 'back-office/ressources/training/competences');
$boNavPjrAssignments = str_starts_with($p, 'back-office/personnel-job-roles/assignments');
$boNavPlatformShell = function_exists('is_platform_site_admin_shell_request') && is_platform_site_admin_shell_request();
$canMurOperationnel = $gate->allows('operational.board.view')
    || $gate->allows('operational.board.edit')
    || $gate->allows('admin.organization')
    || $gate->allows('admin.access')
    || $gate->allows('site.support');
$boNavLmsSubPage = $boNavHrCharter || $boNavLmsFeedback || $boNavStudioActive
    || $boNavLmsEnrollments || $boNavLmsReports || $boNavLmsCertificates || $boNavLmsAuditTrail || $boNavLmsCompetences;
$canOrgStructure = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
?>
<div class="flex h-full min-h-0 flex-col border-r border-slate-800/80 bg-slate-950">
    <div class="border-b border-slate-800/80 px-4 py-5">
        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-emerald-400/90">Back-office</p>
        <p class="mt-1 text-base font-black tracking-tight text-white">Communauté</p>
        <?php if ($tenantLabel !== ''): ?>
            <p class="mt-1 truncate text-xs font-medium text-slate-400" title="<?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tenantLabel, ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <p class="mt-1 text-xs text-slate-500">Administration de votre espace</p>
        <?php endif; ?>
    </div>

    <nav class="bo-side-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-4 pt-5" aria-label="Navigation back-office">
        <?php $boSection('Vue d’ensemble'); ?>
        <?php $boLink('back-office', 'Tableau de bord', $boNavHome); ?>

        <?php $boSection('Membres & accès'); ?>
        <?php $boLink('back-office/users', 'Utilisateurs', $boNavUsers); ?>
        <?php if ($canInv): ?>
            <?php $boLink('back-office/invitations', 'Invitations', $boNavInv); ?>
        <?php endif; ?>
        <?php $boSection('Recrutement'); ?>
        <?php $boLink($rwPath, 'Bureau recrutement', $boNavRecWorkspaceDash); ?>
        <?php
        $boRecN = (int) ($boBadges['recruitments_submitted'] ?? 0);
        $boRecBadge = !empty($boBadges['show_staff_recruitment']) && $boRecN > 0 ? $boRecN : null;
        $boLink('back-office/recruitments', 'File des dossiers', $boNavRec && !$boNavRecSettings && !$boNavRecMessages, $boRecBadge, 'emerald');
        ?>
        <?php $boLink($rwPath . '/analyses', 'Analyses candidatures', $boNavRecWorkspaceAnalytics); ?>
        <?php $boLink('back-office/recruitments/settings', 'SLA recrutement', $boNavRecSettings); ?>
        <?php $boLink('back-office/recruitments/messages-prefaits', 'Messages préfaits', $boNavRecMessages); ?>
        <?php if ($canRecOffers): ?>
            <?php $boLink('back-office/recruitment/offers', 'Offres publiées', $boNavRecOffers); ?>
            <?php $boLink('back-office/recruitment/offers/create', 'Nouvelle offre', $boNavRecOfferNew); ?>
        <?php endif; ?>
        <?php $boLink('back-office/roles', 'Rôles communautaires', $boNavRoles); ?>
        <?php $boLink('back-office/roles/presets', 'Profils & kits de rôles', $boNavRolesPresets); ?>
        <?php $boLink('back-office/roles-functions', 'Cellule S1 — doctrine des fonctions', $boNavRolesFx); ?>
        <?php $boLink('back-office/personnel-job-roles', 'Emplois & missions', $boNavPjr && !$boNavPjrAssignments); ?>
        <?php if ($canOrgStructure): ?>
            <?php $boLink('back-office/personnel-job-roles/assignments', 'Affectations emplois & missions', $boNavPjrAssignments); ?>
        <?php endif; ?>
        <?php $boLink('deploiement', 'Déploiement personnel', $boNavPersonnelDeployment); ?>
        <?php $boLink('back-office/roleplay-followup', 'Suivi roleplay', $boNavRoleplayFollowup); ?>

        <?php $boSection('Organisation'); ?>
        <?php $boLink('back-office/organisation-effectifs', 'Structure des effectifs', $boNavEff); ?>
        <?php if ($canStructureRecruitmentHub): ?>
            <?php $boLink('back-office/organisation/structure', 'Structure & recrutement', $boNavStructureHub); ?>
        <?php endif; ?>
        <?php $boLink('back-office/groups', 'Groupes', $boNavGroups); ?>
        <?php $boLink('back-office/teams', 'Équipes', $boNavTeams); ?>
        <?php $boLink('back-office/categories', 'Catégories', $boNavCats); ?>
        <?php $boLink('back-office/referentiels/grades', 'Référentiel des grades', $boNavGrades); ?>
        <?php if ($canOrgStructure): ?>
            <?php $boLink('back-office/positions', 'Postes & fonctions (état-major)', $boNavPositions); ?>
        <?php endif; ?>
        <?php if ($canTenantModules || $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support')): ?>
            <?php $boLink('back-office/organisation/anciennete', 'Ancienneté (fiches & RH)', $boNavSeniority); ?>
        <?php endif; ?>

        <?php if ($canCommsSection): ?>
            <?php $boSection('Communications'); ?>
            <?php $boLink('back-office/communications', 'Nouveau message', $boNavCommunications && !$boNavCommsHistory && !$boNavCommsTemplates && !$boNavCommsGroups); ?>
            <?php $boLink('back-office/communications/history', 'Historique des envois', $boNavCommsHistory); ?>
            <?php $boLink('back-office/communications/templates', 'Modèles d’e-mail', $boNavCommsTemplates); ?>
            <?php $boLink('back-office/communications/groups', 'Groupes de diffusion', $boNavCommsGroups); ?>
        <?php endif; ?>

        <?php $boSection('Communauté'); ?>
        <?php $boLink('back-office/community', 'Identité & code d’accès', $boNavCommCode); ?>
        <?php $boLink('back-office/community/presentation', 'Page d’accueil publique', $boNavCommPres); ?>
        <?php $boLink('back-office/alerts', 'Annonces & alertes', $boNavAlerts); ?>
        <?php $boLink('back-office/configuration', 'Paramètres avancés', $boNavConfig); ?>
        <?php if ($canIntegrationsBo): ?>
            <?php $boLink('back-office/integrations', 'Intégrations externes', $boNavInteg); ?>
        <?php endif; ?>
        <?php $boLink('back-office/analytics', 'Indicateurs d’usage', $boNavAnalytics); ?>
        <?php $boLink('back-office/analytics/conversion', 'Conversion communautés', $boNavAnalyticsConversion); ?>
        <?php $boLink('back-office/dashboard-pins', 'Raccourcis du portail', $boNavPins); ?>
        <?php $boLink('back-office/onboarding-members', 'Onboarding membres', $boNavOnbMembers); ?>
        <?php $boLink('back-office/onboarding-recovery', 'Aide après inscription', $boNavOnb); ?>

        <?php $boSection('Pilotage'); ?>
        <?php if ($canMurOperationnel): ?>
            <?php $boLink('tableau-operationnel', 'Mur opérationnel (vue membres)', $boNavPortalOpsBoard); ?>
        <?php endif; ?>
        <?php $boLink('back-office/centre-operations', 'Centre d’opérations admin', $boNavOpsAdmin); ?>
        <?php $boLink('back-office/tableau-operationnel', 'Pilotage du mur opérationnel', $boNavOpsBoard); ?>
        <?php $boLink('back-office/courrier/traceabilite', 'Traçabilité courrier', $boNavCourrierTrace); ?>
        <?php if ($canOrgStructure): ?>
            <?php $boLink('back-office/conformite/export-dossier', 'Export dossier conformité', $boNavConformite); ?>
        <?php endif; ?>
        <?php $boLink('back-office/audit', 'Journal d’activité', $boNavAudit); ?>
        <?php if ($canMemberModeration): ?>
            <?php $boLink('back-office/moderation', 'Restrictions membres', $boNavMod); ?>
        <?php endif; ?>
        <?php $boLink('back-office/events', 'RSVP & pointage', $boNavEvents); ?>
        <?php $boLink('back-office/events/insights', 'Insights présence', $boNavEventInsights); ?>

        <?php if ($canDocs || $canTraining || $canTenantModules): ?>
            <?php $boSection('Ressources & outils'); ?>
            <?php if ($canDocs): ?>
                <?php $boLink('documents/gestion', 'Bibliothèque documentaire', false); ?>
            <?php endif; ?>
            <?php if ($canTraining): ?>
                <?php $boLink($lmsResPath, 'Formations (tableau de bord)', $boNavLmsRes && !$boNavLmsSubPage); ?>
                <?php $boLink('back-office/ressources/training/enrollments', 'Inscriptions & validations', $boNavLmsEnrollments); ?>
                <?php $boLink('back-office/ressources/training/reports', 'Rapports & suivis', $boNavLmsReports); ?>
                <?php $boLink('back-office/ressources/training/certificates', 'Certificats & attestations', $boNavLmsCertificates); ?>
                <?php $boLink('back-office/ressources/training/audit', 'Journal pédagogique (audit)', $boNavLmsAuditTrail); ?>
                <?php $boLink('back-office/ressources/training/competences/bureau-personnel', 'Compétences (LMS)', $boNavLmsCompetences); ?>
                <?php $boLink('back-office/ressources/training/charte-rh', 'Charte RH (formations)', $boNavHrCharter); ?>
                <?php $boLink('back-office/ressources/training/feedback', 'Feedback post-leçon', $boNavLmsFeedback); ?>
                <?php $boLink(training_studio_path(), 'Studio des parcours', $boNavStudioActive); ?>
            <?php endif; ?>
            <?php if ($canTenantModules): ?>
                <?php $boLink('admin/modpacks', 'Modpacks', false); ?>
                <?php $boLink('admin/forum-config', 'Briefing & forum', false); ?>
                <?php $boLink('back-office/forum/priorite-mission/nouveau', 'Publication priorité mission', $boNavForumMissionPriority); ?>
                <?php $boLink('back-office/cooperation/missions', 'Coopérations inter-unités', $boNavCoop); ?>
                <?php if (function_exists('can') && (can('cooperation.catalog.manage') || can('cooperation.announcements.manage'))): ?>
                    <?php $boLink('back-office/cooperation/catalog', 'Types de coopération (catalogue)', str_starts_with($p, 'back-office/cooperation/catalog')); ?>
                    <?php $boLink('back-office/cooperation/announcements', 'Annonces coopération', str_starts_with($p, 'back-office/cooperation/announcements')); ?>
                <?php endif; ?>
                <?php $boLink('admin/atak-config', 'Cartographie & ATAK', false); ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($gate->allows('admin.system') || $gate->allows('site.support')): ?>
            <?php $boSection('Plateforme'); ?>
            <?php $boLink('admin', $gate->allows('admin.system') ? 'Administration site' : 'Pilotage site (vue assistance)', $boNavPlatformShell); ?>
        <?php endif; ?>
        <?php if ($canForumModConsole): ?>
            <?php $boSection('Modération'); ?>
            <?php
            $boModN = (int) ($boBadges['forum_moderation_total'] ?? 0);
            $boModBadge = $boModN > 0 ? $boModN : null;
            $boLink('back-office/forum-moderation', 'Console modération forum', str_starts_with($p, 'back-office/forum-moderation'), $boModBadge, 'rose');
            ?>
            <?php $boLink('admin/content-moderation', 'Fichiers et pièces jointes', str_starts_with($p, 'admin/content-moderation')); ?>
        <?php endif; ?>
    </nav>

    <div class="border-t border-slate-800/80 space-y-2 p-3">
        <?php
        $boPersN = (int) ($boBadges['personal_inbox'] ?? 0);
        $boPersBadge = $boPersN > 0 ? $boPersN : null;
        $boPersAria = $boPersBadge !== null
            ? ' aria-label="Mon activité — ' . ($boPersN > 99 ? '99+' : (string) $boPersN) . ' notification(s)"'
            : '';
        $boPersPill = $boPersBadge !== null
            ? '<span class="inline-flex min-w-[1.35rem] shrink-0 justify-center rounded-full bg-sky-600 px-1.5 py-0.5 text-[10px] font-black leading-none text-white" aria-hidden="true">' . htmlspecialchars($boPersN > 99 ? '99+' : (string) $boPersN, ENT_QUOTES, 'UTF-8') . '</span>'
            : '';
        ?>
        <a href="<?= htmlspecialchars(url('activite'), ENT_QUOTES, 'UTF-8') ?>" class="flex w-full min-w-0 items-center justify-between gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800 hover:text-white"<?= $boPersAria ?>>
            <span class="flex min-w-0 items-center gap-2">
                <svg class="h-4 w-4 shrink-0 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
                <span class="truncate">Mon activité</span>
            </span>
            <?= $boPersPill ?>
        </a>
        <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800 hover:text-white">
            <svg class="h-4 w-4 shrink-0 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Retour au portail
        </a>
    </div>
</div>
