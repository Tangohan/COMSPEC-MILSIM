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

$userName = trim((string) (\App\Core\Session::get('display_name') ?? \App\Core\Session::get('callsign') ?? 'Administrateur')) ?: 'Administrateur';
$words = preg_split('/\s+/u', $userName) ?: [];
$initials = count($words) > 1
    ? mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1))
    : mb_strtoupper(mb_substr($userName, 0, 2));
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

    <div class="ath-sidebar__nav" id="ath-sidebar-nav">
        <div class="ath-sidebar__group is-open" data-ath-nav-group="pilotage">
            <button type="button" class="ath-sidebar__group-head" data-ath-group-toggle aria-expanded="true"><svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2"><path d="m9 18 6-6-6-6"></path></svg><span class="ath-sidebar__group-label">PILOTAGE</span></button>
            <div class="ath-sidebar__group-body">
                <?php $link('admin', 'Tableau de bord', 'dash', $active('admin', true)); ?>
                <?php $link('admin/analytics', 'Indicateurs transverses', 'chart', $active('admin/analytics')); ?>
                <?php $link('admin/ops-center', 'Synthèse opérationnelle', 'chart', $active('admin/ops-center')); ?>
                <?php if ($isPlatformAdmin) $link('admin/system/retours-interface', 'Retours interface', 'book', $active('admin/system/retours-interface')); ?>
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
