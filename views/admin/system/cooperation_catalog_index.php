<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $cooperationCatalogRows */
$rows = $cooperationCatalogRows ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Types de coopération (référence site)</h1>
        <div class="flex flex-wrap gap-3">
            <a href="<?= url('admin/system/cooperation/catalog/create') ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-emerald-600 transition-colors">Ajouter un type</a>
            <a href="<?= url('admin') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Retour</a>
        </div>
    </div>
    <p class="text-sm text-slate-600 mb-6 max-w-3xl">Ces libellés et aides sont proposés à toutes les communautés pour qualifier une coopération. Chaque communauté peut ajouter ses propres entrées dans son back-office.</p>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if ($rows === []): ?>
        <p class="text-slate-600 text-sm">Aucune entrée.</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Libellé</th>
                        <th class="px-4 py-3">Actif</th>
                        <th class="px-4 py-3">Tri</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $id = (int) ($r['id'] ?? 0);
                        $active = !empty($r['is_active']);
                        ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) ($r['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php if (trim((string) ($r['description'] ?? '')) !== ''): ?>
                                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars(trim((string) $r['description']), ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3"><?= $active ? '<span class="text-emerald-700 font-medium">Oui</span>' : '<span class="text-slate-400">Non</span>' ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= (int) ($r['sort_order'] ?? 0) ?></td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="<?= url('admin/system/cooperation/catalog/' . $id . '/edit') ?>" class="text-sm font-semibold text-blue-700 hover:underline">Modifier</a>
                                <form method="post" action="<?= url('admin/system/cooperation/catalog/' . $id . '/delete') ?>" class="inline" onsubmit="return confirm('Retirer ce type de la référence site ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="text-sm font-semibold text-rose-700 hover:underline">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
