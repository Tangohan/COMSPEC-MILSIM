<?php
$stats = $stats ?? ['courses' => 0, 'enrollments' => 0, 'completed' => 0, 'expiringCount' => 0];
$expiring = $expiring ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-8">Formations — Tableau de bord</h1>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-3xl font-black text-slate-900"><?= (int) $stats['courses'] ?></p>
            <p class="text-sm text-slate-500 uppercase tracking-wider">Formations</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-3xl font-black text-slate-900"><?= (int) $stats['enrollments'] ?></p>
            <p class="text-sm text-slate-500 uppercase tracking-wider">Inscriptions</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-3xl font-black text-emerald-600"><?= (float) ($stats['completed'] ?? 0) ?> %</p>
            <p class="text-sm text-slate-500 uppercase tracking-wider">Taux complétion</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <p class="text-3xl font-black text-amber-600"><?= (int) ($stats['expiringCount'] ?? 0) ?></p>
            <p class="text-sm text-slate-500 uppercase tracking-wider">Expirant / expirés</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-4 mb-8">
        <a href="<?= url('admin/training/courses') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Formations</a>
        <a href="<?= url('admin/training/enrollments') ?>" class="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-semibold rounded hover:bg-slate-50">Assignations</a>
        <a href="<?= url('admin/training/reports') ?>" class="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-semibold rounded hover:bg-slate-50">Rapports</a>
        <a href="<?= url('admin/training/certificates') ?>" class="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-semibold rounded hover:bg-slate-50">Certificats</a>
        <a href="<?= url('admin/training/audit') ?>" class="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-semibold rounded hover:bg-slate-50">Audit</a>
    </div>
    <?php if (!empty($expiring)): ?>
    <section class="rounded-xl border border-amber-200 bg-amber-50/50 p-5">
        <h2 class="text-lg font-bold text-slate-900 mb-3">Inscriptions expirant ou expirées</h2>
        <ul class="space-y-2">
            <?php foreach (array_slice($expiring, 0, 10) as $e): ?>
            <li class="text-sm text-slate-700"><?= htmlspecialchars($e['course_title'] ?? '') ?> — <?= htmlspecialchars($e['display_name'] ?? $e['email'] ?? '') ?> — <?= date('d/m/Y', strtotime($e['expires_at'] ?? '')) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('admin') ?>" class="underline">Retour administration</a></p>
</div>
