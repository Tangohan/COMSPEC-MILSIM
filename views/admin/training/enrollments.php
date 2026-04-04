<?php
$enrollments = $enrollments ?? [];
$courses = $courses ?? [];
$selectedCourseId = $selectedCourseId ?? 0;
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6">Assignations</h1>
    <form method="get" action="<?= url('admin/training/enrollments') ?>" class="mb-8 flex flex-wrap items-center gap-4">
        <label class="text-sm font-medium text-slate-700">Formation</label>
        <select name="course_id" class="border border-slate-300 rounded px-3 py-2 text-sm" onchange="this.form.submit()">
            <option value="0">— Choisir —</option>
            <?php foreach ($courses as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $selectedCourseId === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if ($selectedCourseId && empty($enrollments)): ?>
    <p class="text-slate-500">Aucune inscription pour cette formation.</p>
    <?php elseif ($selectedCourseId): ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Utilisateur</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Statut</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Assigné le</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Expire</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($enrollments as $e): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($e['display_name'] ?? $e['email'] ?? '') ?></td>
                <td class="p-3">
                    <span class="px-2 py-0.5 text-xs rounded <?= ($e['status'] ?? '') === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><?= htmlspecialchars($e['status'] ?? '') ?></span>
                </td>
                <td class="p-3"><?= htmlspecialchars($e['assignment_type'] ?? '') ?></td>
                <td class="p-3"><?= !empty($e['assigned_at']) ? date('d/m/Y', strtotime($e['assigned_at'])) : '—' ?></td>
                <td class="p-3"><?= !empty($e['expires_at']) ? date('d/m/Y', strtotime($e['expires_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('admin/training') ?>" class="underline">Retour tableau de bord</a></p>
</div>
