<?php require base_path('views/admin/training/partials/command_shell_open.php'); ?>
<header class="tc-panel p-6 md:p-8">
    <p class="tc-kicker">Vue commandement</p>
    <h1 class="tc-hero-title mb-4">Carte de compétences &amp; readiness</h1>
    <p class="text-slate-600 text-sm max-w-3xl leading-relaxed">
        Page de pilotage dédiée au modèle ALPHA/BRAVO/CHARLIE/DELTA : suivi de la préparation opérationnelle,
        certifications à risque et priorités de recyclage par tenant.
    </p>
</header>

<section class="grid gap-4 md:grid-cols-3">
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Readiness unité</p>
        <p class="mt-2 text-3xl font-black text-emerald-600">-- %</p>
        <p class="mt-2 text-xs text-slate-500">Connectez l’agrégat sur `user_progress` + `module_competencies`.</p>
    </article>
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Modules expirés</p>
        <p class="mt-2 text-3xl font-black text-amber-600">--</p>
        <p class="mt-2 text-xs text-slate-500">Basé sur `status = EXPIRED` et `expires_at`.</p>
    </article>
    <article class="tc-panel p-5">
        <p class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Validations DELTA</p>
        <p class="mt-2 text-3xl font-black text-slate-900">--</p>
        <p class="mt-2 text-xs text-slate-500">Prévoir extraction via `trainer_validation_logs`.</p>
    </article>
</section>

<section class="tc-panel p-6">
    <h2 class="text-sm font-black uppercase tracking-[0.2em] text-slate-900">Actions rapides</h2>
    <div class="mt-4 flex flex-wrap gap-3">
        <a href="<?= htmlspecialchars(url('back-office/ressources/training/competences/instructeur'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-emerald">Ouvrir la vue instructeur</a>
        <a href="<?= htmlspecialchars(url('back-office/ressources/training/audit'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Journal LMS existant</a>
        <a href="<?= htmlspecialchars(url('back-office/ressources/training'), ENT_QUOTES, 'UTF-8') ?>" class="tc-btn-primary tc-btn-ghost">Retour formations</a>
    </div>
</section>
<?php require base_path('views/admin/training/partials/command_shell_close.php'); ?>
