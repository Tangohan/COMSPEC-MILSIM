<?php
$c = $courrier ?? [];
$documents = $c['documents'] ?? [];
$baseUrl = url('');
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-sky-700 mb-1"><a href="<?= $baseUrl ?>/courrier" class="hover:underline">Bureau Courrier</a></p>
    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 mb-2">Archives</h1>
    <p class="mb-8 text-sm text-slate-600">Documents archivés où vous avez participé (rédaction, validation ou signature).</p>
    <?php if (empty($documents)): ?>
    <p class="text-slate-500">Aucun document archivé.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Titre / Référence</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Archivé le</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documents as $d): ?>
            <tr class="border-b border-slate-100">
                <td class="p-3"><?= htmlspecialchars($d['title'] ?: $d['reference_number'] ?: 'Sans titre') ?></td>
                <td class="p-3 text-sm text-slate-500"><?= htmlspecialchars($d['archived_at'] ?? '') ?></td>
                <td class="p-3"><a href="<?= $baseUrl ?>/courrier/read/<?= (int)$d['id'] ?>" class="text-slate-600 hover:underline text-sm">Lire</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
