<?php
declare(strict_types=1);

$baseUrl = url('');
$health = $systemHealth ?? [];
$db = $health['database'] ?? [];
$api = $health['api'] ?? [];

$ctx = function_exists('portal_header_context') ? portal_header_context() : [];
$displayName = trim((string) ($ctx['display_name'] ?? ''));
$tenantLabel = trim((string) ($ctx['tenant_label'] ?? ''));
$roleLabel = trim((string) ($ctx['role_label'] ?? ''));

$cards = [
    [
        'href' => url('account/preferences'),
        'title' => 'Préférences',
        'desc' => 'Identité, interface, fuseau, langue et notifications e-mail — avec raccourcis vers les autres réglages.',
        'accent' => 'from-violet-500 to-purple-600',
        'ring' => 'group-hover:ring-violet-500/25',
        'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    ],
    [
        'href' => url('account/image'),
        'title' => 'Photo de compte',
        'desc' => 'Avatar visible dans la navigation, le forum et les listes.',
        'accent' => 'from-sky-500 to-blue-600',
        'ring' => 'group-hover:ring-sky-500/25',
        'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    ],
    [
        'href' => url('account/portrait'),
        'title' => 'Portrait opérateur',
        'desc' => 'Image in-universe pour fiches, ORBAT et briefings.',
        'accent' => 'from-amber-500 to-orange-600',
        'ring' => 'group-hover:ring-amber-500/25',
        'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
    ],
    [
        'href' => url('account/mail'),
        'title' => 'Adresse e-mail',
        'desc' => 'Modifier l’adresse utilisée pour la connexion et les notifications.',
        'accent' => 'from-emerald-500 to-teal-600',
        'ring' => 'group-hover:ring-emerald-500/25',
        'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
    ],
    [
        'href' => url('account/password'),
        'title' => 'Mot de passe',
        'desc' => 'Renouveler votre secret d’accès au terminal.',
        'accent' => 'from-rose-500 to-red-600',
        'ring' => 'group-hover:ring-rose-500/25',
        'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
    ],
    [
        'href' => url('account/recruitment-presets'),
        'title' => 'Profils de candidature',
        'desc' => 'Préréglages pour accélérer vos formulaires d’enrôlement.',
        'accent' => 'from-indigo-500 to-indigo-700',
        'ring' => 'group-hover:ring-indigo-500/25',
        'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    ],
];
?>
<div class="relative min-h-[70vh]">
    <!-- Hero -->
    <section class="relative overflow-hidden border-b border-emerald-900/20 bg-slate-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-1/4 top-0 h-[420px] w-[420px] rounded-full bg-emerald-500/20 blur-[100px]"></div>
            <div class="absolute -right-1/4 bottom-0 h-[380px] w-[380px] rounded-full bg-teal-600/15 blur-[90px]"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(15,23,42,0.3)_0%,rgba(15,23,42,0.95)_100%)]"></div>
            <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, rgb(255 255 255) 1px, transparent 0); background-size: 28px 28px;"></div>
        </div>
        <div class="relative mx-auto max-w-6xl px-4 pb-14 pt-12 sm:px-6 sm:pb-16 sm:pt-16 lg:px-8">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-[11px] font-black uppercase tracking-[0.35em] text-emerald-400/90">Espace personnel</p>
                    <h1 class="mt-3 text-[clamp(1.875rem,4vw,2.75rem)] font-black leading-[1.1] tracking-tight text-white">
                        Votre compte, votre cadre
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
                        Ajustez votre identité sur la plateforme, sécurisez l’accès et préparez vos dossiers d’enrôlement — tout au même endroit, avec la même rigueur qu’en mission.
                    </p>
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <?php if ($displayName !== ''): ?>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-white shadow-sm backdrop-blur-sm">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-teal-600 text-xs font-black text-white" aria-hidden="true"><?= htmlspecialchars(mb_strtoupper(mb_substr($displayName, 0, 1))) ?></span>
                            <?= htmlspecialchars($displayName) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($tenantLabel !== ''): ?>
                        <span class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-950/50 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-emerald-300/95">
                            <?= htmlspecialchars($tenantLabel) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($roleLabel !== ''): ?>
                        <span class="text-xs font-medium text-slate-500"><?= htmlspecialchars($roleLabel) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col gap-3 sm:flex-row lg:flex-col lg:items-end">
                    <a href="<?= url('dashboard') ?>"
                       class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/10 px-5 py-3 text-sm font-bold text-white backdrop-blur-sm transition hover:border-emerald-400/40 hover:bg-emerald-500/20">
                        <svg class="h-4 w-4 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Retour au tableau de bord
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="relative mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
        <!-- Intro strip -->
        <div class="mb-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-black uppercase tracking-wide text-slate-900">Configurer votre profil</h2>
                <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-600">
                    Chaque tuile ouvre un écran dédié. Les changements sont sauvegardés à la validation du formulaire concerné.
                </p>
            </div>
        </div>

        <!-- Cards grid -->
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($cards as $c): ?>
            <a href="<?= htmlspecialchars($c['href']) ?>"
               class="group relative flex flex-col overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm ring-1 ring-slate-900/[0.04] transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-900/[0.06] <?= htmlspecialchars($c['ring']) ?>">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br shadow-md <?= htmlspecialchars($c['accent']) ?>" aria-hidden="true">
                        <?= $c['icon'] ?>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-black text-slate-900"><?= htmlspecialchars($c['title']) ?></h3>
                            <span class="mt-0.5 text-slate-300 transition group-hover:text-emerald-600" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($c['desc']) ?></p>
                    </div>
                </div>
                <span class="pointer-events-none absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-emerald-500/0 via-emerald-500/0 to-teal-500/0 opacity-0 transition group-hover:from-emerald-500/80 group-hover:via-teal-500/60 group-hover:to-emerald-600/80 group-hover:opacity-100"></span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Santé système -->
        <section class="mt-16 overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white shadow-lg shadow-slate-900/[0.04] ring-1 ring-slate-900/[0.03]">
            <div class="border-b border-slate-200/80 bg-slate-900 px-6 py-5 sm:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-emerald-400/95">État des services</h2>
                        <p class="mt-1 text-sm text-slate-400">Vérification rapide de la base et du nœud C2 (ATAK).</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-300">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400"></span>
                        Diagnostic
                    </span>
                </div>
            </div>
            <div class="grid gap-px bg-slate-200/80 sm:grid-cols-2">
                <div class="bg-white p-6 sm:p-8">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl <?= !empty($db['ok']) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                        </span>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Base de données</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900"><?= !empty($db['ok']) ? 'Opérationnelle' : 'Problème' ?></p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars(!empty($db['ok']) ? ($db['message'] ?? '') : ($db['message'] ?? 'Indisponible')) ?></p>
                </div>
                <div class="bg-white p-6 sm:p-8">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl <?= !empty($api['ok']) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' ?>" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">API ATAK (C2)</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900"><?= !empty($api['ok']) ? 'Joignable' : 'À vérifier' ?></p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($api['message'] ?? 'Non vérifiée') ?></p>
                    <?php if (!empty($api['url'])): ?>
                    <p class="mt-2 truncate font-mono text-[11px] text-slate-500" title="<?= htmlspecialchars($api['url']) ?>"><?= htmlspecialchars($api['url']) ?></p>
                    <?php elseif (empty($api['ok']) && ($api['message'] ?? '') !== 'Non vérifiée (base indisponible)'): ?>
                    <p class="mt-4 text-sm text-slate-600">
                        Configurez le <strong class="text-slate-800">node_url</strong> dans
                        <a href="<?= url('admin/atak-config') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-900">Admin → Configuration ATAK</a>.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <p class="mt-12 text-center text-sm text-slate-500">
            Besoin d’aide communautaire ? Passez par le <a href="<?= url('dashboard') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950">tableau de bord</a> ou contactez votre référent.
        </p>
    </div>
</div>
