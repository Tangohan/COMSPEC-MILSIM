<?php
$base = url('');
$title = $title ?? 'Tableau de bord — Athena';
$showcase_training_feature = $showcase_training_feature ?? false;
$showcase_items = $showcase_items ?? [];
$dashboard_tenant_label = $dashboard_tenant_label ?? null;
$dashboard_tester_program = $dashboard_tester_program ?? null;
$showcaseJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $showcaseJsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$showcase_json = json_encode($showcase_items, $showcaseJsonFlags);
if (!is_string($showcase_json) || $showcase_json === '') {
    $showcase_json = '[]';
}
?>
<!DOCTYPE html>
<html lang="fr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
<?php
    $seo_og_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $meta_description = $meta_description ?? 'Tableau de bord Athena : accès rapide aux formations, messages et activités de votre communauté.';
    require base_path('views/partials/seo_meta.php');
?>
    <?php $tailwindBaseUrl = $base; require base_path('views/partials/tailwind_cdn_or_build.php'); ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/design-system.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/design-system.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/styles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php if (is_file(base_path('public/assets/css/dashboard-impact.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/dashboard-impact.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/announce-tiles.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/announce-tiles.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/athena-header.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/athena-header.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php if (is_file(base_path('public/assets/css/navbar-info-banners.css'))): ?>
    <link href="<?= htmlspecialchars(asset_url('assets/css/navbar-info-banners.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <?php endif; ?>
    <?php
    $loadAlpineDashboard = (!empty($showcase_training_feature) && !empty($showcase_items))
        || (int) (\App\Core\Session::get('tenant_id') ?? 0) > 1;
    ?>
    <?php if (!empty($showcase_training_feature) && !empty($showcase_items)): ?>
    <script>
        window.__dashboardShowcaseCourses = <?= $showcase_json ?>;
        document.addEventListener('alpine:init', function () {
            Alpine.data('trainingShowcase', function () {
                return {
                    openModal: null,
                    courses: window.__dashboardShowcaseCourses || [],
                    active: function () {
                        var self = this;
                        return this.courses.find(function (c) { return c.id === self.openModal; });
                    },
                    scrollTrack: function (dx) {
                        var track = this.$refs.track;
                        if (track) {
                            track.scrollBy({ left: dx, behavior: 'smooth' });
                        }
                    }
                };
            });
        });
    </script>
    <?php endif; ?>
    <?php if ($loadAlpineDashboard): ?>
    <script defer src="https://unpkg.com/alpinejs@3/dist/cdn.min.js"></script>
    <?php endif; ?>
</head>
<body class="dashboard-shell layout-light text-slate-900 selection:bg-emerald-500/25 selection:text-slate-900 antialiased" style="background:#f8fafc;">
<?php $baseUrl = $base; ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/portal-alerts.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/ui_confirm_modal.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/portal_command_palette.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php if (is_file(base_path('public/assets/js/dashboard-rail.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/dashboard-rail.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php if (is_file(base_path('public/assets/js/athena-header.js'))): ?>
<script defer src="<?= htmlspecialchars(asset_url('assets/js/athena-header.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<?php
// Sur le tableau de bord, les bandeaux classiques passent par les tuiles (pas de doublon).
// Les barres sous le menu (mini / Breaking / important) sont injectées via header_dashboard.
$alertBanners = [];
require base_path('views/partials/alert_banners.php');
?>
<?php require base_path('views/partials/forum_moderation_alerts.php'); ?>

    <div class="min-h-screen bg-[#f8fafc]">
    <main class="min-h-screen bg-[#f8fafc] text-slate-900">
        <?php require base_path('views/partials/layout_flash_toasts.php'); ?>
        <?php
        $communityMemberships = $communityMemberships ?? [];
        $candidate_enlistment_tracking = is_array($candidate_enlistment_tracking ?? null) ? $candidate_enlistment_tracking : [];
        $currentTid = (int) (\App\Core\Session::get('tenant_id') ?? 0);
        ?>

        <?php if ($currentTid === 1): ?>
        <?php require base_path('views/partials/header_dashboard.php'); ?>
        <section class="relative overflow-hidden border-b border-emerald-900/20 bg-gradient-to-br from-[#022c22] via-[#064e3b] to-[#0f172a] text-white">
            <div class="relative mx-auto max-w-6xl px-6 py-12 md:px-10 md:py-16">
                <div class="max-w-3xl">
                    <p class="mb-4 text-[10px] font-black uppercase tracking-[0.45em] text-emerald-300/90">Sans organisation rattachée</p>
                    <h2 class="mb-5 text-3xl font-black uppercase tracking-tight leading-[1.05] text-white md:text-5xl">
                        Rejoignez une unité ou une communauté<span class="text-emerald-400">.</span>
                    </h2>
                    <p class="mb-8 text-sm leading-relaxed text-emerald-100/90 md:text-base">
                        Vous n’êtes rattaché à aucune organisation pour l’instant. Parcourez le registre des communautés,
                        ou utilisez un code d’invitation pour rejoindre votre unité.
                    </p>
                    <div class="flex flex-col flex-wrap gap-4 sm:flex-row">
                        <a href="<?= url('forum') ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-500 px-8 py-4 text-xs font-black uppercase tracking-[0.2em] text-[#022c22] shadow-lg shadow-black/20 transition-colors hover:bg-emerald-400">
                            Forum
                        </a>
                        <a href="<?= url('join') ?>" class="inline-flex items-center justify-center rounded-xl border-2 border-white/25 px-8 py-4 text-xs font-black uppercase tracking-[0.2em] text-white transition-colors hover:bg-white/10">
                            Rejoindre une communauté
                        </a>
                        <a href="<?= url('communities') ?>" class="inline-flex items-center justify-center px-6 py-4 text-[11px] font-bold uppercase tracking-wider text-emerald-200/90 underline decoration-emerald-500/50 underline-offset-4 hover:text-white">
                            Parcourir le registre des unités
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($candidate_enlistment_tracking !== []): ?>
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-6 py-10 md:px-10">
                <div class="mb-6">
                    <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700">Candidatures existantes</p>
                    <h3 class="mt-2 text-2xl font-black uppercase tracking-tight text-slate-900 md:text-3xl">Suivez l’état de vos dossiers</h3>
                    <p class="mt-3 max-w-3xl text-sm text-slate-600">Retrouvez les candidatures déjà transmises et ouvrez le suivi du dossier.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <?php foreach ($candidate_enlistment_tracking as $track): ?>
                        <?php
                        $statusRaw = (string) ($track['status'] ?? 'submitted');
                        $statusLabel = [
                            'submitted' => 'En cours d’instruction',
                            'reviewed' => 'Accepté',
                            'rejected' => 'Refusé',
                            'blocked' => 'Non admis',
                        ][$statusRaw] ?? 'En cours';
                        $statusClass = match ($statusRaw) {
                            'reviewed' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            'rejected', 'blocked' => 'border-rose-200 bg-rose-50 text-rose-800',
                            default => 'border-sky-200 bg-sky-50 text-sky-800',
                        };
                        $tenantName = trim((string) ($track['tenant_name'] ?? 'Communauté'));
                        $createdAt = (string) ($track['created_at'] ?? '');
                        $createdFmt = $createdAt !== '' ? date('d/m/Y H:i', strtotime($createdAt)) : '—';
                        $portalHref = is_string($track['candidate_portal_href'] ?? null) ? (string) $track['candidate_portal_href'] : null;
                        ?>
                        <article class="rounded-2xl border border-slate-200 bg-slate-50/50 p-5">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></p>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wide <?= $statusClass ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Déposé le <?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <?php if ($portalHref !== null): ?>
                                <a href="<?= htmlspecialchars($portalHref, ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-white transition-colors hover:bg-emerald-500">
                                    Ouvrir la page du dossier
                                </a>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2 text-[11px] font-black uppercase tracking-wider text-slate-700 transition-colors hover:bg-white">
                                    Voir les unités
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php
        $myApplicationsAllRoot = is_array($my_applications_all ?? null) ? $my_applications_all : [];
        ?>
        <?php if ($myApplicationsAllRoot !== []): ?>
        <section class="border-b border-slate-200 bg-[#f8fafc]">
            <div class="mx-auto max-w-[100rem] px-4 py-10 sm:px-6 md:px-10">
                <div class="mb-6 px-2 sm:px-0">
                    <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-700">Vue d’ensemble</p>
                    <h3 class="mt-2 text-2xl font-black uppercase tracking-tight text-slate-900 md:text-3xl">Toutes mes candidatures</h3>
                    <p class="mt-3 max-w-3xl text-sm text-slate-600">Le détail complet de vos dossiers déposés, quel que soit leur statut.</p>
                </div>
                <?php require base_path('views/partials/dashboard_applications_table.php'); ?>
            </div>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($currentTid > 1): ?>
            <?php require base_path('views/partials/dashboard_command_center.php'); ?>
        <?php endif; ?>
    </main>
    </div>
    <?php
    $lmsModuleEntryAuto = null;
    require base_path('views/partials/lms_module_entry_modal.php');
    ?>
</body>
</html>
