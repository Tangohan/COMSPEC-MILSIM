<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="border-b border-slate-200 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900 text-white">
        <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-300/90">Commandement</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Salle de guerre</h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300 sm:text-base">
                Espace collectif pour briefings, coordination et suivi de situation au sein de la communauté.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-4xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid gap-5 sm:grid-cols-2">
            <a href="<?= htmlspecialchars(url('c2'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <h2 class="text-lg font-bold text-slate-900">Poste de commandement</h2>
                <p class="mt-2 text-sm text-slate-600">Accéder aux modes ATAK, Overwatch et terrain.</p>
            </a>
            <a href="<?= htmlspecialchars(url('tableau-operationnel'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <h2 class="text-lg font-bold text-slate-900">Mur opérationnel</h2>
                <p class="mt-2 text-sm text-slate-600">Permanences et consignes publiées pour l’unité.</p>
            </a>
            <a href="<?= htmlspecialchars(url('manoeuvres'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <h2 class="text-lg font-bold text-slate-900">Manœuvres</h2>
                <p class="mt-2 text-sm text-slate-600">Présences et confirmations aux créneaux.</p>
            </a>
            <a href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md">
                <h2 class="text-lg font-bold text-slate-900">Forum</h2>
                <p class="mt-2 text-sm text-slate-600">Briefings et annonces de la communauté.</p>
            </a>
        </div>
    </div>
</div>
