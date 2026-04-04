<?php
$courses = $courses ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-8">Rapports & Conformité</h1>
    <p class="text-slate-600 mb-8">Consultez les formations et utilisez l’API <code class="bg-slate-100 px-1 rounded">/api/training/admin/reports/compliance</code> et <code class="bg-slate-100 px-1 rounded">/api/training/admin/reports/course/{id}</code> pour les données détaillées.</p>
    <ul class="space-y-2">
        <?php foreach ($courses as $c): ?>
        <li><a href="<?= url('admin/training/enrollments?course_id=' . (int)$c['id']) ?>" class="text-emerald-600 hover:underline"><?= htmlspecialchars($c['title']) ?></a> — Assignations et progression</li>
        <?php endforeach; ?>
    </ul>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('admin/training') ?>" class="underline">Retour tableau de bord</a></p>
</div>
