<?php
declare(strict_types=1);
use App\Support\CooperationDictionary;

$row = $cooperationCatalogEntry;
$isEdit = $row !== null;
$csrf = \App\Core\Csrf::token();
$action = $formAction ?? '';
$prioChoices = $cooperationPriorityChoices ?? CooperationDictionary::priorityChoices();
$slugVal = $isEdit ? (string) ($row['slug'] ?? '') : '';
$labelVal = $isEdit ? (string) ($row['label'] ?? '') : '';
$descVal = $isEdit ? (string) ($row['description'] ?? '') : '';
$prioVal = $isEdit ? (string) ($row['default_priority'] ?? '') : '';
$sortVal = $isEdit ? (int) ($row['sort_order'] ?? 0) : 0;
$activeVal = $isEdit ? !empty($row['is_active']) : true;
$checkLines = '';
if ($isEdit && !empty($row['checklist_json'])) {
    $raw = $row['checklist_json'];
    if (is_string($raw)) {
        $d = json_decode($raw, true);
        if (is_array($d)) {
            $lines = [];
            foreach ($d as $x) {
                if (is_string($x) && trim($x) !== '') {
                    $lines[] = $x;
                }
            }
            $checkLines = implode("\n", $lines);
        }
    }
}
?>
<div class="max-w-2xl mx-auto px-6 py-12">
    <a href="<?= url('admin/system/cooperation/catalog') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Retour</a>
    <h1 class="mt-4 text-2xl font-black text-slate-900"><?= $isEdit ? 'Modifier le type' : 'Nouveau type (référence site)' ?></h1>
    <p class="mt-2 text-sm text-slate-600">L’identifiant interne sert au suivi technique ; seuls le libellé et le texte d’aide sont visibles des utilisateurs.</p>

    <?php $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mt-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="mt-8 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!$isEdit): ?>
        <div>
            <label for="slug" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Identifiant interne (fixe)</label>
            <input type="text" id="slug" name="slug" required pattern="[a-z0-9_]{1,64}" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="exercice_joint" value="">
            <p class="text-xs text-slate-500 mt-1">Lettres minuscules, chiffres ou tirets bas uniquement.</p>
        </div>
        <?php else: ?>
            <p class="text-xs text-slate-500">Identifiant interne : <span class="font-mono text-slate-700"><?= htmlspecialchars($slugVal, ENT_QUOTES, 'UTF-8') ?></span> (non modifiable)</p>
        <?php endif; ?>
        <div>
            <label for="label" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Libellé affiché</label>
            <input type="text" id="label" name="label" required maxlength="255" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars($labelVal, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="description" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Texte d’aide (facultatif)</label>
            <textarea id="description" name="description" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= htmlspecialchars($descVal, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div>
            <label for="default_priority" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Priorité suggérée par défaut</label>
            <select id="default_priority" name="default_priority" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="">— Aucune —</option>
                <?php foreach ($prioChoices as $k => $lab): ?>
                <option value="<?= htmlspecialchars($k, ENT_QUOTES, 'UTF-8') ?>"<?= $prioVal === $k ? ' selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="sort_order" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Ordre d’affichage</label>
            <input type="number" id="sort_order" name="sort_order" min="0" max="99999" class="w-40 rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= (int) $sortVal ?>">
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" name="is_active" value="1" class="rounded border-slate-300"<?= $activeVal ? ' checked' : '' ?>>
            <label for="is_active" class="text-sm text-slate-700">Proposer ce type dans les listes</label>
        </div>
        <div>
            <label for="checklist_lines" class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1">Points de contrôle suggérés (une ligne = un point)</label>
            <textarea id="checklist_lines" name="checklist_lines" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Ex. : valider le calendrier commun…"><?= htmlspecialchars($checkLines, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
    </form>
</div>
