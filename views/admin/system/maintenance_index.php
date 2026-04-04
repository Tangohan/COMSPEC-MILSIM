<?php

declare(strict_types=1);

$rules = $maintenanceRules ?? [];
$missing = !empty($maintenanceTableMissing);
$s = \App\Core\Session::getFlash('success');
$e = \App\Core\Session::getFlash('error');
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Maintenance</h1>
        <div class="flex flex-wrap gap-3">
            <a href="<?= url('admin/system') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">Admin système</a>
            <?php if (!$missing): ?>
            <a href="<?= url('admin/system/maintenance/create') ?>" class="text-sm font-semibold text-white bg-slate-900 px-4 py-2 rounded-lg hover:bg-slate-800">Nouvelle règle</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($s): ?>
        <p class="mb-4 text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 px-4 py-3 rounded-lg"><?= htmlspecialchars($s) ?></p>
    <?php endif; ?>
    <?php if ($e): ?>
        <p class="mb-4 text-sm text-red-800 bg-red-50 border border-red-200 px-4 py-3 rounded-lg"><?= htmlspecialchars($e) ?></p>
    <?php endif; ?>

    <?php if ($missing): ?>
        <p class="text-slate-600">Appliquez la migration <code class="bg-slate-100 px-1 rounded">migrations/maintenance.sql</code> ou Phinx <code class="bg-slate-100 px-1 rounded">20260404000001_create_app_maintenance</code>.</p>
    <?php elseif ($rules === []): ?>
        <p class="text-slate-600 mb-6">Aucune règle. Créez une règle (global, <code class="bg-slate-100 px-1 rounded">route:/chemin</code>, <code class="bg-slate-100 px-1 rounded">module:forum</code>).</p>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-black uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Scope</th>
                        <th class="px-4 py-3">Titre</th>
                        <th class="px-4 py-3">Actif</th>
                        <th class="px-4 py-3">Priorité</th>
                        <th class="px-4 py-3">Fenêtre</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($rules as $r): ?>
                        <?php
                        $id = (int) ($r['id'] ?? 0);
                        $en = !empty($r['is_enabled']);
                        ?>
                        <tr class="hover:bg-slate-50/80">
                            <td class="px-4 py-3 font-mono text-xs"><?= $id ?></td>
                            <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars((string) ($r['scope'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-medium"><?= htmlspecialchars((string) ($r['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3">
                                <?php if ($en): ?>
                                    <span class="text-emerald-700 font-semibold">Oui</span>
                                <?php else: ?>
                                    <span class="text-slate-400">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3"><?= (int) ($r['priority'] ?? 0) ?></td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <?= htmlspecialchars((string) ($r['starts_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                →
                                <?= htmlspecialchars((string) ($r['ends_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="<?= url('admin/system/maintenance/' . $id . '/edit') ?>" class="text-emerald-700 hover:underline mr-3">Modifier</a>
                                <a href="<?= url('admin/system/maintenance/' . $id . '/audit') ?>" class="text-slate-600 hover:underline mr-3">Historique</a>
                                <form action="<?= url('admin/system/maintenance/' . $id . '/toggle') ?>" method="post" class="inline">
                                    <?= \App\Core\Csrf::field() ?>
                                    <input type="hidden" name="enabled" value="<?= $en ? '0' : '1' ?>">
                                    <button type="submit" class="text-slate-700 hover:underline mr-3"><?= $en ? 'Désactiver' : 'Activer' ?></button>
                                </form>
                                <form action="<?= url('admin/system/maintenance/' . $id . '/delete') ?>" method="post" class="inline" onsubmit="return confirm('Supprimer cette règle ?');">
                                    <?= \App\Core\Csrf::field() ?>
                                    <button type="submit" class="text-red-600 hover:underline">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
