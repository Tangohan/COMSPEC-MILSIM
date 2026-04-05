<?php
$units = $units ?? [];
$grades = $grades ?? [];
$matriculeConfig = $matriculeConfig ?? null;
$adminPanels = $adminPanels ?? [];
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900">Configuration</h1>
            <p class="mt-1 text-slate-600">Unités, grades, matricules et panneaux administratifs.</p>
        </div>
        <a href="<?= url('back-office') ?>" class="text-slate-600 hover:text-slate-900 text-sm font-medium">← Back-office</a>
    </div>

    <?php if (\App\Core\Session::get('success')): ?>
    <p class="mb-4 text-sm text-emerald-600"><?= htmlspecialchars(\App\Core\Session::get('success')) ?></p>
    <?php \App\Core\Session::forget('success'); endif; ?>
    <?php if (\App\Core\Session::get('error')): ?>
    <p class="mb-4 text-sm text-red-600"><?= htmlspecialchars(\App\Core\Session::get('error')) ?></p>
    <?php \App\Core\Session::forget('error'); endif; ?>

    <!-- Unités -->
    <section class="mb-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-slate-900">Unités / Équipes / Groupes</h2>
            <div class="flex gap-2">
                <a href="<?= url('admin/units') ?>" class="px-3 py-1.5 bg-slate-100 text-slate-700 text-sm font-medium rounded hover:bg-slate-200">Liste</a>
                <a href="<?= url('admin/units/create') ?>" class="px-3 py-1.5 bg-slate-900 text-white text-sm font-medium rounded hover:bg-slate-800">Nouvelle unité</a>
            </div>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (empty($units)): ?>
            <p class="p-6 text-slate-500">Aucune unité. <a href="<?= url('admin/units/create') ?>" class="underline">Créer une unité</a>.</p>
            <?php else: ?>
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Type</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units as $u): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="p-3 font-medium"><?= htmlspecialchars($u['name']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($u['type'] ?? '—') ?></td>
                        <td class="p-3"><?= htmlspecialchars($u['code'] ?? '—') ?></td>
                        <td class="p-3">
                            <a href="<?= url('admin/units/' . (int)$u['id'] . '/edit') ?>" class="text-slate-600 hover:text-slate-900 text-sm underline">Modifier</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Données : Grades, Matricule, Panneaux -->
    <section class="grid gap-8 md:grid-cols-2">
        <!-- Grades -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-900">Grades</h2>
                <p class="text-xs text-slate-500 mt-0.5">Rangs et codes OTAN du tenant.</p>
            </div>
            <div class="p-6">
                <?php if (empty($grades)): ?>
                <p class="text-slate-500 text-sm">Aucun grade défini.</p>
                <?php else: ?>
                <ul class="space-y-2">
                    <?php foreach ($grades as $g): ?>
                    <li class="flex justify-between items-center text-sm">
                        <span class="font-medium"><?= htmlspecialchars($g['label_long'] ?? $g['name'] ?? '') ?></span>
                        <span class="text-slate-500"><?= htmlspecialchars($g['label_short'] ?? $g['short_name'] ?? '') ?><?= !empty($g['label_otan'] ?? $g['nato_code']) ? ' · ' . htmlspecialchars($g['label_otan'] ?? $g['nato_code']) : '' ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Config matricule -->
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-900">Configuration matricule</h2>
                <p class="text-xs text-slate-500 mt-0.5">Préfixe et format d’attribution des matricules.</p>
            </div>
            <div class="p-6">
                <?php if ($matriculeConfig): ?>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-slate-500 font-medium">Préfixe</dt><dd class="font-mono"><?= htmlspecialchars($matriculeConfig['prefix'] ?? '—') ?></dd></div>
                    <div><dt class="text-slate-500 font-medium">Format</dt><dd class="font-mono"><?= htmlspecialchars($matriculeConfig['format_pattern'] ?? '—') ?></dd></div>
                    <div><dt class="text-slate-500 font-medium">Prochain numéro</dt><dd><?= (int)($matriculeConfig['next_number'] ?? 0) ?></dd></div>
                </dl>
                <p class="mt-4 text-xs text-slate-500">La configuration est utilisée lors de la génération de matricule depuis la fiche personnel.</p>
                <?php else: ?>
                <p class="text-slate-500 text-sm">Aucune configuration. Elle sera créée à la première génération de matricule.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Panneaux admin personnel -->
    <section class="mt-8">
        <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-900">Panneaux administratifs (fiche personnel)</h2>
            <p class="text-xs text-slate-500 mt-0.5">Sections personnalisées affichées sur les fiches personnel.</p>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <?php if (empty($adminPanels)): ?>
            <p class="p-6 text-slate-500 text-sm">Aucun panneau configuré.</p>
            <?php else: ?>
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Nom</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Slug</th>
                        <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adminPanels as $p): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                        <td class="p-3 font-medium"><?= htmlspecialchars($p['name']) ?></td>
                        <td class="p-3 font-mono text-sm"><?= htmlspecialchars($p['slug']) ?></td>
                        <td class="p-3"><?= (int)($p['display_order'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>

    <p class="mt-8 text-sm text-slate-500">
        <a href="<?= url('back-office') ?>" class="underline">Retour back-office</a>
        · <a href="<?= url('admin/units') ?>" class="underline">Gérer les unités</a>
        · <a href="<?= url('admin/users') ?>" class="underline">Utilisateurs</a>
    </p>
</div>
