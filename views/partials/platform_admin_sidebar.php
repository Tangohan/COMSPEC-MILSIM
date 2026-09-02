<?php
declare(strict_types=1);
?>
<style>
    .pa-side-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
    .pa-side-scroll::-webkit-scrollbar-thumb { background: #475569; border-radius: 999px; }
    .pa-side-scroll::-webkit-scrollbar-track { background: transparent; }
    .pa-submenu details > summary { list-style: none; }
    .pa-submenu details > summary::-webkit-details-marker { display: none; }
</style>
<?php
$p = function_exists('back_office_path_suffix') ? back_office_path_suffix() : '';
$gate = \App\Core\Gate::getInstance();
$isPlatformAdmin = $gate->allows('admin.system');
$isSupportHub = $gate->allows('site.support') && !$isPlatformAdmin;
$hasOrgPath = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
$canForumModConsole = function_exists('forum_user_can_moderate') && forum_user_can_moderate();

$paLink = static function (string $path, string $label, bool $active): void {
    $cls = $active
        ? 'flex items-center gap-3 rounded-lg bg-slate-800 px-3 py-2.5 text-sm font-semibold text-white shadow-sm ring-1 ring-white/10'
        : 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800/80 hover:text-white';
    echo '<a href="' . htmlspecialchars(url($path), ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '"><span class="truncate">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span></a>';
};

$paSection = static function (string $title): void {
    echo '<p class="mt-6 mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 first:mt-0">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
};

$paSubLink = static function (string $path, string $label, bool $active): void {
    $cls = $active
        ? 'block rounded-md bg-slate-800/90 px-3 py-2 text-xs font-semibold text-white'
        : 'block rounded-md px-3 py-2 text-xs font-medium text-slate-400 transition hover:bg-slate-800/60 hover:text-slate-100';
    echo '<a href="' . htmlspecialchars(url($path), ENT_QUOTES, 'UTF-8') . '" class="' . $cls . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
};

$navDash = $p === 'admin';
$navTenants = $p === 'admin/tenants' || str_starts_with($p, 'admin/tenants/');
$navTenantRecovery = str_starts_with($p, 'admin/system/tenant-recovery');
$navAnalytics = $p === 'admin/analytics';
$navNewsletter = $p === 'admin/newsletter';
$navOps = $p === 'admin/ops-center';
$navAudit = $p === 'admin/audit' || str_starts_with($p, 'admin/audit/');
$navMaint = $p === 'admin/maintenance' || str_starts_with($p, 'admin/maintenance/');
$navStorage = str_starts_with($p, 'admin/system/storage');
$navRoles = $p === 'admin/roles' || str_starts_with($p, 'admin/roles/');
$navSiteRoles = $p === 'admin/site-roles' || str_starts_with($p, 'admin/site-roles/');
$navSettings = $p === 'admin/settings';
$navBlocklist = str_starts_with($p, 'admin/system/blocklist');
$navSanctions = str_starts_with($p, 'admin/system/member-sanctions');
$navAdvancedFiche = str_starts_with($p, 'admin/system/advanced-fiche-edit');
$navPlatformUsers = $p === 'admin/users' || str_starts_with($p, 'admin/users/');
$navBrief = str_starts_with($p, 'admin/system/brief');
$navCron = str_starts_with($p, 'admin/system/cron');
$navUxFeedback = str_starts_with($p, 'admin/system/retours-interface');
$navDeployment = str_starts_with($p, 'admin/system/deployment');
$navUpdates = str_starts_with($p, 'admin/system/updates');
$navAlerts = str_starts_with($p, 'admin/system/alerts');
$navDemoNda = str_starts_with($p, 'admin/system/demo-nda');
$navRecruitTools = str_starts_with($p, 'admin/system/recruitment-portal-tools');
$navPlans = str_starts_with($p, 'admin/system/subscription-plans');
$navCoopCatalog = str_starts_with($p, 'admin/system/cooperation/catalog');
$navCoopAnnounce = str_starts_with($p, 'admin/system/cooperation/announcements');
$navMilitaryRef = str_starts_with($p, 'admin/system/military-referential');
$alertsCreateActive = $p === 'admin/system/alerts/create';
$alertsListActive = $navAlerts && !$alertsCreateActive;
$alertsOpen = $navAlerts;
?>
<div class="flex h-full min-h-0 flex-col border-r border-slate-800/80 bg-slate-950">
    <div class="border-b border-slate-800/80 px-4 py-5">
        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-amber-400/90">Plateforme</p>
        <p class="mt-1 text-base font-black tracking-tight text-white"><?= $isSupportHub ? 'Pilotage site' : 'Administration site' ?></p>
        <p class="mt-1 text-xs text-slate-500 leading-relaxed">
            <?= $isSupportHub
                ? 'Synthèse et journaux. Les réglages sensibles restent réservés aux administrateurs plateforme.'
                : 'Outils transverses : identité globale, paramètres, maintenance et traçabilité.' ?>
        </p>
    </div>

    <nav class="pa-side-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-4 pt-5" aria-label="Navigation administration plateforme">
        <?php $paSection('Vue d’ensemble'); ?>
        <?php $paLink('admin', 'Tableau de bord', $navDash); ?>
        <?php if ($isPlatformAdmin): ?>
            <?php $paSection('Communautés'); ?>
            <?php $paLink('admin/tenants', 'Annuaire des communautés', $navTenants); ?>
            <?php $paLink('admin/system/tenant-recovery', 'Récupération communauté', $navTenantRecovery); ?>
            <?php $paLink('admin/system/subscription-plans', 'Formules d’accès', $navPlans); ?>
            <?php $paLink('admin/newsletter', 'Lettre d’information du site', $navNewsletter); ?>
            <?php $paLink('admin/system/demo-nda', 'Accès démo du site', $navDemoNda); ?>
        <?php endif; ?>
        <?php $paLink('admin/analytics', 'Indicateurs transverses', $navAnalytics); ?>
        <?php if ($isPlatformAdmin): ?>
            <?php $paLink('admin/system/retours-interface', 'Retours interface', $navUxFeedback); ?>
        <?php endif; ?>
        <?php $paLink('admin/ops-center', 'Synthèse opérationnelle', $navOps); ?>

        <?php if ($isPlatformAdmin): ?>
            <?php $paSection('Sécurité & accès'); ?>
            <?php $paLink('admin/users', 'Comptes utilisateurs', $navPlatformUsers); ?>
            <?php $paLink('admin/system/advanced-fiche-edit', 'Édition avancée de fiche', $navAdvancedFiche); ?>
            <?php $paLink('admin/roles', 'Rôles système', $navRoles); ?>
            <?php $paLink('admin/site-roles', 'Affectations rôles site', $navSiteRoles); ?>
            <?php $paLink('admin/system/blocklist', 'Liste de restriction (site entier)', $navBlocklist); ?>
            <?php $paLink('admin/system/member-sanctions', 'Sanctions à l’échelle du site', $navSanctions); ?>
            <?php $paLink('admin/system/recruitment-portal-tools', 'Outils du portail candidatures', $navRecruitTools); ?>

            <?php $paSection('Configuration'); ?>
            <?php $paLink('admin/settings', 'Paramètres système', $navSettings); ?>
            <?php $paLink('admin/system/brief', 'Brief (accès membres)', $navBrief); ?>
            <?php $paLink('admin/system/cron', 'Tâches automatiques', $navCron); ?>

            <?php $paSection('Référentiels du site'); ?>
            <?php $paLink('admin/system/cooperation/catalog', 'Types de coopération', $navCoopCatalog); ?>
            <?php $paLink('admin/system/cooperation/announcements', 'Annonces de coopération', $navCoopAnnounce); ?>
            <?php $paLink('admin/system/military-referential', 'Référentiel militaire', $navMilitaryRef); ?>

            <?php $paSection('Déploiement et préqualification'); ?>
            <?php $paLink('admin/system/updates', 'Mises à jour plateforme', $navUpdates); ?>
            <?php $paLink('admin/system/deployment', 'Publications & canaux', $navDeployment && !str_starts_with($p, 'admin/system/deployment/communities')); ?>
            <?php $paLink('admin/system/deployment/communities', 'Communautés de test', str_starts_with($p, 'admin/system/deployment/communities')); ?>

            <div class="pa-submenu mt-2 px-1">
                <details class="group rounded-lg border border-slate-800/80 bg-slate-900/40" <?= $alertsOpen ? 'open' : '' ?>>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800/50">
                        <span>Alertes plateforme</span>
                        <svg class="h-4 w-4 shrink-0 text-slate-500 transition group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </summary>
                    <div class="space-y-0.5 border-t border-slate-800/80 px-2 py-2">
                        <?php $paSubLink('admin/system/alerts', 'Toutes les alertes', $alertsListActive); ?>
                        <?php $paSubLink('admin/system/alerts/create', 'Nouvelle alerte', $alertsCreateActive); ?>
                    </div>
                </details>
            </div>
        <?php else: ?>
            <?php $paSection('Communication & disponibilité'); ?>
            <?php $paLink('admin/system/alerts', 'Alertes visibles sur le site', $navAlerts); ?>
        <?php endif; ?>

        <?php $paSection('Exploitation'); ?>
        <?php if ($isPlatformAdmin): ?>
            <?php $paLink('admin/system/storage', 'Espace disque et historiques', $navStorage); ?>
        <?php endif; ?>
        <?php $paLink('admin/maintenance', 'Maintenance des données', $navMaint); ?>
        <?php $paLink('admin/audit', 'Journal d’audit', $navAudit); ?>

        <?php if ($hasOrgPath): ?>
            <?php $paSection('Communauté active'); ?>
            <?php $paLink('back-office', 'Back-office communauté', str_starts_with($p, 'back-office')); ?>
        <?php endif; ?>

        <?php if ($canForumModConsole): ?>
            <?php $paSection('Modération'); ?>
            <?php $paLink('back-office/forum-moderation', 'Console modération forum', str_starts_with($p, 'back-office/forum-moderation')); ?>
            <?php $paLink('admin/content-moderation', 'Fichiers et pièces jointes', str_starts_with($p, 'admin/content-moderation')); ?>
        <?php endif; ?>
    </nav>

    <div class="border-t border-slate-800/80 p-3 space-y-2">
        <a href="<?= htmlspecialchars(url('dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="flex items-center justify-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800 hover:text-white">
            <svg class="h-4 w-4 shrink-0 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Retour au portail
        </a>
    </div>
</div>
