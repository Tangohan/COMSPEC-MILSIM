<?php
$steps = [
    ['label' => 'Préparer', 'done' => false, 'active' => true],
    ['label' => 'Installer', 'done' => false, 'active' => false],
    ['label' => 'Connecter', 'done' => false, 'active' => false],
    ['label' => 'Valider', 'done' => false, 'active' => false],
];
?>
<div class="bg-slate-50 pb-16 sm:pb-24">
    <div class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">ATAK</p>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Première liaison</h1>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                Suivez ces étapes pour mettre en service votre outil de situation tactique au sein de la communauté.
            </p>
            <div class="mt-8">
                <?php require base_path('views/partials/ui/stepper.php'); ?>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-10 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">1. Préparer le matériel</h2>
            <p class="mt-2 text-sm text-slate-600">Vérifiez que votre appareil est à jour et que vous disposez des accès fournis par l’encadrement.</p>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">2. Installer et configurer</h2>
            <p class="mt-2 text-sm text-slate-600">
                Consultez le
                <a href="<?= htmlspecialchars(url('atak/setup'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline-offset-2 hover:underline">guide d’installation</a>
                et le
                <a href="<?= htmlspecialchars(url('atak/tuto'), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-emerald-700 underline-offset-2 hover:underline">tutoriel</a>.
            </p>
        </section>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h2 class="text-lg font-bold text-slate-900">3. Ouvrir la situation</h2>
            <p class="mt-2 text-sm text-slate-600">Une fois prêt, accédez à la vue tactique de votre unité.</p>
            <a href="<?= htmlspecialchars(url('atak'), ENT_QUOTES, 'UTF-8') ?>" class="mt-6 inline-flex rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Ouvrir ATAK</a>
        </section>
    </div>
</div>
