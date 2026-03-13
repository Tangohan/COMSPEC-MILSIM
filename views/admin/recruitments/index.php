<?php
$enlistments = $enlistments ?? [];
$statusFilter = $statusFilter ?? null;
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Candidatures</h1>
        <div class="flex items-center gap-2">
            <a href="<?= url('enlistment') ?>" class="text-slate-600 hover:text-slate-900 text-sm">Formulaire public</a>
            <span class="text-slate-300">|</span>
            <a href="<?= url('admin') ?>" class="text-slate-600 hover:text-slate-900 text-sm">Administration</a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium text-slate-600">Filtrer :</span>
        <a href="<?= url('admin/recruitments') ?>" class="px-3 py-1 rounded text-sm <?= $statusFilter === null ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Toutes</a>
        <a href="<?= url('admin/recruitments') ?>?status=submitted" class="px-3 py-1 rounded text-sm <?= $statusFilter === 'submitted' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Soumis</a>
        <a href="<?= url('admin/recruitments') ?>?status=reviewed" class="px-3 py-1 rounded text-sm <?= $statusFilter === 'reviewed' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Traitées</a>
        <a href="<?= url('admin/recruitments') ?>?status=rejected" class="px-3 py-1 rounded text-sm <?= $statusFilter === 'rejected' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">Rejetées</a>
    </div>

    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <?php if (empty($enlistments)): ?>
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-8 text-center">
        <p class="text-slate-500">Aucune candidature<?= $statusFilter ? ' pour ce filtre' : '' ?>.</p>
        <p class="mt-2 text-sm text-slate-400">Les candidatures sont envoyées depuis la page <a href="<?= url('enlistment') ?>" class="underline">Enrôlement</a>.</p>
    </div>
    <?php else: ?>
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Date</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Email</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Indicatif</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enlistments as $e): ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="p-3 text-sm text-slate-600"><?= $e['created_at'] ? date('d/m/Y H:i', strtotime($e['created_at'])) : '—' ?></td>
                    <td class="p-3 font-medium"><?= htmlspecialchars(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: '—') ?></td>
                    <td class="p-3"><?= htmlspecialchars($e['email'] ?? '—') ?></td>
                    <td class="p-3"><?= htmlspecialchars($e['callsign'] ?? '—') ?></td>
                    <td class="p-3">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                            <?= ($e['status'] ?? '') === 'submitted' ? 'bg-amber-100 text-amber-800' : (($e['status'] ?? '') === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700') ?>">
                            <?= htmlspecialchars($e['status'] ?? '—') ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <p class="mt-8 text-sm text-slate-500">
        <a href="<?= url('admin') ?>" class="underline">Retour administration</a>
    </p>
</div>
