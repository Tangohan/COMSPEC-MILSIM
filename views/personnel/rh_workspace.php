<?php
declare(strict_types=1);
$trainingAllowed = !empty($rhTrainingAllowed);
$charterReady = !empty($rhCharterReady);
$charterAccepted = !empty($rhCharterAccepted);
$seniorityLines = is_array($rhSeniorityLines ?? null) ? $rhSeniorityLines : [];
$testerCommunities = is_array($rhTesterCommunities ?? null) ? $rhTesterCommunities : [];
$rolloutRows = is_array($rhRolloutRows ?? null) ? $rhRolloutRows : [];
$greetingName = trim((string) ($rhGreetingName ?? ''));
$rhWorkspaceCsrf = htmlspecialchars((string) ($rhWorkspaceCsrf ?? ''), ENT_QUOTES, 'UTF-8');

$todoItems = [];
if ($trainingAllowed && $charterReady && !$charterAccepted) {
    $todoItems[] = [
        'label' => 'Prendre connaissance de la charte de participation aux formations et confirmer votre accord.',
        'href' => url('account/charte-formations'),
        'cta' => 'Ouvrir la charte',
    ];
}
?>
<div class="bg-slate-50 pb-20">
    <div class="relative overflow-hidden border-b border-slate-800/80 bg-gradient-to-br from-slate-900 via-violet-950 to-slate-900 text-white">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_25%,rgba(167,139,250,0.15)_0,transparent_45%),radial-gradient(circle_at_85%_10%,rgba(52,211,153,0.12)_0,transparent_40%)]" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_80%,rgba(255,255,255,0.06)_0.5px,transparent_0.6px)] bg-[length:24px_24px] opacity-40" aria-hidden="true"></div>
        <div class="relative mx-auto max-w-6xl px-4 pt-10 pb-12 sm:px-6 sm:pt-14 sm:pb-16 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300/90">Personnel</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Espace RH et formations</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
                <?php if ($greetingName !== ''): ?>
                    Bonjour, <span class="font-semibold text-white"><?= htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8') ?></span> — cet espace regroupe tout ce qui concerne votre parcours de formation, vos engagements formalisés et les informations utiles à votre suivi au sein de la communauté.
                <?php else: ?>
                    Bienvenue — cet espace regroupe tout ce qui concerne votre parcours de formation, vos engagements formalisés et les informations utiles à votre suivi au sein de la communauté.
                <?php endif; ?>
            </p>
            <div class="mt-8 flex flex-wrap gap-3 sm:gap-4">
                <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                    Ma fiche personnelle
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
                <?php if ($trainingAllowed): ?>
                    <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-xl border border-white/25 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-200 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                        Catalogue des formations
                    </a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500/15 px-4 py-2.5 text-sm font-semibold text-emerald-100 backdrop-blur-sm transition hover:bg-emerald-500/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-200 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                    Centre opérationnel
                </a>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-6xl space-y-10 px-4 pt-10 sm:px-6 sm:pt-12 lg:px-8">
        <div class="grid gap-5 sm:grid-cols-2 sm:gap-6 lg:grid-cols-4">
            <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="group flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-md shadow-slate-900/5 transition hover:border-violet-200 hover:shadow-lg">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-800 ring-1 ring-violet-200/80" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="font-bold text-slate-900">Fiche &amp; dossier</p>
                    <p class="mt-1 text-sm text-slate-600">Qualifications, affectations et pièces liées à votre profil.</p>
                    <p class="mt-2 text-xs font-semibold text-violet-700 group-hover:text-violet-900">Ouvrir →</p>
                </div>
            </a>
            <a href="<?= htmlspecialchars(url('personnel/tutorials'), ENT_QUOTES, 'UTF-8') ?>" class="group flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-md shadow-slate-900/5 transition hover:border-emerald-200 hover:shadow-lg">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A9.014 9.014 0 0112 15c2.685 0 5.198-.867 7-2.33V21c0 .552.448 1 1 1h3c.552 0 1-.448 1-1v-4.674c.002-.008.002-.016.002-.025M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="font-bold text-slate-900">Tutoriels du portail</p>
                    <p class="mt-1 text-sm text-slate-600">Prise en main des outils et bonnes pratiques pas à pas.</p>
                    <p class="mt-2 text-xs font-semibold text-emerald-700 group-hover:text-emerald-900">Consulter →</p>
                </div>
            </a>
            <a href="<?= htmlspecialchars(url('account'), ENT_QUOTES, 'UTF-8') ?>" class="group flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-md shadow-slate-900/5 transition hover:border-sky-200 hover:shadow-lg">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-800 ring-1 ring-sky-200/80" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.542-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="font-bold text-slate-900">Mon compte</p>
                    <p class="mt-1 text-sm text-slate-600">Sécurité, préférences et informations de contact.</p>
                    <p class="mt-2 text-xs font-semibold text-sky-700 group-hover:text-sky-900">Paramètres →</p>
                </div>
            </a>
            <a href="<?= htmlspecialchars(url('orbat'), ENT_QUOTES, 'UTF-8') ?>" class="group flex gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-md shadow-slate-900/5 transition hover:border-amber-200 hover:shadow-lg">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-900 ring-1 ring-amber-200/80" aria-hidden="true">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008H17.25v-.008zm0 3h.008v.008H17.25V18zm0 3h.008v.008H17.25v-.008z"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="font-bold text-slate-900">Organisation</p>
                    <p class="mt-1 text-sm text-slate-600">Vue d’ensemble des unités et de votre place dans l’effectif.</p>
                    <p class="mt-2 text-xs font-semibold text-amber-800 group-hover:text-amber-950">Voir l’organigramme →</p>
                </div>
            </a>
        </div>

        <?php if ($todoItems !== []): ?>
            <section class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-orange-50/80 p-6 sm:p-8 shadow-sm" aria-labelledby="rh-todo-heading">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="rh-todo-heading" class="text-lg font-bold text-amber-950">Pistes utiles pour la suite</h2>
                        <p class="mt-1 text-sm text-amber-900/80">Quelques actions courantes pour avancer sereinement sur le portail.</p>
                    </div>
                </div>
                <ol class="mt-5 space-y-3">
                    <?php foreach ($todoItems as $i => $item): ?>
                        <li class="flex flex-col gap-2 rounded-xl border border-amber-100/90 bg-white/90 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-200/80 text-sm font-bold text-amber-950"><?= $i + 1 ?></span>
                                <p class="text-sm font-medium text-slate-800 leading-relaxed"><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <a href="<?= htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8') ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2 sm:ml-4">
                                <?= htmlspecialchars((string) $item['cta'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <div class="flex flex-wrap items-center gap-x-2 gap-y-2 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-700 shadow-sm sm:px-6 sm:py-5">
            <span class="font-semibold text-slate-800">Raccourcis :</span>
            <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-2 py-1 font-medium text-emerald-700 hover:bg-emerald-50">Centre opérationnel</a>
            <span class="text-slate-300" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars(url('activite'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-2 py-1 font-medium text-emerald-700 hover:bg-emerald-50">Mon activité</a>
            <span class="text-slate-300" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars(url('communities'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-2 py-1 font-medium text-emerald-700 hover:bg-emerald-50">Annuaire des communautés</a>
            <span class="text-slate-300" aria-hidden="true">·</span>
            <a href="<?= htmlspecialchars(url('evenements'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg px-2 py-1 font-medium text-emerald-700 hover:bg-emerald-50">Événements</a>
        </div>

        <div class="grid gap-8 lg:grid-cols-2 lg:gap-x-10 lg:gap-y-10">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm lg:col-span-2">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-800 ring-1 ring-violet-200/80" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Formations</h2>
                            <p class="mt-1 text-sm text-slate-600 leading-relaxed">Catalogue, inscriptions et suivi de vos parcours pédagogiques.</p>
                        </div>
                    </div>
                </div>
                <?php if ($trainingAllowed): ?>
                    <div class="mt-8 flex flex-wrap gap-3 sm:gap-4">
                        <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">Découvrir le catalogue</a>
                        <a href="<?= htmlspecialchars(url('formations/mes-formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2">Mes parcours en cours</a>
                    </div>
                <?php else: ?>
                    <div class="mt-8 rounded-xl border border-slate-100 bg-slate-50/90 p-5 sm:p-6">
                        <p class="text-sm text-slate-700 leading-relaxed">Les formations ne font pas partie des services activés pour votre communauté dans l’offre actuelle. Pour en savoir plus sur les possibilités d’accès, adressez-vous à l’encadrement ou à l’administration de votre organisation.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
                <div class="flex gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200/80" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-900">Charte de participation</h2>
                        <p class="mt-1 text-sm text-slate-600 leading-relaxed">Document d’engagement commun lorsque les formations sont proposées sur la plateforme.</p>
                    </div>
                </div>
                <div class="mt-8">
                    <?php if (!$trainingAllowed): ?>
                        <p class="text-sm text-slate-600 leading-relaxed">Sans accès aux formations, cette charte ne vous est pas demandée ici.</p>
                    <?php elseif (!$charterReady): ?>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-sm text-slate-700 leading-relaxed">Votre organisation finalise encore la configuration : la consultation et la confirmation seront proposées ici dès que le document sera publié.</p>
                        </div>
                    <?php else: ?>
                        <div class="rounded-xl border <?= $charterAccepted ? 'border-emerald-100 bg-emerald-50/50' : 'border-amber-100 bg-amber-50/60' ?> p-4">
                            <p class="text-sm font-medium text-slate-800">
                                <?= $charterAccepted
                                    ? 'Votre dernière prise en compte est enregistrée. Vous pouvez relire le texte à tout moment.'
                                    : 'Une lecture attentive puis une confirmation sont nécessaires avant de poursuivre certains parcours.' ?>
                            </p>
                        </div>
                        <div class="mt-6">
                            <a href="<?= htmlspecialchars(url('account/charte-formations'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                                <?= $charterAccepted ? 'Relire la charte' : 'Lire et confirmer la charte' ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm">
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex gap-4 min-w-0">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-800 ring-1 ring-indigo-200/80" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-slate-900">Ancienneté et parcours</h2>
                            <p class="mt-1 text-sm text-slate-600 leading-relaxed">Indicateurs liés à votre présence et à votre historique au sein de la communauté.</p>
                        </div>
                    </div>
                    <div class="shrink-0 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 sm:max-w-sm">
                        <p class="text-xs text-slate-600 leading-relaxed">Utile après un changement d’affectation ou de rôle enregistré par l’encadrement.</p>
                        <form method="post" action="<?= htmlspecialchars(url('personnel/mon-espace-rh/actualiser'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3">
                            <input type="hidden" name="_csrf_token" value="<?= $rhWorkspaceCsrf ?>">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 sm:w-auto">
                                Actualiser depuis mon dossier
                            </button>
                        </form>
                    </div>
                </div>
                <?php if ($seniorityLines === []): ?>
                    <div class="mt-8 rounded-xl border border-dashed border-slate-200 bg-slate-50/80 p-6 text-center sm:p-8">
                        <p class="text-sm text-slate-600 leading-relaxed">Aucun indicateur n’est affiché pour l’instant. Cela peut correspondre aux réglages de votre organisation ou à une mise à jour des données en cours.</p>
                        <p class="mt-6 text-sm">
                            <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-2 hover:text-indigo-900">Consulter ma fiche pour le détail du dossier</a>
                        </p>
                    </div>
                <?php else: ?>
                    <ul class="mt-8 divide-y divide-slate-100 rounded-xl border border-slate-100">
                        <?php foreach ($seniorityLines as $line): ?>
                            <li class="flex justify-between gap-4 px-4 py-3 text-sm">
                                <span class="font-medium text-slate-800"><?= htmlspecialchars((string) ($line['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="text-slate-600 tabular-nums"><?= htmlspecialchars((string) ($line['formatted'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="mt-6 text-sm">
                        <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-indigo-700 underline decoration-indigo-200 underline-offset-2 hover:text-indigo-900">Ouvrir ma fiche personnelle</a>
                    </p>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm lg:col-span-2">
                <div class="flex gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-800 ring-1 ring-teal-200/80" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-slate-900">Programmes de préqualification</h2>
                        <p class="mt-1 text-sm text-slate-600 leading-relaxed">Participations ponctuelles proposées par la plateforme ou votre encadrement pour découvrir des évolutions en avant-première.</p>
                    </div>
                </div>
                <?php if ($testerCommunities === []): ?>
                    <div class="mt-8 rounded-xl border border-slate-100 bg-slate-50 p-5 sm:p-6">
                        <p class="text-sm text-slate-700 leading-relaxed">Vous n’êtes rattaché à aucun programme de ce type pour le moment. Si une participation vous est proposée, vous en serez informé par les canaux habituels de votre communauté.</p>
                    </div>
                <?php else: ?>
                    <ul class="mt-8 grid gap-5 sm:grid-cols-2">
                        <?php foreach ($testerCommunities as $tc): ?>
                            <li class="rounded-xl border border-teal-100 bg-teal-50/40 p-4">
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($tc['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php $desc = trim((string) ($tc['description'] ?? '')); ?>
                                <?php if ($desc !== ''): ?>
                                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php
                                $vf = $tc['valid_from'] ?? null;
                                $vu = $tc['valid_until'] ?? null;
                                if (($vf !== null && $vf !== '') || ($vu !== null && $vu !== '')):
                                ?>
                                    <p class="mt-3 text-xs text-slate-500">
                                        Période d’inclusion
                                        <?php if ($vf !== null && $vf !== ''): ?> du <?= htmlspecialchars((string) $vf, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        <?php if ($vu !== null && $vu !== ''): ?> au <?= htmlspecialchars((string) $vu, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <?php if ($rolloutRows !== []): ?>
                <section class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 shadow-sm lg:col-span-2">
                    <div class="flex gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-800 ring-1 ring-rose-200/80" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-bold text-slate-900">Évolutions liées à vos programmes</h2>
                            <p class="mt-1 text-sm text-slate-600 leading-relaxed">Selon les règles définies par l’organisation, certaines fonctionnalités peuvent vous être proposées en avant-première ou faire l’objet de limitations temporaires.</p>
                        </div>
                    </div>
                    <ul class="mt-8 grid gap-5 md:grid-cols-2">
                        <?php foreach ($rolloutRows as $rr): ?>
                            <li class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($rr['module_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-amber-800"><?= htmlspecialchars((string) ($rr['rule_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php $md = $rr['module_description'] ?? null; ?>
                                <?php if (is_string($md) && trim($md) !== ''): ?>
                                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars(trim($md), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php $ev = $rr['evaluation_version'] ?? null; ?>
                                <?php if (is_string($ev) && $ev !== ''): ?>
                                    <p class="mt-3 text-sm text-slate-700">
                                        Référence de l’évaluation en cours : <span class="font-semibold text-slate-900"><?= htmlspecialchars($ev, ENT_QUOTES, 'UTF-8') ?></span>
                                    </p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>
</div>
