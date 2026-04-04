<?php
$documents = $courrier['documents'] ?? [];
$baseUrl = url('');
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Historique — Bureau Courrier</h1>
    <p class="mb-6"><a href="<?= $baseUrl ?>/courrier" class="text-slate-500 hover:text-slate-900 text-sm">← Bureau Courrier</a></p>
    <?php if (empty($documents)): ?>
    <p class="text-slate-500">Aucun document.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Titre / Référence</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Statut</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Modifié</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documents as $d): ?>
            <tr class="border-b border-slate-100">
                <td class="p-3"><?= htmlspecialchars($d['title'] ?: $d['reference_number'] ?: 'Sans titre') ?></td>
                <td class="p-3"><?= htmlspecialchars($d['status'] ?? '') ?></td>
                <td class="p-3 text-sm text-slate-500"><?= htmlspecialchars($d['updated_at'] ?? '') ?></td>
                <td class="p-3"><a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="text-slate-600 hover:underline text-sm">Lire</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
