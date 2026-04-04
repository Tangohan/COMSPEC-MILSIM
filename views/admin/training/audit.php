<?php
$logs = $logs ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-8">Audit Formations</h1>
    <?php if (empty($logs)): ?>
    <p class="text-slate-500">Aucune entrée d’audit.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Date</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Action</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Cible</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">ID</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $l): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 text-slate-500"><?= !empty($l['created_at']) ? date('d/m/Y H:i', strtotime($l['created_at'])) : '—' ?></td>
                <td class="p-3 font-medium"><?= htmlspecialchars($l['action'] ?? '') ?></td>
                <td class="p-3"><?= htmlspecialchars($l['target_type'] ?? '') ?></td>
                <td class="p-3"><?= (int)($l['target_id'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('admin/training') ?>" class="underline">Retour tableau de bord</a></p>
</div>
