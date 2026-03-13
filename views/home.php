<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-3xl font-black tracking-[0.2em] text-slate-900 mb-4 italic uppercase">Bienvenue sur Athena</h1>
    <p class="text-slate-600 mb-8 font-serif leading-relaxed text-lg">Plateforme RH tactique multi-tenant — Commandement, personnel, formation, documents.</p>
    <div class="flex flex-wrap gap-4">
        <a href="<?= url('login') ?>" class="inline-flex items-center px-5 py-2.5 bg-slate-900 text-white text-sm font-black uppercase tracking-[0.2em] rounded-3xl hover:bg-emerald-600 shadow-sm hover:shadow-2xl hover:-translate-y-0.5 transition-all italic">Connexion</a>
        <a href="<?= url('dashboard') ?>" class="card-elevated inline-flex items-center px-5 py-2.5 bg-white text-slate-700 text-sm font-black uppercase tracking-[0.2em] rounded-3xl border-0 shadow-sm hover:shadow-2xl hover:-translate-y-0.5 transition-all italic">Dashboard</a>
        <a href="<?= url('enlistment') ?>" class="card-elevated inline-flex items-center px-5 py-2.5 bg-white text-slate-700 text-sm font-black uppercase tracking-[0.2em] rounded-3xl border-0 shadow-sm hover:shadow-2xl hover:-translate-y-0.5 transition-all italic">Enrôlement</a>
    </div>

    <?php
    $label = 'Operational_Notes_v2.0';
    $text = "Les protocoles de transmission doivent être suivis avec une rigueur absolue. Chaque briefing est une pièce maîtresse de l'architecture opérationnelle, permettant une coordination fluide entre les différents vecteurs d'intervention.";
    $ref = 'ATH-01';
    $date = '13.03.2026';
    require base_path('views/partials/editorial-block.php');
    ?>
</div>
