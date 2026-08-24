<?php
/** @var bool $onboarding_done */
$onboarding_done = !empty($onboarding_done);
$onboarding_personas = is_array($onboarding_personas ?? null) ? $onboarding_personas : [];
$onboarding_persona = is_string($onboarding_persona ?? null) ? $onboarding_persona : null;
$activePersona = $onboarding_persona !== null ? ($onboarding_personas[$onboarding_persona] ?? null) : null;
$completedSteps = is_array($onboarding_completed_steps ?? null) ? array_map('intval', $onboarding_completed_steps) : [];
$journeyStepCount = is_array($activePersona['steps'] ?? null) ? count($activePersona['steps']) : 0;
$journeyCompletedCount = count(array_intersect(range(0, max(0, $journeyStepCount - 1)), $completedSteps));

$steps = [
    ['label' => 'Bienvenue', 'done' => true, 'active' => false],
    ['label' => 'Votre rôle', 'done' => $activePersona !== null, 'active' => $activePersona === null],
    ['label' => 'Votre parcours', 'done' => $onboarding_done, 'active' => $activePersona !== null && !$onboarding_done],
    ['label' => 'C’est parti', 'done' => $onboarding_done, 'active' => $onboarding_done],
];

$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Accueil</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Premiers pas sur Athena</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                Quelques étapes pour découvrir votre espace et accéder aux modules essentiels de la communauté.
            </p>
            <div class="mt-8">
                <?php require base_path('views/partials/ui/stepper.php'); ?>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-3xl space-y-8 px-4 py-10 sm:px-6 lg:px-8">
        <?php if ($flashSuccess): ?>
            <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= htmlspecialchars((string) $flashSuccess, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($flashError): ?>
            <p class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"><?= htmlspecialchars((string) $flashError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <section aria-labelledby="persona-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Parcours personnalisé</p>
                    <h2 id="persona-heading" class="mt-2 text-xl font-black text-slate-900">Quel est votre objectif principal ?</h2>
                </div>
                <?php if ($activePersona !== null): ?>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Parcours actif : <?= htmlspecialchars((string) $activePersona['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <?php foreach ($onboarding_personas as $key => $persona): ?>
                    <?php $selected = $key === $onboarding_persona; ?>
                    <form method="post" action="<?= htmlspecialchars(url('onboarding/persona'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="persona" value="<?= htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="h-full w-full rounded-2xl border p-5 text-left shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 <?= $selected ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md' ?>" aria-pressed="<?= $selected ? 'true' : 'false' ?>">
                            <span class="text-[11px] font-black uppercase tracking-[0.18em] text-emerald-700"><?= htmlspecialchars((string) $persona['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="mt-2 block text-base font-black text-slate-950"><?= htmlspecialchars((string) $persona['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="mt-2 block text-sm leading-relaxed text-slate-600"><?= htmlspecialchars((string) $persona['description'], ENT_QUOTES, 'UTF-8') ?></span>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($onboarding_done && $activePersona !== null): ?>
            <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">Parcours terminé</h2>
                <p class="mt-2 text-sm text-slate-600">Vous avez validé l’accueil. Vous pouvez revenir à tout moment sur les modules ci-dessous.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Centre de commandement</a>
                    <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Ma fiche</a>
                </div>
            </section>
        <?php elseif ($activePersona !== null): ?>
            <section id="persona-journey" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700"><?= htmlspecialchars((string) $activePersona['eyebrow'], ENT_QUOTES, 'UTF-8') ?></p>
                <h2 class="mt-2 text-lg font-bold text-slate-900">Votre itinéraire recommandé</h2>
                <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars((string) $activePersona['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="mt-5" aria-label="Progression du parcours">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600">
                        <span><?= $journeyCompletedCount ?> étape<?= $journeyCompletedCount > 1 ? 's' : '' ?> sur <?= $journeyStepCount ?></span>
                        <span><?= $journeyStepCount > 0 ? (int) round(($journeyCompletedCount / $journeyStepCount) * 100) : 0 ?> %</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $journeyStepCount ?>" aria-valuenow="<?= $journeyCompletedCount ?>">
                        <div class="h-full rounded-full bg-emerald-600 transition-all" style="width:<?= $journeyStepCount > 0 ? (int) round(($journeyCompletedCount / $journeyStepCount) * 100) : 0 ?>%"></div>
                    </div>
                </div>
                <ol class="mt-6 space-y-4 text-sm text-slate-700">
                    <?php foreach (($activePersona['steps'] ?? []) as $idx => $personaStep): ?>
                        <?php $stepDone = in_array((int) $idx, $completedSteps, true); ?>
                        <li class="flex gap-3 rounded-xl border <?= $stepDone ? 'border-emerald-200 bg-emerald-50/70' : 'border-slate-200 bg-slate-50' ?> p-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full <?= $stepDone ? 'bg-emerald-700 text-white' : 'bg-emerald-100 text-emerald-800' ?> text-xs font-bold"><?= $stepDone ? '✓' : (int) $idx + 1 ?></span>
                            <div class="min-w-0 flex-1">
                                <a class="font-semibold text-emerald-800 underline-offset-2 hover:underline" href="<?= htmlspecialchars((string) $personaStep['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $personaStep['label'], ENT_QUOTES, 'UTF-8') ?></a>
                                <p class="mt-1 text-xs leading-relaxed text-slate-600"><?= htmlspecialchars((string) $personaStep['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <form method="post" action="<?= htmlspecialchars(url('onboarding/step'), ENT_QUOTES, 'UTF-8') ?>" class="mt-3">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="step" value="<?= (int) $idx ?>">
                                    <button type="submit" class="text-xs font-bold <?= $stepDone ? 'text-slate-500 hover:text-slate-800' : 'text-emerald-700 hover:text-emerald-900' ?>">
                                        <?= $stepDone ? 'Marquer comme à reprendre' : 'Marquer comme réalisé' ?>
                                    </button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <form method="post" action="<?= htmlspecialchars(url('onboarding/complete'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" <?= $journeyCompletedCount < $journeyStepCount ? 'disabled aria-disabled="true"' : '' ?> class="inline-flex rounded-xl px-5 py-3 text-sm font-semibold shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 <?= $journeyCompletedCount < $journeyStepCount ? 'cursor-not-allowed bg-slate-300 text-slate-600' : 'bg-emerald-700 text-white hover:bg-emerald-800' ?>">
                        J’ai terminé l’accueil
                    </button>
                    <?php if ($journeyCompletedCount < $journeyStepCount): ?><p class="mt-2 text-xs text-slate-500">Marquez les trois étapes comme réalisées pour terminer l’accueil.</p><?php endif; ?>
                </form>
            </section>
        <?php else: ?>
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-4 text-sm text-slate-600">Choisissez un profil ci-dessus : Athena adaptera les trois premières actions sans masquer les autres modules.</p>
        <?php endif; ?>
    </div>
</div>
