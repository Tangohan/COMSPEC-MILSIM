<?php
/**
 * Assistant création d’entraînement (wizard).
 * @var list<array{label: string, done?: bool, active?: bool}> $steps
 */
$steps = $steps ?? [
    ['label' => 'Scénario', 'active' => true],
    ['label' => 'Modules'],
    ['label' => 'Évaluation'],
    ['label' => 'Publier'],
];
$wizardStep = (int) ($wizardStep ?? 1);
?>
<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
    <p class="text-[10px] font-black uppercase tracking-[0.28em] text-emerald-700">Formation</p>
    <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Créer un entraînement</h1>
    <p class="mt-3 text-sm text-slate-600">Quatre étapes pour publier un parcours dans le catalogue de votre communauté.</p>
    <div class="mt-8">
        <?php require base_path('views/partials/ui/stepper.php'); ?>
    </div>
    <div class="mt-10 space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <?php if ($wizardStep <= 1): ?>
            <h2 class="text-lg font-bold text-slate-900">1. Scénario</h2>
            <p class="text-sm text-slate-600">Donnez un titre clair et un objectif pédagogique en une phrase.</p>
            <label class="block text-sm font-semibold text-slate-800">Titre du parcours
                <input type="text" name="title" class="ds-input mt-2" placeholder="Ex. Briefing radio et procédures d’urgence" maxlength="160">
            </label>
            <label class="mt-4 block text-sm font-semibold text-slate-800">Objectif
                <textarea name="objective" rows="3" class="ds-input mt-2" placeholder="À l’issue, l’opérateur doit être capable de…"></textarea>
            </label>
        <?php elseif ($wizardStep === 2): ?>
            <h2 class="text-lg font-bold text-slate-900">2. Modules</h2>
            <p class="text-sm text-slate-600">Ajoutez les leçons dans l’ordre de lecture souhaité (éditable ensuite dans le studio).</p>
        <?php elseif ($wizardStep === 3): ?>
            <h2 class="text-lg font-bold text-slate-900">3. Évaluation</h2>
            <p class="text-sm text-slate-600">Quiz optionnel ou validation instructeur.</p>
        <?php else: ?>
            <h2 class="text-lg font-bold text-slate-900">4. Publier</h2>
            <p class="text-sm text-slate-600">Vérifiez le résumé puis publiez dans le catalogue communauté.</p>
        <?php endif; ?>
        <div class="flex flex-wrap gap-3 pt-4">
            <a href="<?= htmlspecialchars(url('formation/studio'), ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--primary">Continuer dans le studio</a>
            <a href="<?= htmlspecialchars(url('formations'), ENT_QUOTES, 'UTF-8') ?>" class="ds-btn ds-btn--ghost">Annuler</a>
        </div>
    </div>
</div>
