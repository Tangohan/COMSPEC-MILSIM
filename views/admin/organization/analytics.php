<?php
/** @var int $activeApprox */
/** @var int $dashboardEvents */
/** @var string $since */
?>
<div class="max-w-4xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Analytics (30 jours)</h1>
        <a href="<?= url('admin/organization') ?>" class="text-sm text-slate-600 hover:underline">Retour</a>
    </div>
    <p class="text-slate-600 text-sm mb-6">Depuis <?= htmlspecialchars($since) ?> (UTC serveur).</p>
    <dl class="grid sm:grid-cols-2 gap-4">
        <div class="border border-slate-200 rounded-lg p-4">
            <dt class="text-xs uppercase text-slate-500">Utilisateurs actifs (approx. audit)</dt>
            <dd class="text-3xl font-black text-slate-900"><?= (int) $activeApprox ?></dd>
        </div>
        <div class="border border-slate-200 rounded-lg p-4">
            <dt class="text-xs uppercase text-slate-500">Visites tableau de bord (instrumentation)</dt>
            <dd class="text-3xl font-black text-slate-900"><?= (int) $dashboardEvents ?></dd>
        </div>
    </dl>
</div>
