<?php
$grade = $grade ?? null;
$systems = $systems ?? [];
$categories = $categories ?? [];
$isEdit = $grade !== null;
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <h1 class="text-2xl font-black text-slate-900 mb-6"><?= $isEdit ? 'Modifier le grade' : 'Nouveau grade' ?></h1>
    <?php if ($flashError): ?>
    <p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= $isEdit ? url('admin/organization/referentiels/grades/' . (int)$grade['id'] . '/update') : url('admin/organization/referentiels/grades/store') ?>" class="space-y-4">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="grade_system_id" class="block text-sm font-medium text-slate-700">Système de grade</label>
            <select id="grade_system_id" name="grade_system_id" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">— Choisir —</option>
                <?php foreach ($systems as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= ($grade['grade_system_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['label']) ?> (<?= htmlspecialchars($s['code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="grade_category_id" class="block text-sm font-medium text-slate-700">Catégorie</label>
            <select id="grade_category_id" name="grade_category_id" required class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
                <option value="">— Choisir —</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= ($grade['grade_category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['label']) ?> (<?= htmlspecialchars($c['code']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
            <input type="text" id="code" name="code" value="<?= htmlspecialchars($grade['code'] ?? '') ?>" required maxlength="50" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" placeholder="ex. CNE">
        </div>
        <div>
            <label for="label_short" class="block text-sm font-medium text-slate-700">Libellé court</label>
            <input type="text" id="label_short" name="label_short" value="<?= htmlspecialchars($grade['label_short'] ?? '') ?>" required maxlength="100" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" placeholder="ex. CNE">
        </div>
        <div>
            <label for="label_long" class="block text-sm font-medium text-slate-700">Libellé long</label>
            <input type="text" id="label_long" name="label_long" value="<?= htmlspecialchars($grade['label_long'] ?? '') ?>" required maxlength="150" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" placeholder="ex. Capitaine">
        </div>
        <div>
            <label for="label_otan" class="block text-sm font-medium text-slate-700">Code OTAN</label>
            <input type="text" id="label_otan" name="label_otan" value="<?= htmlspecialchars($grade['label_otan'] ?? '') ?>" maxlength="50" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm" placeholder="ex. OF-2">
        </div>
        <div>
            <label for="sort_order" class="block text-sm font-medium text-slate-700">Ordre hiérarchique</label>
            <input type="number" id="sort_order" name="sort_order" value="<?= (int) ($grade['sort_order'] ?? 0) ?>" min="0" class="mt-1 block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm">
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_commissioned" name="is_commissioned" value="1" <?= !empty($grade['is_commissioned']) ? 'checked' : '' ?> class="rounded border-slate-300">
            <label for="is_commissioned" class="text-sm text-slate-700">Officier commissionné</label>
        </div>
        <?php if ($isEdit): ?>
        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($grade['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300">
            <label for="is_active" class="text-sm text-slate-700">Actif</label>
        </div>
        <?php endif; ?>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
            <a href="<?= url('admin/organization/referentiels/grades') ?>" class="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-medium rounded hover:bg-slate-50">Annuler</a>
        </div>
    </form>
    <p class="mt-6 text-sm text-slate-500"><a href="<?= url('admin/organization') ?>" class="underline">Retour administration organisationnelle</a></p>
</div>
