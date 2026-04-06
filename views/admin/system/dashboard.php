<?php
declare(strict_types=1);
$kpis = $adminKpis ?? [];
$blockError = $adminKpiBlockError ?? null;
$envLabel = $adminPlatformEnvLabel ?? '—';
$envRaw = $adminPlatformEnvRaw ?? '';
$healthUrl = $adminHealthCheckUrl ?? url('api/health');
$gate = \App\Core\Gate::getInstance();
$hasOrgPath = $gate->allows('admin.organization') || $gate->allows('admin.access');
?>
<div class="bg-slate-50 min-h-[calc(100vh-3.5rem)]">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8 lg:space-y-10">

        <header class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-amber-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-8 py-7 lg:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                <div class="min-w-0 flex-1">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-900/80 mb-3">
                        <span class="h-px w-6 bg-amber-400" aria-hidden="true"></span>
                        Administration plateforme
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Centre opérateur site</h1>
                    <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                        Outils <strong class="font-semibold text-slate-800">globaux</strong> : tenants, rôles site, paramètres applicatifs, audit transverse, maintenance.
                        La gestion d’une <strong class="font-semibold text-slate-800">communauté</strong> (membres, unités, recrutement) se fait dans le <a href="<?= url('back-office') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 hover:text-emerald-950">back-office</a>, pas ici.
                    </p>
                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a href="<?= url('dashboard') ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50 transition-colors">
                            <svg class="h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                            Portail
                        </a>
                        <?php if ($hasOrgPath): ?>
                        <a href="<?= url('back-office') ?>" class="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50/90 px-4 py-2 text-sm font-semibold text-emerald-950 shadow-sm hover:bg-emerald-100/80 transition-colors">
                            Back-office communauté
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="shrink-0 w-full lg:w-72 rounded-xl border border-slate-200/80 bg-white/90 backdrop-blur-sm p-4 shadow-sm">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-3">Environnement</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Mode</dt>
                            <dd><span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800"><?= htmlspecialchars($envLabel, ENT_QUOTES, 'UTF-8') ?></span></dd>
                        </div>
                        <?php if ($envRaw !== ''): ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">APP_ENV</dt>
                            <dd class="font-mono text-xs text-slate-700"><?= htmlspecialchars($envRaw, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <a href="<?= htmlspecialchars($healthUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex w-full justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 hover:border-amber-300 hover:bg-amber-50/50 transition-colors" target="_blank" rel="noopener">Santé API (JSON)</a>
                </div>
            </div>
        </header>

        <section aria-labelledby="scope-split-heading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-2xl border border-amber-200/90 bg-white p-5 shadow-sm">
                <h2 id="scope-split-heading" class="text-xs font-black uppercase tracking-[0.2em] text-amber-900 mb-2">Ce tableau concerne le site entier</h2>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Rôles <em class="not-italic font-semibold text-slate-800">système</em>, affectations <em class="not-italic font-semibold text-slate-800">site</em>, paramètres transverses, alertes globales, maintenance BDD et audit multi-tenant.
                </p>
            </div>
            <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50/40 p-5 shadow-sm">
                <h2 class="text-xs font-black uppercase tracking-[0.2em] text-emerald-900 mb-2">Pour votre communauté</h2>
                <p class="text-sm text-slate-700 leading-relaxed">
                    Membres, invitations, unités, rôles <em class="not-italic font-semibold">communautaires</em>, événements, modération organisationnelle : utilisez le
                    <a href="<?= url('back-office') ?>" class="font-bold text-emerald-900 underline decoration-emerald-400 hover:text-emerald-950">back-office</a>.
                    Les modules techniques (forum, LMS, modpacks…) sont aussi <strong class="font-semibold">scopés au tenant</strong> — raccourcis ci-dessous.
                </p>
            </div>
        </section>

        <section aria-labelledby="sys-kpi-heading">
            <div class="mb-4">
                <h2 id="sys-kpi-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Indicateurs plateforme</h2>
                <p class="mt-1 text-sm text-slate-600">Vue agrégée (tous tenants) — cache court côté serveur.</p>
            </div>
            <?php require base_path('views/admin/partials/kpi_row.php'); ?>
        </section>

        <?php require base_path('views/admin/partials/moderation_platform_overview.php'); ?>

        <?php require base_path('views/admin/partials/recent_activity.php'); ?>

        <?php require base_path('views/admin/partials/quick_actions_system.php'); ?>

        <?php require base_path('views/admin/partials/tenant_session_modules.php'); ?>

    </div>
</div>
