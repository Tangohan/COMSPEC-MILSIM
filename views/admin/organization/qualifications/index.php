<?php
$definitions = $definitions ?? [];
$categories = $categories ?? [];
$types = $types ?? [];
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
?>
<div class="max-w-6xl mx-auto px-6 py-12">
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Référentiel des qualifications</h1>
            <p class="text-sm text-slate-600 mt-1">Définitions, catégories, types et suivi des détenteurs — distinctes des attributions individuelles.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= url('back-office/referentiels/qualifications/emetteurs') ?>" class="px-3 py-2 text-sm font-medium border border-slate-300 rounded hover:bg-slate-50">Organismes émetteurs</a>
            <a href="<?= url('back-office/referentiels/qualifications/attribuer') ?>" class="px-3 py-2 text-sm font-medium border border-slate-300 rounded hover:bg-slate-50">Attribuer</a>
            <a href="<?= url('back-office/referentiels/qualifications/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouvelle qualification</a>
        </div>
    </div>

    <?php if ($flashSuccess): ?><p class="mb-4 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded"><?= htmlspecialchars($flashSuccess) ?></p><?php endif; ?>
    <?php if ($flashError): ?><p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars($flashError) ?></p><?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6 mb-8">
        <div class="rounded-lg border border-slate-200 p-4 bg-white">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-3">Catégories</h2>
            <ul class="space-y-1 text-sm mb-4 max-h-40 overflow-auto">
                <?php foreach ($categories as $c): ?>
                    <li><?= htmlspecialchars((string) $c['name']) ?></li>
                <?php endforeach; ?>
                <?php if ($categories === []): ?><li class="text-slate-500">Aucune pour l’instant.</li><?php endif; ?>
            </ul>
            <form method="post" action="<?= url('back-office/referentiels/qualifications/categories') ?>" class="space-y-2">
                <?= \App\Core\Csrf::field() ?>
                <input type="text" name="name" required placeholder="Nom de catégorie" class="w-full border border-slate-300 rounded px-2 py-1.5 text-sm">
                <button class="text-xs font-semibold text-emerald-800 underline">Ajouter</button>
            </form>
        </div>
        <div class="rounded-lg border border-slate-200 p-4 bg-white">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-3">Types</h2>
            <ul class="space-y-1 text-sm mb-4 max-h-40 overflow-auto">
                <?php foreach ($types as $t): ?>
                    <li><span class="font-mono text-[11px] text-slate-500"><?= htmlspecialchars((string) $t['code']) ?></span> — <?= htmlspecialchars((string) $t['name']) ?></li>
                <?php endforeach; ?>
            </ul>
            <form method="post" action="<?= url('back-office/referentiels/qualifications/types') ?>" class="space-y-2">
                <?= \App\Core\Csrf::field() ?>
                <input type="text" name="name" required placeholder="Nom" class="w-full border border-slate-300 rounded px-2 py-1.5 text-sm">
                <input type="text" name="code" required placeholder="Code" class="w-full border border-slate-300 rounded px-2 py-1.5 text-sm uppercase">
                <button class="text-xs font-semibold text-emerald-800 underline">Ajouter</button>
            </form>
        </div>
        <div class="rounded-lg border border-slate-200 p-4 bg-slate-50">
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-2">Rappel</h2>
            <p class="text-sm text-slate-600 leading-relaxed">Les états « expiration prochaine » ou « expirée » sont calculés automatiquement à partir des dates. L’archivage conserve l’historique des attributions.</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Qualification</th>
                    <th class="px-4 py-3">Catégorie</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Niveaux</th>
                    <th class="px-4 py-3">Validité</th>
                    <th class="px-4 py-3">Personnels</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($definitions as $d):
                    $archived = !empty($d['archived_at']);
                    $validite = !empty($d['is_permanent'])
                        ? 'Permanente'
                        : ((isset($d['default_validity_months']) && $d['default_validity_months'] !== null)
                            ? ((int) $d['default_validity_months'] . ' mois')
                            : '—');
                    ?>
                <tr class="<?= $archived ? 'opacity-60' : '' ?>">
                    <td class="px-4 py-3 font-mono text-xs"><?= htmlspecialchars((string) $d['code']) ?></td>
                    <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) $d['name']) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars((string) ($d['category_name'] ?? '—')) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars((string) ($d['type_name'] ?? '—')) ?></td>
                    <td class="px-4 py-3"><?= (int) ($d['levels_count'] ?? 0) ?></td>
                    <td class="px-4 py-3"><?= htmlspecialchars($validite) ?></td>
                    <td class="px-4 py-3"><?= (int) ($d['holders_count'] ?? 0) ?></td>
                    <td class="px-4 py-3"><?= $archived ? 'Archivée' : 'Active' ?></td>
                    <td class="px-4 py-3 text-right">
                        <a href="<?= url('back-office/referentiels/qualifications/' . (int) $d['id'] . '/edit') ?>" class="text-emerald-800 font-medium underline">Ouvrir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($definitions === []): ?>
                <tr><td colspan="9" class="px-4 py-8 text-center text-slate-500">Aucune qualification dans le référentiel.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
