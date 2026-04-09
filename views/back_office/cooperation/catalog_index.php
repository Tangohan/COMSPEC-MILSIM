<?php
declare(strict_types=1);
$rows = $cooperationCatalogRows ?? [];
$tableOk = !empty($cooperationCatalogTableOk);
$csrf = $csrfToken ?? \App\Core\Csrf::token();
?>
<div class="max-w-5xl mx-auto px-6 py-10">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <a href="<?= htmlspecialchars(cooperation_mission_index_url(), ENT_QUOTES, 'UTF-8') ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900 underline">← Coopérations inter-unités</a>
            <h1 class="mt-3 text-2xl font-black text-slate-900">Types de coopération (votre communauté)</h1>
        </div>
        <?php if ($tableOk): ?>
        <a href="<?= url('back-office/cooperation/catalog/create') ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-bold hover:bg-emerald-600 transition-colors">Ajouter un type local</a>
        <?php endif; ?>
    </div>
    <p class="text-sm text-slate-600 mb-6 max-w-3xl">Complétez la liste proposée par le site avec vos propres types ou modèles internes. Les entrées locales peuvent reprendre le même nom interne qu’une référence du site pour en modifier le libellé affiché chez vous.</p>
    <?php $s = \App\Core\Session::getFlash('success'); $e = \App\Core\Session::getFlash('error'); ?>
    <?php if ($s): ?><p class="text-emerald-700 text-sm mb-4"><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($e): ?><p class="text-red-600 text-sm mb-4"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if (!$tableOk): ?>
        <p class="text-amber-800 text-sm bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Cette fonction sera disponible après la prochaine mise à jour de la base de données.</p>
    <?php elseif ($rows === []): ?>
        <p class="text-slate-600 text-sm">Aucune entrée locale. Les types proposés par défaut du site restent disponibles lors de la rédaction d’une proposition.</p>
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
                                <a href="<?= url('back-office/cooperation/catalog/' . $id . '/edit') ?>" class="text-sm font-semibold text-blue-700 hover:underline">Modifier</a>
                                <form method="post" action="<?= url('back-office/cooperation/catalog/' . $id . '/delete') ?>" class="inline" onsubmit="return confirm('Supprimer cette entrée locale ?');">
                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
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
