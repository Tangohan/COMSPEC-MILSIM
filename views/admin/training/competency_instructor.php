<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Vue instructeur</p>
    <h1 class="tc-hero-title mb-4">Validation terrain &amp; observations</h1>
    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
        Console d’encadrement pour les validations humaines, les overrides de score et les observations terrain
        adossées aux tables `trainer_validation_logs` et `user_progress_event_logs`.
    </p>
</header>

<section class="grid gap-4 lg:grid-cols-2">
    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">File d’attente validation</h2>
        <ul class="mt-4 space-y-2 text-sm text-slate-600">
            <li>• Modules CHARLIE terminés en attente de DELTA.</li>
            <li>• Évaluations FIELD avec `requires_validator = 1`.</li>
            <li>• Recyclages critiques à arbitrer.</li>
        </ul>
    </article>
    <article class="tc-panel p-5">
        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Traçabilité instructeur</h2>
        <ul class="mt-4 space-y-2 text-sm text-slate-600">
            <li>• Qui valide / refuse.</li>
            <li>• Évolution score avant/après.</li>
            <li>• Commentaires d’observation terrain.</li>
        </ul>
    </article>
</section>

<section class="tc-panel p-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Navigation</h2>
    <div class="mt-4 flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/commandement'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Vue commandement</a>
        <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/formateur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Espace formateur</a>
        <a href="<?= htmlspecialchars(url('formations/competences'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Vue utilisateur</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
