<?php
$courses = $courses ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-8">Formations</h1>
    <?php if (empty($courses)): ?>
    <p class="text-slate-500">Aucune formation. Créez-en une via l’API ou un import.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Titre</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Slug</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Visibilité</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Catégorie</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $c): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($c['title']) ?></td>
                <td class="p-3"><?= htmlspecialchars($c['slug']) ?></td>
                <td class="p-3">
                    <span class="px-2 py-0.5 text-xs rounded <?= ($c['visibility'] ?? '') === 'published' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' ?>"><?= htmlspecialchars($c['visibility'] ?? '') ?></span>
                </td>
                <td class="p-3"><?= htmlspecialchars($c['category'] ?? '—') ?></td>
                <td class="p-3">
                    <a href="<?= url('formations/' . htmlspecialchars($c['slug'])) ?>" class="text-slate-600 hover:text-slate-900 text-sm underline">Voir</a>
                    <a href="<?= url('admin/training/courses/' . (int) $c['id'] . '/showcase') ?>" class="text-slate-600 hover:text-slate-900 text-sm underline ml-2">Vitrine</a>
                    <a href="<?= url('admin/training/enrollments?course_id=' . (int)$c['id']) ?>" class="text-slate-600 hover:text-slate-900 text-sm underline ml-2">Assignations</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('admin/training') ?>" class="underline">Retour tableau de bord</a></p>
</div>
