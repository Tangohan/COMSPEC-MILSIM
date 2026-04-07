<div class="min-h-[calc(100vh-8rem)] bg-gradient-to-b from-slate-50 via-white to-emerald-50/30">
    <div class="mx-auto max-w-5xl px-6 py-14 md:py-20">
        <div class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-[0_24px_80px_-32px_rgba(15,23,42,0.18)]">
            <div class="border-b border-slate-100 bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 px-8 py-12 md:px-12 md:py-16">
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-emerald-400/90">Athena</p>
                <h1 class="mt-4 text-3xl font-black italic tracking-tight text-white md:text-4xl lg:text-[2.75rem] lg:leading-tight">
                    Bienvenue
                </h1>
                <p class="mt-5 max-w-xl text-sm leading-relaxed text-slate-300 md:text-base">
                    Portail communautaire : personnel, formations, documents et organisation. Connectez-vous pour accéder à votre espace, ou parcourez les accès publics ci-dessous.
                </p>
                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="<?= url('login') ?>" class="inline-flex items-center justify-center rounded-2xl bg-white px-6 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-slate-900 shadow-lg shadow-black/20 transition hover:bg-emerald-50">
                        Connexion
                    </a>
                    <a href="<?= url('dashboard') ?>" class="inline-flex items-center justify-center rounded-2xl border border-white/25 bg-white/10 px-6 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-white backdrop-blur-sm transition hover:bg-white/15">
                        Tableau de bord
                    </a>
                    <a href="<?= url('enlistment') ?>" class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-6 py-3.5 text-xs font-black uppercase tracking-[0.2em] text-emerald-200/95 transition hover:text-white">
                        Enrôlement
                    </a>
                </div>
            </div>
            <div class="grid gap-0 sm:grid-cols-3 sm:divide-x sm:divide-slate-100">
                <div class="p-6 md:p-8">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Pour les unités</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">Doctrine &amp; ressources</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">Après connexion, les documents et parcours disponibles dépendent de votre communauté et de vos habilitations.</p>
                </div>
                <div class="border-t border-slate-100 p-6 sm:border-t-0 md:p-8">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Accès</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">Compte &amp; communauté</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">Création de compte, invitation par code ou constitution d’une nouvelle communauté depuis le menu de connexion.</p>
                </div>
                <div class="border-t border-slate-100 p-6 sm:border-t-0 md:p-8">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Déjà inscrit ?</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">Rejoindre une communauté</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-600">
                        <a href="<?= url('join') ?>" class="font-semibold text-emerald-700 underline decoration-emerald-200 underline-offset-2 hover:text-emerald-800">Utiliser un code d’invitation</a>
                        après connexion si besoin.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-12">
            <?php
            $label = 'Operational_Notes_v2.0';
            $text = "Les protocoles de transmission doivent être suivis avec une rigueur absolue. Chaque briefing est une pièce maîtresse de l'architecture opérationnelle, permettant une coordination fluide entre les différents vecteurs d'intervention.";
            $ref = 'ATH-01';
            $date = '13.03.2026';
            require base_path('views/partials/editorial-block.php');
            ?>
        </div>
    </div>
</div>
