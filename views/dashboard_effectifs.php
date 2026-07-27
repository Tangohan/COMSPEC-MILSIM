<?php
$base = url('');
$title = $title ?? 'Bureau des effectifs — Athena';
$dashboard_tenant_label = $dashboard_tenant_label ?? null;
$dashboard_is_default_tenant = !empty($dashboard_is_default_tenant);
$can_view_personnel_directory = !empty($can_view_personnel_directory);
$can_open_effectifs_workspace = !empty($can_open_effectifs_workspace);
$dashboard_effectifs_rows = is_array($dashboard_effectifs_rows ?? null) ? $dashboard_effectifs_rows : [];
$can_view_atak_operators = !empty($can_view_atak_operators);
$can_see_inactive_effectifs = !empty($can_see_inactive_effectifs);
$atak_operators_linked_count = $atak_operators_linked_count ?? null;
$can_manage_invitations = !empty($can_manage_invitations);
$pending_invitations_count = (int) ($pending_invitations_count ?? 0);
$ewPath = function_exists('effectifs_workspace_path') ? effectifs_workspace_path() : 'back-office/ressources/effectifs';
$unitLabel = ($dashboard_tenant_label !== null && $dashboard_tenant_label !== '')
    ? (string) $dashboard_tenant_label
    : 'Communauté';
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? 'Bureau des effectifs : registre RH et carte tactique.';
    require base_path('views/partials/seo_meta.php');
?>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/design-system.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/athena-header.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/navbar-info-banners.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/navbar-info-banners.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body class="dashboard-shell layout-light text-slate-900 antialiased" style="background:#f8fafc;">
<script defer src="<?= htmlspecialchars(asset_url('assets/js/portal-alerts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if (is_file(base_path('public/assets/js/athena-header.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php
$alertBanners = [];
require base_path('views/partials/alert_banners.php');
?>
<div class="min-h-screen bg-[#f8fafc]">
<main class="min-h-screen bg-[#f8fafc] text-slate-900">
    <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
    <?php require base_path('views/partials/header_dashboard.php'); ?>

    <section class="relative overflow-hidden border-b border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-slate-100">
        <div class="relative mx-auto max-w-6xl px-6 py-12 md:px-10 md:py-14">
            <p class="mb-3 text-[10px] font-black uppercase tracking-[0.45em] text-emerald-700">Profil bureau des effectifs</p>
            <h1 class="mb-4 max-w-3xl text-3xl font-black uppercase tracking-tight text-slate-900 md:text-4xl">
                <?= htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8') ?><span class="text-emerald-600">.</span>
            </h1>
            <p class="mb-8 max-w-2xl text-sm leading-relaxed text-slate-600 md:text-base">
                Espace dédié au registre RH, au forum public et à la carte tactique. Formations et recrutement ne font pas partie de ce profil.
                <?php if (function_exists('can') && (can('admin.organization') || can('admin.access'))): ?>
                <a href="<?= htmlspecialchars(url('back-office/organisation/parametres') . '#org-profil', ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-800 underline underline-offset-2">Vérifier ou réappliquer le profil</a>
                <?php endif; ?>
            </p>
            <div class="flex flex-col flex-wrap gap-3 sm:flex-row">
                <?php if ($can_open_effectifs_workspace): ?>
                <a href="<?= htmlspecialchars(url($ewPath), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-7 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-white shadow-sm transition hover:bg-emerald-500">
                    Bureau effectifs
                </a>
                <?php endif; ?>
                <?php if ($can_manage_invitations): ?>
                <a href="<?= htmlspecialchars(url('back-office/invitations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-emerald-600 bg-white px-7 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-emerald-800 transition hover:bg-emerald-50">
                    Inviter un membre<?= $pending_invitations_count > 0 ? ' (' . $pending_invitations_count . ')' : '' ?>
                </a>
                <a href="<?= htmlspecialchars(url('back-office/users/create'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-slate-800 transition hover:bg-slate-50">
                    Créer un compte
                </a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('personnel'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-slate-800 transition hover:bg-slate-50">
                    Annuaire
                </a>
                <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-slate-800 transition hover:bg-slate-50">
                    Carte ATAK
                </a>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto grid max-w-6xl gap-4 px-6 py-10 md:grid-cols-3 md:px-10">
            <a href="<?= htmlspecialchars(url($ewPath), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 no-underline transition hover:border-emerald-300 hover:bg-emerald-50/40">
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-emerald-700">RH</p>
                <h2 class="mt-2 text-lg font-black text-slate-900">Tableur des effectifs</h2>
                <p class="mt-2 text-sm text-slate-600">Profils, statuts, affectations et élévations.</p>
            </a>
            <a href="<?= htmlspecialchars(url('orbat'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 no-underline transition hover:border-emerald-300 hover:bg-emerald-50/40">
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-sky-700">Organisation</p>
                <h2 class="mt-2 text-lg font-black text-slate-900">ORBAT</h2>
                <p class="mt-2 text-sm text-slate-600">Vue hiérarchique des unités et postes.</p>
            </a>
            <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-5 no-underline transition hover:border-emerald-300 hover:bg-emerald-50/40">
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-amber-700">Terrain</p>
                <h2 class="mt-2 text-lg font-black text-slate-900">Carte ATAK</h2>
                <p class="mt-2 text-sm text-slate-600">
                    <?php if ($can_view_atak_operators && $atak_operators_linked_count !== null): ?>
                        <?= (int) $atak_operators_linked_count ?> en liaison · suivi temps réel.
                    <?php else: ?>
                        Suivi cartographique et opérateurs connectés.
                    <?php endif; ?>
                </p>
            </a>
        </div>
    </section>

    <?php if ($can_view_personnel_directory && $dashboard_effectifs_rows !== []): ?>
    <section class="bg-[#f8fafc]">
        <div class="mx-auto max-w-[100rem] px-4 py-10 sm:px-6 md:px-10">
            <div class="mb-6">
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700">Aperçu</p>
                <h3 class="mt-2 text-2xl font-black uppercase tracking-tight text-slate-900">Effectifs récents</h3>
            </div>
            <?php require base_path('views/partials/dashboard_effectifs_table.php'); ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap gap-3 px-6 py-8 md:px-10">
            <?php if ($can_manage_invitations): ?>
            <a href="<?= htmlspecialchars(url('back-office/invitations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 no-underline hover:bg-slate-50">
                Invitations<?= $pending_invitations_count > 0 ? ' (' . $pending_invitations_count . ')' : '' ?>
            </a>
            <a href="<?= htmlspecialchars(url('back-office/users/create'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 no-underline hover:bg-slate-50">Créer un compte</a>
            <a href="<?= htmlspecialchars(url('back-office/users'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 no-underline hover:bg-slate-50">Membres</a>
            <?php endif; ?>
            <a href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 no-underline hover:bg-slate-50">Forum</a>
            <a href="<?= htmlspecialchars(url('back-office/community'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 no-underline hover:bg-slate-50">Paramètres</a>
            <a href="<?= htmlspecialchars(url('back-office/atak/operateurs'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 no-underline hover:bg-slate-50">Effectifs en liaison</a>
            <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 no-underline hover:bg-slate-50">Ma fiche</a>
        </div>
    </section>
</main>
</div>
</body>
</html>
