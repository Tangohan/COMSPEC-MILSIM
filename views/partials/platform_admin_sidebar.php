<?php
declare(strict_types=1);

/** Navigation plateforme, sur la même charte visuelle que le back-office ATHENA. */
$p = function_exists('back_office_path_suffix') ? back_office_path_suffix() : '';
$gate = \App\Core\Gate::getInstance();
$isPlatformAdmin = $gate->allows('admin.system');
$isSupportHub = $gate->allows('site.support') && !$isPlatformAdmin;
$hasOrgPath = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
$canForumModConsole = function_exists('forum_user_can_moderate') && forum_user_can_moderate();
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$icons = [
    'dash' => 'M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z',
    'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8M22 21v-2a4 4 0 0 0-3-3.9',
    'chart' => 'M4 20V10M10 20V4M16 20v-7M22 20H2',
    'shield' => 'M12 3l8 4v6c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V7z',
    'gear' => 'M12 9a3 3 0 1 0 .01 0M20 12l2-1-2-3.5-2.3.6a6 6 0 0 0-1.6-.9L15.5 5h-4l-.6 2.2a6 6 0 0 0-1.6.9L7 7.5 5 11l2 1-2 1 2 3.5 2.3-.6c.5.4 1 .7 1.6.9l.6 2.2h4l.6-2.2c.6-.2 1.1-.5 1.6-.9l2.3.6L22 13z',
    'book' => 'M4 4h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2zM4 18h15',
    'rocket' => 'M12 2c3 2 5 5.5 5 9.5L12 16l-5-4.5C7 7.5 9 4 12 2M9 17l-2 4 5-2 5 2-2-4',
    'audit' => 'M9 3h6M4 6h16v15H4zM8 11h8M8 15h5',
];
$icon = static fn (string $key): string => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($icons[$key] ?? $icons['gear']) . '"></path></svg>';
$active = static fn (string $path, bool $exact = false): bool => $exact ? $p === $path : ($p === $path || str_starts_with($p, $path . '/'));
$link = static function (string $path, string $label, string $ico, bool $isActive) use ($h, $icon): void { ?>
    <a href="<?= $h(url($path)) ?>" class="ath-sidebar__item<?= $isActive ? ' is-active' : '' ?>" title="<?= $h($label) ?>">
        <?= $icon($ico) ?><span class="ath-sidebar__item-label"><?= $h($label) ?></span>
    </a>
<?php };

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
<nav class="ath-sidebar" id="ath-sidebar" aria-label="Navigation administration plateforme">
    <div class="ath-sidebar__head">
        <div class="ath-sidebar__logo" aria-hidden="true">A</div>
        <div class="ath-sidebar__brand">
            <div class="ath-sidebar__brand-name">ATHENA<span>.</span></div>
            <div class="ath-sidebar__brand-sub">ADMINISTRATION · PLATEFORME</div>
        </div>
        <button type="button" class="ath-sidebar__toggle" data-ath-sidebar-toggle title="Plier le menu" aria-label="Plier le menu">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="m9 18 6-6-6-6"></path></svg>
        </button>
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
        </div>
        <?php if ($isPlatformAdmin): ?>
        <div class="ath-sidebar__group is-open" data-ath-nav-group="communautes">
            <button type="button" class="ath-sidebar__group-head" data-ath-group-toggle aria-expanded="true"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><path d="m9 18 6-6-6-6"></path></svg><span class="ath-sidebar__group-label">COMMUNAUTÉS</span></button>
            <div class="ath-sidebar__group-body">
                <?php $link('admin/tenants', 'Annuaire des communautés', 'users', $active('admin/tenants')); ?>
                <?php $link('admin/system/subscription-plans', 'Formules d’accès', 'book', $active('admin/system/subscription-plans')); ?>
                <?php $link('admin/newsletter', 'Lettre d’information', 'book', $active('admin/newsletter')); ?>
                <?php $link('admin/system/demo-nda', 'Accès démo du site', 'shield', $active('admin/system/demo-nda')); ?>
            </div>
        </div>
        <div class="ath-sidebar__group is-open" data-ath-nav-group="securite">
            <button type="button" class="ath-sidebar__group-head" data-ath-group-toggle aria-expanded="true"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><path d="m9 18 6-6-6-6"></path></svg><span class="ath-sidebar__group-label">SÉCURITÉ & ACCÈS</span></button>
            <div class="ath-sidebar__group-body">
                <?php $link('admin/users', 'Comptes utilisateurs', 'users', $active('admin/users')); ?>
                <?php $link('admin/system/advanced-fiche-edit', 'Édition avancée de fiche', 'users', $active('admin/system/advanced-fiche-edit')); ?>
                <?php $link('admin/roles', 'Rôles système', 'shield', $active('admin/roles')); ?>
                <?php $link('admin/site-roles', 'Affectations rôles site', 'shield', $active('admin/site-roles')); ?>
                <?php $link('admin/system/blocklist', 'Liste de restriction', 'shield', $active('admin/system/blocklist')); ?>
                <?php $link('admin/system/member-sanctions', 'Sanctions du site', 'shield', $active('admin/system/member-sanctions')); ?>
                <?php $link('admin/system/recruitment-portal-tools', 'Outils du portail candidatures', 'gear', $active('admin/system/recruitment-portal-tools')); ?>
            </div>
        </div>
        <div class="ath-sidebar__group is-open" data-ath-nav-group="configuration">
            <button type="button" class="ath-sidebar__group-head" data-ath-group-toggle aria-expanded="true"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><path d="m9 18 6-6-6-6"></path></svg><span class="ath-sidebar__group-label">CONFIGURATION</span></button>
            <div class="ath-sidebar__group-body">
                <?php $link('admin/settings', 'Paramètres système', 'gear', $active('admin/settings')); ?>
                <?php $link('admin/system/brief', 'Brief membres', 'book', $active('admin/system/brief')); ?>
                <?php $link('admin/system/cron', 'Tâches automatiques', 'gear', $active('admin/system/cron')); ?>
                <?php $link('admin/system/cooperation/catalog', 'Types de coopération', 'book', $active('admin/system/cooperation/catalog')); ?>
                <?php $link('admin/system/cooperation/announcements', 'Annonces de coopération', 'book', $active('admin/system/cooperation/announcements')); ?>
                <?php $link('admin/system/military-referential', 'Référentiel militaire', 'book', $active('admin/system/military-referential')); ?>
                <?php $link('admin/system/updates', 'Mises à jour plateforme', 'rocket', $active('admin/system/updates')); ?>
                <?php $link('admin/system/deployment', 'Publications & canaux', 'rocket', $active('admin/system/deployment')); ?>
                <?php $link('admin/system/alerts', 'Alertes plateforme', 'shield', $active('admin/system/alerts')); ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="ath-sidebar__group is-open" data-ath-nav-group="exploitation">
            <button type="button" class="ath-sidebar__group-head" data-ath-group-toggle aria-expanded="true"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><path d="m9 18 6-6-6-6"></path></svg><span class="ath-sidebar__group-label">EXPLOITATION</span></button>
            <div class="ath-sidebar__group-body">
                <?php if ($isPlatformAdmin) $link('admin/system/storage', 'Espace disque', 'gear', $active('admin/system/storage')); ?>
                <?php $link('admin/maintenance', 'Maintenance des données', 'gear', $active('admin/maintenance')); ?>
                <?php $link('admin/audit', 'Journal d’audit', 'audit', $active('admin/audit')); ?>
                <?php if ($hasOrgPath) $link('back-office', 'Back-office communauté', 'dash', str_starts_with($p, 'back-office')); ?>
                <?php if ($canForumModConsole) $link('admin/content-moderation', 'Modération des fichiers', 'shield', $active('admin/content-moderation')); ?>
            </div>
        </div>
    </div>

    <a href="<?= $h(url('dashboard')) ?>" class="ath-sidebar__portal" title="Retour au portail">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M13.5 19.5 21 12m0 0-7.5-7.5M21 12H3"></path></svg>
        <span class="ath-sidebar__portal-label">Retour au tableau de bord</span>
    </a>
    <div class="ath-sidebar__foot">
        <div class="ath-sidebar__avatar" aria-hidden="true"><?= $h($initials) ?></div>
        <div class="ath-sidebar__user-meta">
            <div class="ath-sidebar__user-name"><?= $h(mb_strtoupper($userName)) ?></div>
            <div class="ath-sidebar__user-role"><?= $isSupportHub ? 'ASSISTANCE PLATEFORME' : 'ADMINISTRATEUR PLATEFORME' ?></div>
        </div>
    </div>
</nav>
