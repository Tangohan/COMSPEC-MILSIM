<?php
declare(strict_types=1);
$kpis = $adminKpis ?? [];
$blockError = $adminKpiBlockError ?? null;
$envLabel = $adminPlatformEnvLabel ?? '—';
$envRaw = $adminPlatformEnvRaw ?? '';
$healthUrl = $adminHealthCheckUrl ?? url('api/health');
$gate = \App\Core\Gate::getInstance();
$hasOrgPath = $gate->allows('admin.organization') || $gate->allows('admin.access') || $gate->allows('site.support');
$isPlatformAdmin = $gate->allows('admin.system');
$isSupportHub = $gate->allows('site.support') && !$isPlatformAdmin;

$subnavClass = 'inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 text-xs font-semibold text-slate-600 shadow-sm';
$subnavLink = 'rounded-lg px-3 py-2 transition hover:bg-slate-100 hover:text-slate-900';
?>
<div class="min-h-0 flex-1 bg-slate-50">
    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10 space-y-8 lg:space-y-10">

        <header class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/90 via-white to-slate-50 shadow-sm">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-amber-100/50 via-transparent to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-8 py-7 lg:py-8 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                <div class="min-w-0 flex-1">
                    <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-900/80 mb-3">
                        <span class="h-px w-6 bg-amber-400" aria-hidden="true"></span>
                        <?= $isSupportHub ? 'Pilotage site (assistance)' : 'Administration plateforme' ?>
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Centre opérateur site</h1>
                    <?php if ($isSupportHub): ?>
                    <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                        Vue d’ensemble et suivi pour l’<strong class="font-semibold text-slate-800">équipe assistance</strong> : indicateurs, modération transverse, journaux et synthèses opérationnelles.
                        La modification des réglages système et des habilitations globales reste réservée aux <strong class="font-semibold text-slate-800">administrateurs plateforme</strong>.
                        Pour la gestion d’une <strong class="font-semibold text-slate-800">communauté</strong> (membres, invitations, configuration), utilisez le
                        <a href="<?= url('back-office') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 hover:text-emerald-950">back-office</a>.
                    </p>
                    <?php else: ?>
                    <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                        Outils <strong class="font-semibold text-slate-800">globaux</strong> : tenants, rôles site, paramètres applicatifs, audit transverse, maintenance.
                        La gestion d’une <strong class="font-semibold text-slate-800">communauté</strong> (membres, unités, recrutement) se fait dans le <a href="<?= url('back-office') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 hover:text-emerald-950">back-office</a>, pas ici.
                    </p>
                    <?php endif; ?>
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
                        <?php if ($isPlatformAdmin && $envRaw !== ''): ?>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Réglage interne</dt>
                            <dd class="font-mono text-xs text-slate-700"><?= htmlspecialchars($envRaw, ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <a href="<?= htmlspecialchars($healthUrl, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 inline-flex w-full justify-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-700 hover:border-amber-300 hover:bg-amber-50/50 transition-colors" target="_blank" rel="noopener">Vérifier l’état des services</a>
                </div>
            </div>
        </header>

        <nav class="flex flex-wrap items-center gap-2" aria-label="Sections du tableau de bord">
            <span class="<?= htmlspecialchars($subnavClass, ENT_QUOTES, 'UTF-8') ?>">
                <a href="#hub-annuaire" class="<?= htmlspecialchars($subnavLink, ENT_QUOTES, 'UTF-8') ?>">Annuaire &amp; offres</a>
                <a href="#hub-plateforme" class="<?= htmlspecialchars($subnavLink, ENT_QUOTES, 'UTF-8') ?>">Plateforme</a>
                <a href="#hub-moderation" class="<?= htmlspecialchars($subnavLink, ENT_QUOTES, 'UTF-8') ?>">Modération</a>
                <a href="#hub-assistance" class="<?= htmlspecialchars($subnavLink, ENT_QUOTES, 'UTF-8') ?>">Assistance</a>
            </span>
        </nav>

        <?php if ($isPlatformAdmin): ?>
            <?php require base_path('views/admin/partials/platform_site_directory.php'); ?>
        <?php endif; ?>

        <section id="hub-plateforme" class="scroll-mt-24 space-y-8 lg:space-y-10">
            <section aria-labelledby="scope-split-heading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-amber-200/90 bg-white p-5 shadow-sm">
                    <h2 id="scope-split-heading" class="text-xs font-black uppercase tracking-[0.2em] text-amber-900 mb-2">Ce tableau concerne le site entier</h2>
                    <?php if ($isSupportHub): ?>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Vous consultez les <strong class="font-semibold text-slate-800">indicateurs et journaux</strong> à l’échelle de la plateforme. Les actions de configuration (rôles système, paramètres applicatifs, restrictions réseau, etc.) sont réservées aux administrateurs plateforme.
                    </p>
                    <?php else: ?>
                    <p class="text-sm text-slate-600 leading-relaxed">
                        Rôles <em class="not-italic font-semibold text-slate-800">système</em>, affectations <em class="not-italic font-semibold text-slate-800">site</em>, paramètres transverses, alertes globales, maintenance BDD et audit multi-tenant.
                    </p>
                    <?php endif; ?>
                </div>
                <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50/40 p-5 shadow-sm">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-emerald-900 mb-2">Pour votre communauté</h2>
                    <p class="text-sm text-slate-700 leading-relaxed">
                        Membres, invitations, unités, rôles <em class="not-italic font-semibold">communautaires</em>, événements, modération organisationnelle : utilisez le
                        <a href="<?= url('back-office') ?>" class="font-bold text-emerald-900 underline decoration-emerald-400 hover:text-emerald-950">back-office</a>.
                        Les modules techniques (forum, formations, modpacks…) sont <strong class="font-semibold">liés à la communauté active</strong> — raccourcis en bas de page.
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

            <?php
            $usagePrev = is_array($adminPlatformUsagePreview ?? null) ? $adminPlatformUsagePreview : [];
            $usageErr = isset($usagePrev['error']) ? (string) $usagePrev['error'] : '';
            $snap = is_array($usagePrev['snapshot'] ?? null) ? $usagePrev['snapshot'] : ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
            $cats = is_array($usagePrev['categories'] ?? null) ? $usagePrev['categories'] : [];
            $uk = is_array($usagePrev['kpis'] ?? null) ? $usagePrev['kpis'] : [];
            $ev7 = (int) ($uk['usage_events_in_period'] ?? 0);
            $actors7 = (int) ($uk['usage_distinct_actors_in_period'] ?? 0);
            $newUsers7 = (int) ($uk['users_registered_in_period'] ?? 0);
            ?>
            <section aria-labelledby="usage-preview-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="usage-preview-heading" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Aperçu des 7 derniers jours</h2>
                        <p class="mt-1 text-sm text-slate-600">Signaux issus de l’usage mesuré et des bases métier — même source que la page indicateurs détaillés.</p>
                    </div>
                    <a href="<?= htmlspecialchars(url('admin/analytics?days=7'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800">Voir le détail</a>
                </div>
                <?php if ($usageErr !== ''): ?>
                    <p class="mt-4 text-sm text-amber-800"><?= htmlspecialchars($usageErr, ENT_QUOTES, 'UTF-8') ?></p>
                <?php else: ?>
                    <dl class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Événements d’usage</dt>
                            <dd class="mt-1 text-xl font-black text-slate-900"><?= number_format($ev7, 0, ',', ' ') ?></dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Acteurs distincts</dt>
                            <dd class="mt-1 text-xl font-black text-slate-900"><?= number_format($actors7, 0, ',', ' ') ?></dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Communautés actives (usage)</dt>
                            <dd class="mt-1 text-xl font-black text-slate-900"><?= number_format((int) ($snap['tenants_with_events'] ?? 0), 0, ',', ' ') ?></dd>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/80 px-3 py-3">
                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Nouveaux comptes</dt>
                            <dd class="mt-1 text-xl font-black text-slate-900"><?= number_format($newUsers7, 0, ',', ' ') ?></dd>
                        </div>
                    </dl>
                    <?php if ($cats !== []): ?>
                        <div class="mt-5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Principales catégories d’événements</p>
                            <ul class="mt-2 flex flex-wrap gap-2 text-xs">
                                <?php foreach ($cats as $c): ?>
                                    <?php
                                    $cl = (string) ($c['category'] ?? '');
                                    $cn = (int) ($c['events'] ?? 0);
                                    ?>
                                    <li class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 font-medium text-slate-800">
                                        <span class="text-slate-500"><?= $cl === '' ? '—' : htmlspecialchars($cl, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="ml-1 font-black text-emerald-800"><?= number_format($cn, 0, ',', ' ') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <p class="mt-3 text-xs text-slate-500">Événements sur 24 h (tous tenants) : <strong class="font-semibold text-slate-700"><?= number_format((int) ($snap['events_24h'] ?? 0), 0, ',', ' ') ?></strong></p>
                <?php endif; ?>
            </section>

            <?php if ($isSupportHub): ?>
                <?php require base_path('views/admin/partials/quick_actions_support_hub.php'); ?>
            <?php endif; ?>
            <?php require base_path('views/admin/partials/quick_actions_system.php'); ?>
        </section>

        <section id="hub-moderation" class="scroll-mt-24">
            <?php require base_path('views/admin/partials/moderation_platform_overview.php'); ?>
        </section>

        <section id="hub-assistance" class="scroll-mt-24 space-y-8">
            <?php require base_path('views/admin/partials/recent_activity.php'); ?>
        </section>

        <?php require base_path('views/admin/partials/tenant_session_modules.php'); ?>

    </div>
</div>
