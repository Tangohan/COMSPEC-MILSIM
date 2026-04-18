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

$boLink = static function (string $path, string $label, bool $active): void {
    $cls = $active
        ? 'flex items-center gap-3 rounded-lg bg-slate-800 px-3 py-2.5 text-sm font-semibold text-white shadow-sm ring-1 ring-white/10'
        : 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800/80 hover:text-white';
    echo '<a href="' . htmlspecialchars(url($path), ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '"><span class="truncate">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
};

$boSection = static function (string $title): void {
    echo '<p class="mt-6 mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 first:mt-0">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
};

/* Préfixe boNav* : ne pas utiliser $groups, $users, etc. — ce sont des noms de données vues écrasés avant le require du contenu. */
$boNavHome = $p === 'back-office';
$boNavUsers = $p === 'back-office/users' || str_starts_with($p, 'back-office/users/');
$boNavInv = str_starts_with($p, 'back-office/invitations');
$boNavRec = str_starts_with($p, 'back-office/recruitments');
$boNavRecSettings = str_starts_with($p, 'back-office/recruitments/settings');
$boNavRecMessages = str_starts_with($p, 'back-office/recruitments/messages-prefaits');
$boNavRecOfferNew = str_starts_with($p, 'back-office/recruitment/offers/create');
$boNavRecOffers = (str_starts_with($p, 'back-office/recruitment/offers') && !$boNavRecOfferNew) || str_starts_with($p, 'back-office/recruitment/reference-format');
$canRecOffers = $gate->allows('organization.recruitment.openings.manage') || $gate->allows('organization.recruitment.manage');
$boNavRoles = $p === 'back-office/roles' || str_starts_with($p, 'back-office/roles/');
$boNavRolesFx = $p === 'back-office/roles-functions' || str_starts_with($p, 'back-office/roles-functions/');
$boNavPjr = str_starts_with($p, 'back-office/personnel-job-roles');
$boNavPersonnelDeployment = str_starts_with($p, 'deploiement');
$boNavRoleplayFollowup = str_starts_with($p, 'back-office/roleplay-followup');
$boNavEff = str_starts_with($p, 'back-office/organisation-effectifs');
$boNavStructureHub = str_starts_with($p, 'back-office/organisation/structure');
$boNavGroups = str_starts_with($p, 'back-office/groups');
$boNavCommunications = str_starts_with($p, 'back-office/communications');
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
$boNavEvents = str_starts_with($p, 'back-office/events');
$boNavCourrierTrace = str_starts_with($p, 'back-office/courrier/traceabilite');
$boNavOpsBoard = str_starts_with($p, 'back-office/tableau-operationnel');
$boNavOpsAdmin = str_starts_with($p, 'back-office/centre-operations') || str_starts_with($p, 'back-office/operations-admin');
$studioPath = function_exists('training_studio_path') ? training_studio_path() : 'back-office/ressources/training/studio';
$boNavStudioActive = str_starts_with($p, $studioPath . '/') || $p === $studioPath;
$lmsResPath = function_exists('training_lms_admin_path') ? training_lms_admin_path() : 'back-office/ressources/training';
$boNavLmsRes = $p === $lmsResPath || str_starts_with($p, $lmsResPath . '/');
$boNavHrCharter = str_starts_with($p, 'back-office/ressources/training/charte-rh');
$boNavLmsFeedback = str_starts_with($p, 'back-office/ressources/training/feedback');
$boNavPlatformShell = function_exists('is_platform_site_admin_shell_request') && is_platform_site_admin_shell_request();
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
        <?php $boLink('back-office/recruitments', 'Candidatures', $boNavRec); ?>
        <?php $boLink('back-office/recruitments/settings', 'Paramètres SLA recrutement', $boNavRecSettings); ?>
        <?php $boLink('back-office/recruitments/messages-prefaits', 'Messages préfaits recrutement', $boNavRecMessages); ?>
        <?php if ($canRecOffers): ?>
            <?php $boLink('back-office/recruitment/offers', 'Offres publiées', $boNavRecOffers); ?>
            <?php $boLink('back-office/recruitment/offers/create', 'Nouvelle offre', $boNavRecOfferNew); ?>
        <?php endif; ?>
        <?php $boLink('back-office/roles', 'Rôles communautaires', $boNavRoles); ?>
        <?php $boLink('back-office/roles-functions', 'Rôles & fonctions', $boNavRolesFx); ?>
        <?php $boLink('back-office/personnel-job-roles', 'Emplois & missions', $boNavPjr); ?>
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
        <?php if ($canTenantModules || $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support')): ?>
            <?php $boLink('back-office/organisation/anciennete', 'Ancienneté (fiches & RH)', $boNavSeniority); ?>
        <?php endif; ?>
        <?php if ($canCommsSection): ?>
            <?php $boLink('back-office/communications', 'E-mails aux membres', $boNavCommunications); ?>
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
        <?php $boLink('back-office/centre-operations', 'Centre d’opérations admin', $boNavOpsAdmin); ?>
        <?php $boLink('back-office/tableau-operationnel', 'Tableau opérationnel', $boNavOpsBoard); ?>
        <?php $boLink('back-office/courrier/traceabilite', 'Traçabilité courrier', $boNavCourrierTrace); ?>
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
                <?php $boLink($lmsResPath, 'Formations', $boNavLmsRes && !$boNavHrCharter); ?>
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
            <?php $boLink('back-office/forum-moderation', 'Console modération forum', str_starts_with($p, 'back-office/forum-moderation')); ?>
            <?php $boLink('admin/content-moderation', 'Fichiers et pièces jointes', str_starts_with($p, 'admin/content-moderation')); ?>
        <?php endif; ?>
    </nav>

    <div class="border-t border-slate-800/80 p-3">
        <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800 hover:text-white">
            <svg class="h-4 w-4 shrink-0 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Retour au portail
        </a>
    </div>
</div>
