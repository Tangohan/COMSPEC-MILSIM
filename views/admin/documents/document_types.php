<?php
declare(strict_types=1);

$types = is_array($types ?? null) ? $types : [];
$csrf_token = (string) ($csrf_token ?? '');
?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900">Types de documents</h1>
    <p class="mt-2 text-sm text-slate-600">Référentiel configurable : instructions, notes de service, directives, etc. Chaque type peut imposer par défaut une lecture obligatoire ou un accusé de lecture.</p>

    <form method="post" action="<?= url('back-office/documents/types') ?>" class="mt-6 space-y-3 rounded-xl border border-slate-200 bg-white p-4">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        <div class="grid gap-3 md:grid-cols-3">
            <label class="text-xs font-bold uppercase text-slate-500">Nom
                <input class="mt-1 w-full rounded-lg border px-3 py-2" name="label" required placeholder="Note de service">
            </label>
            <label class="text-xs font-bold uppercase text-slate-500">Code interne
                <input class="mt-1 w-full rounded-lg border px-3 py-2" name="code" placeholder="note_de_service">
            </label>
            <label class="text-xs font-bold uppercase text-slate-500">Abréviation (numérotation)
                <input class="mt-1 w-full rounded-lg border px-3 py-2" name="code_prefix" required placeholder="NS">
            </label>
        </div>
        <label class="block text-xs font-bold uppercase text-slate-500">Description
            <textarea class="mt-1 w-full rounded-lg border px-3 py-2" name="description" rows="2"></textarea>
        </label>
        <div class="grid gap-3 md:grid-cols-4">
            <label class="text-xs font-bold uppercase text-slate-500">Couleur
                <input class="mt-1 w-full rounded-lg border px-3 py-2" name="color" type="color" value="#0f766e">
            </label>
            <label class="flex items-end gap-2 text-sm text-slate-700 pb-2">
                <input type="checkbox" name="default_reading_required" value="1"> Lecture obligatoire par défaut
            </label>
            <label class="flex items-end gap-2 text-sm text-slate-700 pb-2">
                <input type="checkbox" name="default_acknowledgment_required" value="1"> Accusé de lecture par défaut
            </label>
            <label class="flex items-end gap-2 text-sm text-slate-700 pb-2">
                <input type="checkbox" name="default_require_validation" value="1"> Validation avant publication
            </label>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" checked> Type actif
        </label>
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-black uppercase text-white">Ajouter un type</button>
    </form>

    <table class="mt-8 w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-slate-500">
                <th class="py-2">Type</th>
                <th>Abrév.</th>
                <th>Comportement</th>
                <th>État</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($types as $t): ?>
            <?php $tid = (int) ($t['id'] ?? 0); ?>
            <tr class="border-b align-top">
                <td class="py-3">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background:<?= htmlspecialchars((string) ($t['color'] ?? '#64748b'), ENT_QUOTES, 'UTF-8') ?>"></span>
                    <strong><?= htmlspecialchars((string) ($t['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <div class="text-xs text-slate-500"><?= htmlspecialchars((string) ($t['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </td>
                <td class="font-mono font-bold"><?= htmlspecialchars((string) ($t['code_prefix'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-xs text-slate-600">
                    <?= !empty($t['default_reading_required']) ? 'Lecture obligatoire · ' : '' ?>
                    <?= !empty($t['default_acknowledgment_required']) ? 'Accusé requis · ' : '' ?>
                    <?= !empty($t['default_require_validation']) ? 'Validation' : 'Publication directe' ?>
                </td>
                <td><?= !empty($t['is_active']) ? 'Actif' : 'Désactivé' ?></td>
                <td>
                    <form method="post" action="<?= url('back-office/documents/types') ?>" class="space-y-1">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= $tid ?>">
                        <input type="hidden" name="label" value="<?= htmlspecialchars((string) ($t['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="code_prefix" value="<?= htmlspecialchars((string) ($t['code_prefix'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="description" value="<?= htmlspecialchars((string) ($t['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="color" value="<?= htmlspecialchars((string) ($t['color'] ?? '#64748b'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="sort_order" value="<?= (int) ($t['sort_order'] ?? 100) ?>">
                        <?php if (!empty($t['default_reading_required'])): ?><input type="hidden" name="default_reading_required" value="1"><?php endif; ?>
                        <?php if (!empty($t['default_acknowledgment_required'])): ?><input type="hidden" name="default_acknowledgment_required" value="1"><?php endif; ?>
                        <?php if (!empty($t['default_require_validation'])): ?><input type="hidden" name="default_require_validation" value="1"><?php endif; ?>
                        <?php if (empty($t['is_active'])): ?>
                            <input type="hidden" name="is_active" value="1">
                            <button class="text-xs font-bold text-emerald-700" type="submit">Réactiver</button>
                        <?php else: ?>
                            <button class="text-xs font-bold text-rose-700" type="submit">Désactiver</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
