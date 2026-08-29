<?php
$tab = $tab ?? 'fr';
$gradesFr = $gradesFr ?? [];
$gradesUs = $gradesUs ?? [];
$categories = $categories ?? [];
$gcfRaw = $gradeCategoryFilterId ?? null;
$gradeCategoryFilterId = ($gcfRaw !== null && (int) $gcfRaw > 0) ? (int) $gcfRaw : null;
$gradeDisplayService = $gradeDisplayService ?? null;
$flashSuccess = \App\Core\Session::getFlash('success');
$flashError = \App\Core\Session::getFlash('error');
$gradesQuerySuffix = static function (string $t, ?int $catId): string {
    $q = '?tab=' . rawurlencode($t);
    if ($catId !== null && $catId > 0) {
        $q .= '&categorie=' . $catId;
    }

    return $q;
};
?>
<div class="max-w-5xl mx-auto px-6 py-12">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-black text-slate-900">Référentiel des grades</h1>
        <div class="flex items-center gap-3">
            <a href="<?= url('back-office/referentiels/grades/catalogue') ?>" class="text-sm font-medium text-emerald-800 underline hover:text-emerald-950">Catalogue OTAN / audit →</a>
            <a href="<?= url('back-office/referentiels/competences') ?>" class="text-sm font-medium text-slate-600 underline hover:text-slate-900">Matrice de compétences →</a>
            <a href="<?= url('back-office/referentiels/grades/create') ?>" class="px-4 py-2 bg-slate-900 text-white text-sm font-semibold rounded hover:bg-slate-800">Nouveau grade</a>
        </div>
    </div>
    <?php if ($flashSuccess): ?>
    <p class="mb-4 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded"><?= htmlspecialchars($flashSuccess) ?></p>
    <?php endif; ?>
    <?php if ($flashError): ?>
    <p class="mb-4 text-sm text-red-700 bg-red-50 px-3 py-2 rounded"><?= htmlspecialchars($flashError) ?></p>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 border-b border-slate-200 mb-6">
        <a href="<?= url('back-office/referentiels/grades') . $gradesQuerySuffix('fr', $gradeCategoryFilterId) ?>" class="px-4 py-2 text-sm font-medium <?= $tab === 'fr' ? 'border-b-2 border-slate-900 text-slate-900' : 'text-slate-600 hover:text-slate-900' ?>">Grades français</a>
        <a href="<?= url('back-office/referentiels/grades') . $gradesQuerySuffix('us', $gradeCategoryFilterId) ?>" class="px-4 py-2 text-sm font-medium <?= $tab === 'us' ? 'border-b-2 border-slate-900 text-slate-900' : 'text-slate-600 hover:text-slate-900' ?>">Grades américains</a>
        <a href="<?= url('back-office/referentiels/grades') ?>?tab=otan" class="px-4 py-2 text-sm font-medium <?= $tab === 'otan' ? 'border-b-2 border-slate-900 text-slate-900' : 'text-slate-600 hover:text-slate-900' ?>">Correspondances OTAN</a>
        <a href="<?= url('back-office/referentiels/grades') ?>?tab=categories" class="px-4 py-2 text-sm font-medium <?= $tab === 'categories' ? 'border-b-2 border-slate-900 text-slate-900' : 'text-slate-600 hover:text-slate-900' ?>">Catégories</a>
    </nav>

    <?php if ($tab === 'fr' || $tab === 'us'): ?>
    <form method="get" action="<?= url('back-office/referentiels/grades') ?>" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50/80 p-4">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab, ENT_QUOTES, 'UTF-8') ?>">
        <div class="min-w-[12rem]">
            <label for="grade-cat-filter" class="block text-xs font-semibold text-slate-600 mb-1">Filtrer par catégorie</label>
            <select id="grade-cat-filter" name="categorie" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm">
                <option value="0">Toutes les catégories</option>
                <?php foreach ($categories as $c):
                    $cid = (int) ($c['id'] ?? 0);
                    if ($cid < 1) {
                        continue;
                    }
                    $sel = $gradeCategoryFilterId !== null && $gradeCategoryFilterId === $cid ? ' selected' : '';
                    ?>
                <option value="<?= $cid ?>"<?= $sel ?>><?= htmlspecialchars((string) ($c['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Appliquer</button>
        <?php if ($gradeCategoryFilterId !== null && $gradeCategoryFilterId > 0): ?>
        <a href="<?= url('back-office/referentiels/grades') . $gradesQuerySuffix($tab, null) ?>" class="text-sm font-medium text-slate-600 underline hover:text-slate-900 py-2">Réinitialiser le filtre</a>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <?php if ($tab === 'fr'): ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Court</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Long</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">OTAN</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Catégorie</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Rendu</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gradesFr as $g): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-mono text-sm"><?= htmlspecialchars($g['code']) ?></td>
                <td class="p-3"><?= htmlspecialchars($g['label_short']) ?></td>
                <td class="p-3"><?= htmlspecialchars($g['label_long']) ?></td>
                <td class="p-3"><?= htmlspecialchars($g['label_otan'] ?? '—') ?></td>
                <td class="p-3 text-slate-600"><?= htmlspecialchars($g['category_label'] ?? '') ?></td>
                <td class="p-3"><?= (int) $g['sort_order'] ?></td>
                <td class="p-3 text-sm text-slate-600">
                    <?php if ($gradeDisplayService): ?>
                    Classique: <?= htmlspecialchars($gradeDisplayService->formatForUser($g, 'classic', 'FR')) ?>
                    · OTAN: <?= htmlspecialchars($gradeDisplayService->getOtan($g) ?? '—') ?>
                    · Hybride: <?= htmlspecialchars($gradeDisplayService->getFull($g)) ?>
                    <?php endif; ?>
                </td>
                <td class="p-3">
                    <a href="<?= url('back-office/referentiels/grades/' . $g['id'] . '/edit') ?>" class="text-slate-700 hover:underline text-sm">Modifier</a>
                    <?php if (!empty($g['is_active'])): ?>
                    · <form action="<?= url('back-office/referentiels/grades/' . $g['id'] . '/deactivate') ?>" method="post" class="inline" onsubmit="return confirm('Désactiver ce grade ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-amber-600 hover:underline text-sm">Désactiver</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($gradesFr)): ?>
    <p class="text-slate-500">Aucun grade français.</p>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'us'): ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Court</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Long</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">OTAN</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Catégorie</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Rendu</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($gradesUs as $g): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-mono text-sm"><?= htmlspecialchars($g['code']) ?></td>
                <td class="p-3"><?= htmlspecialchars($g['label_short']) ?></td>
                <td class="p-3"><?= htmlspecialchars($g['label_long']) ?></td>
                <td class="p-3"><?= htmlspecialchars($g['label_otan'] ?? '—') ?></td>
                <td class="p-3 text-slate-600"><?= htmlspecialchars($g['category_label'] ?? '') ?></td>
                <td class="p-3"><?= (int) $g['sort_order'] ?></td>
                <td class="p-3 text-sm text-slate-600">
                    <?php if ($gradeDisplayService): ?>
                    Classique: <?= htmlspecialchars($gradeDisplayService->formatForUser($g, 'classic', 'US')) ?>
                    · OTAN: <?= htmlspecialchars($gradeDisplayService->getOtan($g) ?? '—') ?>
                    · Hybride: <?= htmlspecialchars($gradeDisplayService->getFull($g)) ?>
                    <?php endif; ?>
                </td>
                <td class="p-3">
                    <a href="<?= url('back-office/referentiels/grades/' . $g['id'] . '/edit') ?>" class="text-slate-700 hover:underline text-sm">Modifier</a>
                    <?php if (!empty($g['is_active'])): ?>
                    · <form action="<?= url('back-office/referentiels/grades/' . $g['id'] . '/deactivate') ?>" method="post" class="inline" onsubmit="return confirm('Désactiver ce grade ?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="text-amber-600 hover:underline text-sm">Désactiver</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($gradesUs)): ?>
    <p class="text-slate-500">Aucun grade américain.</p>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($tab === 'otan'): ?>
    <p class="text-slate-600">Les codes OTAN sont définis au niveau de chaque grade (colonne label_otan). Les équivalences sont visibles dans les onglets Grades français et Grades américains.</p>
    <?php endif; ?>

    <?php if ($tab === 'categories'): ?>
    <table class="w-full border border-slate-200 rounded-lg overflow-hidden">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Code</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Libellé</th>
                <th class="text-left p-3 text-xs font-semibold text-slate-600 uppercase">Ordre</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $c): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
                <td class="p-3 font-mono text-sm"><?= htmlspecialchars($c['code']) ?></td>
                <td class="p-3"><?= htmlspecialchars($c['label']) ?></td>
                <td class="p-3"><?= (int) $c['sort_order'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (empty($categories)): ?>
    <p class="text-slate-500">Aucune catégorie.</p>
    <?php endif; ?>
    <?php endif; ?>

    <p class="mt-8 text-sm text-slate-500"><a href="<?= url('back-office') ?>" class="underline">Retour administration organisationnelle</a></p>
</div>
