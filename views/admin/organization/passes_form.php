<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $pass */
/** @var list<array<string, mixed>> $passConditions */
/** @var list<string> $passPreviews */
/** @var list<array{type: string, label: string, preview: string}> $passCatalog */
/** @var list<array{value: string, label: string}> $passAvisKinds */
/** @var list<array{value: string, label: string}> $passBilanKinds */
/** @var list<array<string, mixed>> $passCourses */
/** @var list<array{id: int, label: string}> $passQualifications */
/** @var list<array{id: int, label: string}> $passGrades */
/** @var list<string> $passHourCategories */
/** @var bool $passSchemaReady */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$pass = is_array($pass ?? null) ? $pass : null;
$isEdit = $pass !== null;
$conditions = is_array($passConditions ?? null) ? $passConditions : [];
$previews = is_array($passPreviews ?? null) ? $passPreviews : [];
$catalog = is_array($passCatalog ?? null) ? $passCatalog : [];
$avisKinds = is_array($passAvisKinds ?? null) ? $passAvisKinds : [];
$bilanKinds = is_array($passBilanKinds ?? null) ? $passBilanKinds : [];
$courses = is_array($passCourses ?? null) ? $passCourses : [];
$quals = is_array($passQualifications ?? null) ? $passQualifications : [];
$grades = is_array($passGrades ?? null) ? $passGrades : [];
$cats = is_array($passHourCategories ?? null) ? $passHourCategories : [];
$ready = !empty($passSchemaReady);

$val = static function (string $key, mixed $default = '') use ($pass): string {
    if ($pass === null) {
        return (string) $default;
    }

    return (string) ($pass[$key] ?? $default);
};
$checked = static function (string $key, bool $default = true) use ($pass): string {
    if ($pass === null) {
        return $default ? ' checked' : '';
    }

    return !empty($pass[$key]) ? ' checked' : '';
};

$formAction = $isEdit
    ? url('back-office/organisation/passes/' . (int) $pass['id'] . '/update')
    : url('back-office/organisation/passes/store');

$flashError = \App\Core\Session::getFlash('error');
$flashSuccess = \App\Core\Session::getFlash('success');

if ($conditions === []) {
    $conditions = [['condition_type' => '']];
    $previews = [''];
}
?>
<div class="max-w-4xl mx-auto space-y-6 px-4 py-6" data-pass-form>
    <header class="space-y-1">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Back-office · PASS</p>
        <h1 class="text-2xl font-black text-slate-900"><?= $isEdit ? 'Modifier le PASS' : 'Nouveau PASS' ?></h1>
        <a href="<?= $h(url('back-office/organisation/passes')) ?>" class="text-sm font-semibold text-emerald-800 hover:underline">← Liste des PASS</a>
    </header>

    <?php if ($flashError): ?>
    <p class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert"><?= $h((string) $flashError) ?></p>
    <?php endif; ?>
    <?php if ($flashSuccess): ?>
    <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status"><?= $h((string) $flashSuccess) ?></p>
    <?php endif; ?>

    <?php if (!$ready): ?>
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Schéma PASS indisponible.</div>
    <?php else: ?>
    <form method="post" action="<?= $h($formAction) ?>" class="space-y-5">
        <?= \App\Core\Csrf::field() ?>

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Identité</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Nom *</span>
                    <input name="label" required maxlength="160" value="<?= $h($val('label')) ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" placeholder="Ex. PASS JTAC">
                </label>
                <label class="block text-sm">
                    <span class="font-semibold text-slate-700">Code</span>
                    <input name="code" maxlength="64" value="<?= $h($val('code')) ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" placeholder="JTAC">
                </label>
            </div>
            <label class="block text-sm">
                <span class="font-semibold text-slate-700">Description</span>
                <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2"><?= $h($val('description')) ?></textarea>
            </label>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="use_for_post" value="1"<?= $checked('use_for_post') ?>> Poste ouvert</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="use_for_advancement" value="1"<?= $checked('use_for_advancement') ?>> Avancement</label>
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="use_for_notation" value="1"<?= $checked('use_for_notation', false) ?>> Notation</label>
                <label class="inline-flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"<?= $checked('is_active') ?>> Actif
                </label>
            </div>
            <label class="block text-sm max-w-xs">
                <span class="font-semibold text-slate-700">Logique</span>
                <select name="logic" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="all"<?= $val('logic', 'all') === 'all' ? ' selected' : '' ?>>Toutes les conditions</option>
                    <option value="any"<?= $val('logic') === 'any' ? ' selected' : '' ?>>Au moins une condition</option>
                </select>
            </label>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-black uppercase tracking-wide text-slate-800">Conditions</h2>
                <button type="button" class="ath-btn" data-pass-add-condition>Ajouter une condition</button>
            </div>
            <div class="space-y-4" data-pass-conditions>
                <?php foreach ($conditions as $i => $c): ?>
                <?php
                $type = (string) ($c['condition_type'] ?? '');
                $preview = (string) ($previews[$i] ?? '');
                ?>
                <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4 space-y-3" data-pass-condition>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="block text-sm">
                            <span class="font-semibold text-slate-700">Type</span>
                            <select name="condition_type[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" data-pass-type>
                                <option value="">— Choisir —</option>
                                <?php foreach ($catalog as $opt): ?>
                                <option value="<?= $h($opt['type']) ?>"<?= $type === $opt['type'] ? ' selected' : '' ?>><?= $h($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm" data-pass-field="threshold">
                            <span class="font-semibold text-slate-700">Seuil</span>
                            <input type="number" step="0.1" min="0" name="threshold_value[]" value="<?= $h((string) ($c['threshold_value'] ?? '0')) ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        </label>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="block text-sm" data-pass-field="training">
                            <span class="font-semibold text-slate-700">Formation</span>
                            <select name="training_module_id[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($courses as $course): ?>
                                <option value="<?= (int) ($course['id'] ?? 0) ?>"<?= (string) ($c['training_module_id'] ?? '') === (string) ($course['id'] ?? '') ? ' selected' : '' ?>><?= $h((string) ($course['title'] ?? $course['name'] ?? ('#' . ($course['id'] ?? '')))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm" data-pass-field="qualification">
                            <span class="font-semibold text-slate-700">Qualification</span>
                            <select name="qualification_id[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($quals as $q): ?>
                                <option value="<?= (int) $q['id'] ?>"<?= (string) ($c['qualification_id'] ?? '') === (string) $q['id'] ? ' selected' : '' ?>><?= $h($q['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm" data-pass-field="grade">
                            <span class="font-semibold text-slate-700">Grade</span>
                            <select name="grade_id[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($grades as $g): ?>
                                <option value="<?= (int) $g['id'] ?>"<?= (string) ($c['grade_id'] ?? '') === (string) $g['id'] ? ' selected' : '' ?>><?= $h($g['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm" data-pass-field="hour_category">
                            <span class="font-semibold text-slate-700">Type d’heure</span>
                            <select name="hour_category[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($cats as $cat): ?>
                                <option value="<?= $h((string) $cat) ?>"<?= (string) ($c['hour_category'] ?? '') === (string) $cat ? ' selected' : '' ?>><?= $h((string) $cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm" data-pass-field="avis">
                            <span class="font-semibold text-slate-700">Avis hiérarchique</span>
                            <select name="avis_kind[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($avisKinds as $ak): ?>
                                <option value="<?= $h($ak['value']) ?>"<?= (string) ($c['avis_kind'] ?? '') === $ak['value'] ? ' selected' : '' ?>><?= $h($ak['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm" data-pass-field="bilan">
                            <span class="font-semibold text-slate-700">Type de notation</span>
                            <select name="bilan_kind[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                <option value="">—</option>
                                <?php foreach ($bilanKinds as $bk): ?>
                                <option value="<?= $h($bk['value']) ?>"<?= (string) ($c['bilan_kind'] ?? '') === $bk['value'] ? ' selected' : '' ?>><?= $h($bk['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="block text-sm" data-pass-field="window">
                            <span class="font-semibold text-slate-700">Fenêtre (jours)</span>
                            <input type="number" min="0" name="window_days[]" value="<?= $h((string) ($c['window_days'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                        </label>
                        <label class="block text-sm" data-pass-field="validity">
                            <span class="font-semibold text-slate-700">Qualification encore valable</span>
                            <select name="require_validity[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                                <option value="0"<?= empty($c['require_validity']) ? ' selected' : '' ?>>Non</option>
                                <option value="1"<?= !empty($c['require_validity']) ? ' selected' : '' ?>>Oui</option>
                            </select>
                        </label>
                        <input type="hidden" name="session_kind[]" value="<?= $h((string) ($c['session_kind'] ?? '')) ?>">
                    </div>
                    <?php if ($preview !== ''): ?>
                    <p class="text-xs text-slate-500"><?= $h($preview) ?></p>
                    <?php endif; ?>
                    <button type="button" class="text-xs font-semibold text-rose-700 hover:underline" data-pass-remove-condition>Retirer</button>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="ath-btn ath-btn--solid"><?= $isEdit ? 'Enregistrer' : 'Créer' ?></button>
            <a href="<?= $h(url('back-office/organisation/passes')) ?>" class="ath-btn">Annuler</a>
        </div>
    </form>

    <?php if ($isEdit): ?>
    <form method="post" action="<?= $h(url('back-office/organisation/passes/' . (int) $pass['id'] . '/delete')) ?>" onsubmit="return confirm('Supprimer ce PASS ?');">
        <?= \App\Core\Csrf::field() ?>
        <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">Supprimer ce PASS</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
<template id="pass-condition-template">
    <div class="rounded-lg border border-slate-100 bg-slate-50/70 p-4 space-y-3" data-pass-condition>
        <div class="grid gap-3 md:grid-cols-2">
            <label class="block text-sm">
                <span class="font-semibold text-slate-700">Type</span>
                <select name="condition_type[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2" data-pass-type>
                    <option value="">— Choisir —</option>
                    <?php foreach ($catalog as $opt): ?>
                    <option value="<?= $h($opt['type']) ?>"><?= $h($opt['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm" data-pass-field="threshold">
                <span class="font-semibold text-slate-700">Seuil</span>
                <input type="number" step="0.1" min="0" name="threshold_value[]" value="0" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
            </label>
        </div>
        <div class="grid gap-3 md:grid-cols-2">
            <label class="block text-sm" data-pass-field="training">
                <span class="font-semibold text-slate-700">Formation</span>
                <select name="training_module_id[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) ($course['id'] ?? 0) ?>"><?= $h((string) ($course['title'] ?? $course['name'] ?? ('#' . ($course['id'] ?? '')))) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm" data-pass-field="qualification">
                <span class="font-semibold text-slate-700">Qualification</span>
                <select name="qualification_id[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($quals as $q): ?>
                    <option value="<?= (int) $q['id'] ?>"><?= $h($q['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm" data-pass-field="grade">
                <span class="font-semibold text-slate-700">Grade</span>
                <select name="grade_id[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($grades as $g): ?>
                    <option value="<?= (int) $g['id'] ?>"><?= $h($g['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm" data-pass-field="hour_category">
                <span class="font-semibold text-slate-700">Type d’heure</span>
                <select name="hour_category[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($cats as $cat): ?>
                    <option value="<?= $h((string) $cat) ?>"><?= $h((string) $cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm" data-pass-field="avis">
                <span class="font-semibold text-slate-700">Avis hiérarchique</span>
                <select name="avis_kind[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($avisKinds as $ak): ?>
                    <option value="<?= $h($ak['value']) ?>"><?= $h($ak['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm" data-pass-field="bilan">
                <span class="font-semibold text-slate-700">Type de notation</span>
                <select name="bilan_kind[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="">—</option>
                    <?php foreach ($bilanKinds as $bk): ?>
                    <option value="<?= $h($bk['value']) ?>"><?= $h($bk['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm" data-pass-field="window">
                <span class="font-semibold text-slate-700">Fenêtre (jours)</span>
                <input type="number" min="0" name="window_days[]" value="" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
            </label>
            <label class="block text-sm" data-pass-field="validity">
                <span class="font-semibold text-slate-700">Qualification encore valable</span>
                <select name="require_validity[]" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2">
                    <option value="0" selected>Non</option>
                    <option value="1">Oui</option>
                </select>
            </label>
            <input type="hidden" name="session_kind[]" value="">
        </div>
        <button type="button" class="text-xs font-semibold text-rose-700 hover:underline" data-pass-remove-condition>Retirer</button>
    </div>
</template>
<script>
(function () {
  var root = document.querySelector('[data-pass-form]');
  if (!root) return;
  var list = root.querySelector('[data-pass-conditions]');
  var tpl = document.getElementById('pass-condition-template');
  var addBtn = root.querySelector('[data-pass-add-condition]');
  if (addBtn && tpl && list) {
    addBtn.addEventListener('click', function () {
      list.appendChild(tpl.content.cloneNode(true));
    });
  }
  root.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-pass-remove-condition]');
    if (!btn) return;
    var row = btn.closest('[data-pass-condition]');
    if (row && list && list.querySelectorAll('[data-pass-condition]').length > 1) {
      row.remove();
    }
  });
})();
</script>
