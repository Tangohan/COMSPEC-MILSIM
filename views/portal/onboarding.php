<?php
/** @var bool $onboarding_done */
$onboarding_done = !empty($onboarding_done);

$steps = [
    ['label' => 'Bienvenue', 'done' => true, 'active' => false],
    ['label' => 'Votre fiche', 'done' => $onboarding_done, 'active' => !$onboarding_done],
    ['label' => 'Premiers modules', 'done' => $onboarding_done, 'active' => false],
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

        <?php if ($onboarding_done): ?>
            <section class="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">Parcours terminé</h2>
                <p class="mt-2 text-sm text-slate-600">Vous avez validé l’accueil. Vous pouvez revenir à tout moment sur les modules ci-dessous.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Centre de commandement</a>
                    <a href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Ma fiche</a>
                </div>
            </section>
        <?php else: ?>
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-lg font-bold text-slate-900">Complétez votre installation</h2>
                <ol class="mt-6 space-y-4 text-sm text-slate-700">
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">1</span>
                        <span><a class="font-semibold text-emerald-800 underline-offset-2 hover:underline" href="<?= htmlspecialchars(url('personnel/me'), ENT_QUOTES, 'UTF-8') ?>">Vérifiez votre fiche personnelle</a> — identité et informations visibles par la communauté.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">2</span>
                        <span><a class="font-semibold text-emerald-800 underline-offset-2 hover:underline" href="<?= htmlspecialchars(url('hub'), ENT_QUOTES, 'UTF-8') ?>">Explorez le centre de commandement</a> — raccourcis vers les modules du jour.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800">3</span>
                        <span><a class="font-semibold text-emerald-800 underline-offset-2 hover:underline" href="<?= htmlspecialchars(url('forum'), ENT_QUOTES, 'UTF-8') ?>">Consultez le forum</a> — briefings et annonces de l’unité.</span>
                    </li>
                </ol>
                <form method="post" action="<?= htmlspecialchars(url('onboarding/complete'), ENT_QUOTES, 'UTF-8') ?>" class="mt-8">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="inline-flex rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                        J’ai terminé l’accueil
                    </button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
