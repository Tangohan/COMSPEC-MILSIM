<?php
declare(strict_types=1);

/** @var array<string, list<array<string, mixed>>> $competencyByPalier */
/** @var list<array<string, mixed>> $competencyGrades */
/** @var array<int, string> $competencyGradeLabels */
/** @var list<string> $competencyAcquisitionLevels */

$competencyByPalier = $competencyByPalier ?? [];
$competencyGrades = $competencyGrades ?? [];
$competencyGradeLabels = $competencyGradeLabels ?? [];
$competencyAcquisitionLevels = $competencyAcquisitionLevels ?? [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');

$levelBadge = static function (string $lvl): string {
    return match ($lvl) {
        'Basique' => 'bg-slate-100 text-slate-700',
        'Intermédiaire' => 'bg-sky-100 text-sky-800',
        'Avancé' => 'bg-amber-100 text-amber-800',
        'Spécifique' => 'bg-rose-100 text-rose-800',
        default => 'bg-slate-100 text-slate-500',
    };
};
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-black text-slate-900">Matrice de compétences × grades</h1>
        <a href="<?= url('back-office/referentiels/grades') ?>" class="text-sm font-medium text-slate-600 underline hover:text-slate-900">Référentiel des grades →</a>
    </div>
    <p class="mb-6 text-sm text-slate-600 max-w-3xl">Pour chaque palier de formation, associez chaque compétence/module au grade auquel elle est attendue et au niveau d’acquisition visé. Catalogue de référence — ne suit pas l’acquisition réelle par opérateur.</p>

    <?php if ($flashSuccess): ?>
    <p class="mb-4 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded"><?= htmlspecialchars($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>

    <details class="mb-8 rounded-lg border border-slate-200 bg-slate-50/80" open>
        <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-slate-800">Ajouter une compétence à la matrice</summary>
        <form method="post" action="<?= url('back-office/referentiels/competences') ?>" class="grid gap-3 p-4 pt-0 sm:grid-cols-2 lg:grid-cols-5">
            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1" for="palier">Palier</label>
                <input type="text" name="palier" id="palier" required maxlength="120" placeholder="Ex. Formation initiale" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" list="palier-suggestions">
                <datalist id="palier-suggestions">
                    <?php foreach (array_keys($competencyByPalier) as $existingPalier): ?>
                    <option value="<?= htmlspecialchars($existingPalier, ENT_QUOTES, 'UTF-8') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1" for="palier_order">Ordre du palier</label>
                <input type="number" name="palier_order" id="palier_order" value="0" min="0" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1" for="label">Compétence / module</label>
                <input type="text" name="label" id="label" required maxlength="255" placeholder="Ex. Module Radio (courte)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1" for="grade_id">Grade attendu</label>
                <select name="grade_id" id="grade_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="0">—</option>
                    <?php foreach ($competencyGrades as $g): ?>
                    <option value="<?= (int) $g['id'] ?>"><?= htmlspecialchars((string) ($g['label_long'] ?? $g['label_short'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1" for="acquisition_level">Niveau d’acquisition</label>
                <select name="acquisition_level" id="acquisition_level" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    <?php foreach ($competencyAcquisitionLevels as $lvl): ?>
                    <option value="<?= htmlspecialchars($lvl) ?>"><?= htmlspecialchars($lvl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-5">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Ajouter</button>
            </div>
        </form>
    </details>

    <?php if ($competencyByPalier === []): ?>
    <div class="rounded-lg border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500">Aucune compétence dans la matrice pour l’instant.</div>
    <?php else: ?>
    <?php foreach ($competencyByPalier as $palierName => $rows): ?>
    <section class="mb-8">
        <h2 class="mb-3 text-sm font-black uppercase tracking-wide text-slate-800"><?= htmlspecialchars($palierName) ?></h2>
        <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Compétence / module</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Grade attendu</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Niveau d’acquisition</th>
                    <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $rid = (int) $r['id'];
                    $rGradeId = (int) ($r['grade_id'] ?? 0);
                    $rGradeLabel = $rGradeId > 0 ? ($competencyGradeLabels[$rGradeId] ?? '—') : '—';
                    $rLevel = (string) ($r['acquisition_level'] ?? '');
                    ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="p-3 text-sm font-semibold text-slate-900"><?= htmlspecialchars((string) $r['label']) ?></td>
                    <td class="p-3 text-sm text-slate-700"><?= htmlspecialchars($rGradeLabel) ?></td>
                    <td class="p-3">
                        <?php if ($rLevel !== ''): ?>
                        <span class="inline-flex px-2 py-0.5 text-[10px] font-black uppercase rounded-full <?= $levelBadge($rLevel) ?>"><?= htmlspecialchars($rLevel) ?></span>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3 text-sm space-x-2 whitespace-nowrap">
                        <details class="inline-block align-middle">
                            <summary class="cursor-pointer text-slate-700 font-semibold inline">Modifier</summary>
                            <form method="post" action="<?= url('back-office/referentiels/competences/' . $rid) ?>" class="mt-3 grid gap-2 rounded-md border border-slate-200 bg-slate-50/80 p-3 sm:grid-cols-2">
                                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Palier</label>
                                    <input type="text" name="palier" required maxlength="120" value="<?= htmlspecialchars((string) $r['palier']) ?>" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Ordre du palier</label>
                                    <input type="number" name="palier_order" min="0" value="<?= (int) ($r['palier_order'] ?? 0) ?>" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Compétence / module</label>
                                    <input type="text" name="label" required maxlength="255" value="<?= htmlspecialchars((string) $r['label']) ?>" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Grade attendu</label>
                                    <select name="grade_id" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                        <option value="0">—</option>
                                        <?php foreach ($competencyGrades as $g): ?>
                                        <option value="<?= (int) $g['id'] ?>" <?= $rGradeId === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) ($g['label_long'] ?? $g['label_short'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Niveau d’acquisition</label>
                                    <select name="acquisition_level" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm">
                                        <option value="">—</option>
                                        <?php foreach ($competencyAcquisitionLevels as $lvl): ?>
                                        <option value="<?= htmlspecialchars($lvl) ?>" <?= $rLevel === $lvl ? 'selected' : '' ?>><?= htmlspecialchars($lvl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <button type="submit" class="rounded-md bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-800">Enregistrer</button>
                                </div>
                            </form>
                        </details>
                        <form method="post" action="<?= url('back-office/referentiels/competences/' . $rid . '/supprimer') ?>" class="inline" onsubmit="return confirm('Retirer cette compétence de la matrice ?');">
                            <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                            <button type="submit" class="text-rose-700 font-semibold">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
