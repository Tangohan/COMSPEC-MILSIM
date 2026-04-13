<?php
declare(strict_types=1);
$templates = $templates ?? [];
$kinds = $kinds ?? [];
?>
<div class="max-w-4xl mx-auto px-6 py-10">
    <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
        <h1 class="text-2xl font-black text-slate-900">Modèles d’e-mail</h1>
        <a href="<?= url('back-office/communications/templates/create') ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Nouveau modèle</a>
    </div>
    <p class="text-sm text-slate-600 mb-6"><a href="<?= url('back-office/communications') ?>" class="text-blue-700 font-semibold hover:underline">← Retour à la rédaction</a></p>

    <?php if (\App\Core\Session::get('success')): ?><p class="mb-4 text-sm text-emerald-700"><?= htmlspecialchars((string) \App\Core\Session::get('success')) ?></p><?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?><p class="mb-4 text-sm text-rose-700"><?= htmlspecialchars((string) \App\Core\Session::get('error')) ?></p><?php \App\Core\Session::forget('error'); endif; ?>

    <?php if (empty($templates)): ?>
        <p class="text-slate-500">Aucun modèle personnalisé. Les textes d’aide fournis à la création de la communauté restent disponibles dans la liste de rédaction.</p>
    <?php else: ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 font-semibold text-slate-600">Nom</th>
                <th class="text-left p-3 font-semibold text-slate-600">Famille</th>
                <th class="text-left p-3 font-semibold text-slate-600">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $t): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-medium"><?= htmlspecialchars($t['name'] ?? '') ?></td>
                <td class="p-3"><?= htmlspecialchars(\App\Support\TenantEmailKind::label((string) ($t['kind'] ?? ''))) ?></td>
                <td class="p-3">
                    <?php if (empty($t['is_prefab'])): ?>
                        <a href="<?= url('back-office/communications/templates/' . (int) ($t['id'] ?? 0) . '/edit') ?>" class="text-blue-700 hover:underline font-semibold">Modifier</a>
                        <form method="post" action="<?= url('back-office/communications/templates/' . (int) ($t['id'] ?? 0) . '/delete') ?>" class="inline ml-2" onsubmit="return confirm('Supprimer ce modèle ?');">
                            <?= \App\Core\Csrf::field() ?>
                            <button type="submit" class="text-rose-700 hover:underline font-semibold">Supprimer</button>
                        </form>
                    <?php else: ?>
                        <span class="text-slate-400 text-xs">Texte d’aide (non modifiable)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
