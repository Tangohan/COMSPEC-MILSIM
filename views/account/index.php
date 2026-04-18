<?php
declare(strict_types=1);

$accountUser = is_array($accountUser ?? null) ? $accountUser : [];
$accountProfile = is_array($accountProfile ?? null) ? $accountProfile : [];
$accountSnapshot = $accountSnapshot ?? ['email_masked' => '—', 'email_verified' => false, 'last_login_label' => null];
$onboardingSnapshot = is_array($onboardingSnapshot ?? null) ? $onboardingSnapshot : [];
$health = $systemHealth ?? [];
$db = $health['database'] ?? [];
$api = $health['api'] ?? [];

$ctx = function_exists('portal_header_context') ? portal_header_context() : [];
$displayName = trim((string) ($ctx['display_name'] ?? ''));
$tenantLabel = trim((string) ($ctx['tenant_label'] ?? ''));
$roleLabel = trim((string) ($ctx['role_label'] ?? ''));

$prefUrl = url('account/preferences');
$notifUrl = $prefUrl . '#notifications-email';

$fn = trim((string) ($accountProfile['first_name'] ?? ''));
$ln = trim((string) ($accountProfile['last_name'] ?? ''));
$civilLine = $fn !== '' || $ln !== '' ? trim($fn . ' ' . $ln) : '';
$phoneRaw = trim((string) ($accountProfile['phone'] ?? ''));
$callsign = trim((string) ($accountUser['callsign'] ?? ''));
$tz = trim((string) ($accountProfile['timezone'] ?? ''));
$lang = trim((string) ($accountProfile['language'] ?? ''));
$langLabel = match ($lang) {
    'en' => 'English',
    'fr', '' => 'Français',
    default => $lang,
};
$steam = trim((string) ($accountUser['steam_id'] ?? ''));
$slug = trim((string) ($accountUser['profile_slug'] ?? ''));
$onboardingSteps = is_array($onboardingSnapshot['steps'] ?? null) ? $onboardingSnapshot['steps'] : [];
$onboardingCompleted = (int) ($onboardingSnapshot['completed_count'] ?? 0);
$onboardingTotal = (int) ($onboardingSnapshot['total_count'] ?? 0);
$onboardingPercent = (int) ($onboardingSnapshot['percent'] ?? 0);
$onboardingStatus = trim((string) ($onboardingSnapshot['status'] ?? 'à démarrer'));
$onboardingPlan = trim((string) ($onboardingSnapshot['plan'] ?? 'membre'));
$onboardingNudge = trim((string) ($onboardingSnapshot['nudge'] ?? ''));

$canAtakAdmin = function_exists('can') && (can('admin.access') || can('admin.system') || can('admin.organization'));

$sections = [
    [
        'id' => 'civil',
        'kicker' => 'Portail & identité civile',
        'title' => 'Qui vous voyez sur le site',
        'intro' => 'Nom affiché, prénom et nom, téléphone, fuseau, langue, liaison outil cartographique et préférences d’écran — tout se règle dans Préférences.',
        'tiles' => [
            [
                'href' => $prefUrl,
                'title' => 'Préférences du compte',
                'desc' => 'Identité, contact, fuseau horaire, langue, thème, barre latérale et notifications par e-mail.',
                'accent' => 'from-violet-500 to-purple-600',
                'ring' => 'group-hover:ring-violet-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            ],
            [
                'href' => $notifUrl,
                'title' => 'Courriels de la plateforme',
                'desc' => 'Choisir quels messages automatiques vous recevez (rappels, événements, etc.).',
                'accent' => 'from-cyan-500 to-sky-600',
                'ring' => 'group-hover:ring-cyan-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
        ],
    ],
    [
        'id' => 'security',
        'kicker' => 'Connexion',
        'title' => 'Sécurité et adresse de connexion',
        'intro' => 'Protégez l’accès à votre compte et gardez une adresse e-mail à jour pour les liens de vérification.',
        'tiles' => [
            [
                'href' => url('account/mail'),
                'title' => 'Adresse e-mail',
                'desc' => 'Modifier l’adresse utilisée pour vous connecter et recevoir les messages du portail.',
                'accent' => 'from-emerald-500 to-teal-600',
                'ring' => 'group-hover:ring-emerald-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'href' => url('account/password'),
                'title' => 'Mot de passe',
                'desc' => 'Changer le mot de passe de votre compte.',
                'accent' => 'from-rose-500 to-red-600',
                'ring' => 'group-hover:ring-rose-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>',
            ],
        ],
    ],
    [
        'id' => 'media',
        'kicker' => 'Images',
        'title' => 'Photo de profil et portrait',
        'intro' => 'Deux visuels distincts : l’avatar du compte (forum, listes) et le portrait opérationnel (fiches, organigramme).',
        'tiles' => [
            [
                'href' => url('account/image'),
                'title' => 'Photo de compte',
                'desc' => 'Avatar visible dans la navigation, le forum et les listes de membres.',
                'accent' => 'from-sky-500 to-blue-600',
                'ring' => 'group-hover:ring-sky-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
            ],
            [
                'href' => url('account/portrait'),
                'title' => 'Portrait opérateur',
                'desc' => 'Image « in-universe » pour fiches, organigramme et briefings.',
                'accent' => 'from-amber-500 to-orange-600',
                'ring' => 'group-hover:ring-amber-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
            ],
        ],
    ],
    [
        'id' => 'dossier',
        'kicker' => 'MILSIM / unité',
        'title' => 'Dossier opérationnel (personnage)',
        'intro' => 'C’est un espace à part : nom de personnage, affectation, clearance, qualifications, etc. Ce n’est pas modifiable depuis cette page — ouvrez votre fiche personnelle.',
        'tiles' => [
            [
                'href' => url('personnel/me/edit'),
                'title' => 'Modifier ma fiche personnelle',
                'desc' => 'Personnage, unité, matricule, clearance, formations liées au rôle-play.',
                'accent' => 'from-indigo-500 to-blue-800',
                'ring' => 'group-hover:ring-indigo-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
            ],
            [
                'href' => url('personnel/me'),
                'title' => 'Voir ma fiche (lecture)',
                'desc' => 'Aperçu du dossier tel qu’affiché aux membres habilités.',
                'accent' => 'from-slate-600 to-slate-800',
                'ring' => 'group-hover:ring-slate-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>',
            ],
            [
                'href' => url('personnel/tutorials'),
                'title' => 'Guides du dossier',
                'desc' => 'Aide pas à pas pour compléter votre fiche et comprendre les champs.',
                'accent' => 'from-teal-500 to-emerald-700',
                'ring' => 'group-hover:ring-teal-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>',
            ],
            [
                'href' => url('personnel/mon-espace-rh'),
                'title' => 'Espace RH et formations',
                'desc' => 'Formations, charte, ancienneté et programmes de préqualification éventuels.',
                'accent' => 'from-emerald-500 to-cyan-700',
                'ring' => 'group-hover:ring-emerald-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-4.231 10.52c1.977 0 3.78-1.01 5.03-2.598a7.5 7.5 0 0 0-10.06 0c1.25 1.588 3.053 2.598 5.03 2.598Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/></svg>',
            ],
        ],
    ],
    [
        'id' => 'recruitment',
        'kicker' => 'Enrôlement',
        'title' => 'Candidatures',
        'intro' => 'Enregistrez des préréglages pour remplir plus vite les formulaires d’enrôlement.',
        'tiles' => [
            [
                'href' => url('account/recruitment-presets'),
                'title' => 'Profils de candidature',
                'desc' => 'Préréglages pour accélérer vos dossiers d’enrôlement.',
                'accent' => 'from-indigo-500 to-indigo-700',
                'ring' => 'group-hover:ring-indigo-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
        ],
    ],
    [
        'id' => 'compliance',
        'kicker' => 'Formations',
        'title' => 'Charte du catalogue pédagogique',
        'intro' => 'Si votre communauté utilise les parcours de formation, une prise de connaissance peut être demandée avant l’accès au catalogue.',
        'tiles' => [
            [
                'href' => url('account/charte-formations'),
                'title' => 'Charte liée aux formations',
                'desc' => 'Lire la charte publiée par votre communauté et confirmer votre prise en compte.',
                'accent' => 'from-emerald-500 to-teal-700',
                'ring' => 'group-hover:ring-emerald-500/25',
                'icon' => '<svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l2.25 2.25L15 9.75m6 2.25a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            ],
        ],
    ],
];
?>
<div class="relative min-h-[70vh]">
    <section class="relative overflow-hidden border-b border-emerald-900/20 bg-slate-950">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-1/4 top-0 h-[420px] w-[420px] rounded-full bg-emerald-500/20 blur-[100px]"></div>
            <div class="absolute -right-1/4 bottom-0 h-[380px] w-[380px] rounded-full bg-teal-600/15 blur-[90px]"></div>
            <div class="absolute inset-0 bg-[linear-gradient(to_bottom,rgba(15,23,42,0.3)_0%,rgba(15,23,42,0.95)_100%)]"></div>
            <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, rgb(255 255 255) 1px, transparent 0); background-size: 28px 28px;"></div>
        </div>
        <div class="relative mx-auto max-w-6xl px-4 pb-12 pt-12 sm:px-6 sm:pb-14 sm:pt-14 lg:px-8">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-[11px] font-black uppercase tracking-[0.35em] text-emerald-400/90">Espace personnel</p>
                    <h1 class="mt-3 text-[clamp(1.875rem,4vw,2.75rem)] font-black leading-[1.1] tracking-tight text-white">
                        Mon compte
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-relaxed text-slate-300 sm:text-lg">
                        Les <strong class="font-semibold text-white">données légales</strong> (identité civile, téléphone) sont isolées des autres réglages du portail. Le <strong class="font-semibold text-white">dossier opérationnel</strong> (personnage, unité, clearance) reste sur <strong class="font-semibold text-emerald-200">votre fiche personnelle</strong>.
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
                        Tableau de bord
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="relative mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <section class="mb-10 overflow-hidden rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 shadow-sm" aria-labelledby="onboarding-heading">
            <div class="px-5 py-5 sm:px-8 sm:py-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-2xl">
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-emerald-700">Onboarding cross-modules</p>
                        <h2 id="onboarding-heading" class="mt-1 text-xl font-black text-slate-900">Progression d’intégration</h2>
                        <p class="mt-2 text-sm text-slate-700">
                            Plan actif : <strong class="text-slate-900"><?= htmlspecialchars($onboardingPlan) ?></strong> — statut :
                            <span class="inline-flex items-center rounded-full bg-slate-900 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-white"><?= htmlspecialchars($onboardingStatus) ?></span>
                        </p>
                        <?php if ($onboardingNudge !== '' && $onboardingNudge !== 'RAS'): ?>
                        <p class="mt-2 inline-flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900">
                            <span aria-hidden="true">🔔</span> <?= htmlspecialchars($onboardingNudge) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-white px-4 py-3 text-right shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Badge de progression</p>
                        <p class="mt-1 text-2xl font-black text-emerald-700"><?= $onboardingCompleted ?>/<?= $onboardingTotal > 0 ? $onboardingTotal : 5 ?></p>
                        <p class="text-xs font-semibold text-slate-600"><?= $onboardingPercent ?>% complété</p>
                    </div>
                </div>
                <?php if ($onboardingSteps !== []): ?>
                <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($onboardingSteps as $step): ?>
                        <?php
                        $isDone = !empty($step['done']);
                        $href = (string) ($step['href'] ?? '#');
                        $critical = !empty($step['critical']);
                        ?>
                        <a href="<?= htmlspecialchars($href) ?>" class="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5 text-sm transition <?= $isDone ? 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50' ?>">
                            <span>
                                <span class="block font-semibold"><?= htmlspecialchars((string) ($step['label'] ?? 'Étape onboarding')) ?></span>
                                <span class="block text-xs <?= $isDone ? 'text-emerald-700' : 'text-slate-500' ?>"><?= htmlspecialchars((string) ($step['module'] ?? 'Module')) ?><?= $critical ? ' · critique' : '' ?></span>
                            </span>
                            <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-xs font-bold <?= $isDone ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' ?>">
                                <?= $isDone ? 'OK' : 'À faire' ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Aperçu -->
        <section class="mb-12 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-lg shadow-slate-900/[0.04] ring-1 ring-slate-900/[0.03]" aria-labelledby="account-overview-heading">
            <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white px-5 py-4 sm:px-8 sm:py-5">
                <h2 id="account-overview-heading" class="text-lg font-black text-slate-900">Aperçu</h2>
                <p class="mt-1 text-sm text-slate-600">Résumé des réglages enregistrés — les modifications se font via les liens ci-dessous ou la carte Préférences.</p>
            </div>
            <div class="grid gap-6 p-5 sm:grid-cols-2 sm:p-8 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Connexion</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">E-mail (aperçu)</p>
                    <p class="mt-1 font-mono text-sm text-slate-800"><?= htmlspecialchars($accountSnapshot['email_masked'] ?? '—') ?></p>
                    <p class="mt-2">
                        <?php if (!empty($accountSnapshot['email_verified'])): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-900">Adresse confirmée</span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-900">En attente de confirmation</span>
                        <?php endif; ?>
                    </p>
                    <a href="<?= url('account/mail') ?>" class="mt-3 inline-block text-xs font-bold text-emerald-800 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950">Changer l’adresse e-mail</a>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Identité sur le portail</p>
                    <dl class="mt-2 space-y-2 text-sm text-slate-800">
                        <div><dt class="text-xs text-slate-500">Nom affiché</dt><dd class="font-medium"><?= htmlspecialchars(trim((string) ($accountUser['display_name'] ?? '')) !== '' ? (string) ($accountUser['display_name'] ?? '') : '—') ?></dd></div>
                        <div><dt class="text-xs text-slate-500">Prénom & nom</dt><dd class="font-medium"><?= htmlspecialchars($civilLine !== '' ? $civilLine : 'Non renseignés — à compléter dans Préférences') ?></dd></div>
                        <div><dt class="text-xs text-slate-500">Téléphone</dt><dd class="font-medium"><?= htmlspecialchars($phoneRaw !== '' ? $phoneRaw : 'Non renseigné') ?></dd></div>
                        <div><dt class="text-xs text-slate-500">Indicatif</dt><dd class="font-medium"><?= htmlspecialchars($callsign !== '' ? $callsign : '—') ?></dd></div>
                    </dl>
                    <a href="<?= htmlspecialchars($prefUrl) ?>" class="mt-3 inline-block text-xs font-bold text-emerald-800 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950">Ouvrir Préférences</a>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/60 p-4 sm:col-span-2 lg:col-span-1">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Réglages annexes</p>
                    <ul class="mt-2 space-y-1.5 text-sm text-slate-700">
                        <li><span class="text-slate-500">Fuseau :</span> <?= htmlspecialchars($tz !== '' ? $tz : 'Europe/Paris (par défaut)') ?></li>
                        <li><span class="text-slate-500">Langue :</span> <?= htmlspecialchars($langLabel) ?></li>
                        <li><span class="text-slate-500">Liaison outil cartographique :</span> <?= $steam !== '' ? 'renseignée' : 'non renseignée' ?></li>
                        <li><span class="text-slate-500">Lien court vers la fiche :</span> <?= $slug !== '' ? 'défini' : 'non défini' ?></li>
                        <li><span class="text-slate-500">Dernière connexion :</span> <?= $accountSnapshot['last_login_label'] !== null ? htmlspecialchars((string) $accountSnapshot['last_login_label']) : '—' ?></li>
                    </ul>
                </div>
            </div>
        </section>

        <nav class="mb-10 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50/80 p-3 text-xs font-bold uppercase tracking-wider text-slate-600" aria-label="Sections du compte">
            <a href="#section-civil" class="rounded-lg bg-white px-3 py-2 text-slate-800 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">Portail</a>
            <a href="#section-security" class="rounded-lg bg-white px-3 py-2 text-slate-800 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">Sécurité</a>
            <a href="#section-media" class="rounded-lg bg-white px-3 py-2 text-slate-800 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">Images</a>
            <a href="#section-dossier" class="rounded-lg bg-white px-3 py-2 text-slate-800 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">Fiche perso.</a>
            <a href="#section-recruitment" class="rounded-lg bg-white px-3 py-2 text-slate-800 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">Candidatures</a>
            <a href="#section-health" class="rounded-lg bg-white px-3 py-2 text-slate-800 shadow-sm ring-1 ring-slate-200 hover:ring-emerald-300">Services</a>
        </nav>

        <?php foreach ($sections as $block): ?>
        <section id="section-<?= htmlspecialchars($block['id']) ?>" class="mb-14 scroll-mt-24">
            <div class="mb-5">
                <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-700/90"><?= htmlspecialchars($block['kicker']) ?></p>
                <h2 class="mt-2 text-xl font-black tracking-tight text-slate-900 sm:text-2xl"><?= htmlspecialchars($block['title']) ?></h2>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($block['intro']) ?></p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($block['tiles'] as $c): ?>
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
        </section>
        <?php endforeach; ?>

        <section id="section-health" class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white shadow-lg shadow-slate-900/[0.04] ring-1 ring-slate-900/[0.03]">
            <div class="border-b border-slate-200/80 bg-slate-900 px-6 py-5 sm:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-emerald-400/95">État des services</h2>
                        <p class="mt-1 text-sm text-slate-400">Indicateur simplifié pour votre unité (données et carte tactique).</p>
                    </div>
                </div>
            </div>
            <div class="grid gap-px bg-slate-200/80 sm:grid-cols-2">
                <div class="bg-white p-6 sm:p-8">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl <?= !empty($db['ok']) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                        </span>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Données du portail</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900"><?= !empty($db['ok']) ? 'Disponibles' : 'Indisponibles' ?></p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($db['message'] ?? '') ?></p>
                </div>
                <div class="bg-white p-6 sm:p-8">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl <?= !empty($api['ok']) ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' ?>" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </span>
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-slate-500">Carte & outils tactiques</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900"><?= !empty($api['ok']) ? 'Joignable' : 'À vérifier' ?></p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600"><?= htmlspecialchars($api['message'] ?? '') ?></p>
                    <?php if (!$canAtakAdmin && empty($api['ok']) && !empty($db['ok'])): ?>
                    <p class="mt-3 text-sm text-slate-600">Si le problème continue, prévenez une personne administratrice de votre communauté.</p>
                    <?php elseif ($canAtakAdmin && empty($api['ok']) && !empty($db['ok'])): ?>
                    <p class="mt-3 text-sm text-slate-600">
                        Vous pouvez vérifier les <a href="<?= url('admin/atak-config') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950">réglages du serveur cartographique</a> réservés aux administrateurs.
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <p class="mt-12 text-center text-sm text-slate-500">
            Une question sur votre unité ? Repassez par le <a href="<?= url('dashboard') ?>" class="font-semibold text-emerald-800 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-950">tableau de bord</a> ou contactez votre référent.
        </p>
    </div>
</div>
