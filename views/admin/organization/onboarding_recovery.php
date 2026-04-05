<?php
/** @var array{gaps: list<string>, needs_recovery: bool} $health */
$gaps = $health['gaps'] ?? [];
$needs = !empty($health['needs_recovery']);
?>
<div class="mx-auto max-w-3xl px-4 py-10">
    <h1 class="text-2xl font-black tracking-tight text-slate-900">Rattrapage configuration communauté</h1>
    <p class="mt-2 text-sm leading-6 text-slate-600">
        Cet écran liste les écarts détectés par rapport à l’assistant de création complet (version 2). Vous pouvez appliquer un modèle minimal français et un ORBAT d’exemple <strong>sans supprimer</strong> les rôles ou unités déjà présents.
    </p>

    <?php if (!$needs): ?>
    <div class="mt-8 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-900">
        Aucun écart bloquant détecté selon les critères actuels.
    </div>
    <?php else: ?>
    <ul class="mt-8 list-inside list-disc space-y-2 text-sm text-slate-800">
        <?php foreach ($gaps as $g): ?>
        <li><?= htmlspecialchars((string) $g, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>

    <form method="post" action="<?= url('back-office/onboarding-recovery/apply') ?>" class="mt-8">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-600">
            Appliquer le modèle FR + ORBAT minimal (si besoin)
        </button>
    </form>
    <?php endif; ?>

    <p class="mt-8 text-xs text-slate-500">
        Complétez aussi les écrans <a href="<?= url('back-office/community') ?>" class="text-emerald-700 underline">Identité communauté</a> et ORBAT si nécessaire.
    </p>
</div>
