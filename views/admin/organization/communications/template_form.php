<?php
declare(strict_types=1);
$template = $template ?? null;
$kinds = $kinds ?? [];
$isEdit = $template !== null;
$action = $isEdit
    ? url('back-office/communications/templates/' . (int) ($template['id'] ?? 0) . '/update')
    : url('back-office/communications/templates/store');
?>
<div class="max-w-3xl mx-auto px-6 py-10">
    <h1 class="text-2xl font-black text-slate-900 mb-6"><?= $isEdit ? 'Modifier le modèle' : 'Nouveau modèle' ?></h1>
    <p class="text-sm mb-6"><a href="<?= url('back-office/communications/templates') ?>" class="text-blue-700 font-semibold hover:underline">← Modèles</a></p>

    <form method="post" action="<?= htmlspecialchars($action) ?>" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= \App\Core\Csrf::field() ?>
        <div>
            <label for="name" class="block text-sm font-semibold text-slate-800 mb-1">Nom interne</label>
            <input type="text" name="name" id="name" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($template['name'] ?? '')) ?>">
        </div>
        <div>
            <label for="kind" class="block text-sm font-semibold text-slate-800 mb-1">Famille</label>
            <select name="kind" id="kind" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php foreach ($kinds as $k): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= (($template['kind'] ?? '') === $k) ? 'selected' : '' ?>><?= htmlspecialchars(\App\Support\TenantEmailKind::label($k)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="subject" class="block text-sm font-semibold text-slate-800 mb-1">Objet</label>
            <input type="text" name="subject" id="subject" required maxlength="500" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" value="<?= htmlspecialchars((string) ($template['subject'] ?? '')) ?>">
        </div>
        <div>
            <label for="body_html" class="block text-sm font-semibold text-slate-800 mb-1">Corps (HTML)</label>
            <textarea name="body_html" id="body_html" rows="14" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"><?= htmlspecialchars((string) ($template['body_html'] ?? '')) ?></textarea>
        </div>
        <div>
            <label for="body_text" class="block text-sm font-semibold text-slate-800 mb-1">Texte brut (optionnel)</label>
            <textarea name="body_text" id="body_text" rows="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= htmlspecialchars((string) ($template['body_text'] ?? '')) ?></textarea>
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white">Enregistrer</button>
    </form>
</div>
