<?php
declare(strict_types=1);
$groups = $groups ?? [];
?>
<div class="max-w-4xl mx-auto px-6 py-10">
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Groupes de destinataires</h1>
        <a href="<?= url('back-office/communications/groups/create') ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Nouveau groupe</a>
    </div>
    <p class="text-sm text-slate-600 mb-6"><a href="<?= url('back-office/communications') ?>" class="text-blue-700 font-semibold hover:underline">← Rédaction d’e-mail</a></p>

    <?php if (\App\Core\Session::get('success')): ?><p class="mb-4 text-sm text-emerald-700"><?= htmlspecialchars((string) \App\Core\Session::get('success')) ?></p><?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?><p class="mb-4 text-sm text-rose-700"><?= htmlspecialchars((string) \App\Core\Session::get('error')) ?></p><?php \App\Core\Session::forget('error'); endif; ?>

    <?php if (empty($groups)): ?>
        <p class="text-slate-500">Aucun groupe enregistré.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 font-semibold text-slate-600">Nom</th>
                <th class="text-left p-3 font-semibold text-slate-600">Description</th>
                <th class="text-left p-3 font-semibold text-slate-600">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($groups as $g): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($g['name'] ?? '') ?></td>
                <td class="p-3 text-slate-600"><?= htmlspecialchars((string) ($g['description'] ?? '—')) ?></td>
                <td class="p-3">
                    <a href="<?= url('back-office/communications/groups/' . (int) ($g['id'] ?? 0) . '/edit') ?>" class="text-blue-700 hover:underline font-semibold">Modifier</a>
                    <form method="post" action="<?= url('back-office/communications/groups/' . (int) ($g['id'] ?? 0) . '/delete') ?>" class="inline ml-2" onsubmit="return confirm('Supprimer ce groupe ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-rose-700 hover:underline font-semibold">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
