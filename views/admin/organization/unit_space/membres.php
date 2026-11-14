<?php

declare(strict_types=1);

/**
 * Espace d'unité › Membres : les membres actifs de l'unité et de ses sous-unités, une page à la fois.
 * Mêmes données et même périmètre que le tableur des effectifs filtré sur l'unité (`?unite=`), dont il reprend le droit.
 * Inclus par unit_sheet.php (mêmes variables).
 *
 * @var array{rows: list<array<string, mixed>>, total: int, truncated: bool, page: int, pages: int, scope_units: int} $spaceMembers
 */
$sm = is_array($spaceMembers ?? null) ? $spaceMembers : ['rows' => [], 'total' => 0, 'truncated' => false, 'page' => 1, 'pages' => 1, 'scope_units' => 1];
$smRows = is_array($sm['rows'] ?? null) ? $sm['rows'] : [];
$smTotal = (int) ($sm['total'] ?? 0);
$smPage = (int) ($sm['page'] ?? 1);
$smPages = (int) ($sm['pages'] ?? 1);
$smScope = (int) ($sm['scope_units'] ?? 1);
$smRosterUrl = effectifs_workspace_url() . '?unite=' . $unitIdNow;
?>
<section class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4" aria-labelledby="unit-members-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="unit-members-title" class="text-lg font-black uppercase tracking-tight text-slate-900">Membres</h2>
        <a class="ath-btn" href="<?= $h($smRosterUrl) ?>">Ouvrir dans le tableur des effectifs</a>
    </div>
    <?php if ($smRows === []): ?>
        <p class="text-sm text-slate-600">Aucun membre actif n’est rattaché à cette unité<?= $smScope > 1 ? ' ni à ses sous-unités' : '' ?>.</p>
    <?php else: ?>
        <p class="text-sm text-slate-600">
            <?= (int) $smTotal ?> membre<?= $smTotal > 1 ? 's' : '' ?> actif<?= $smTotal > 1 ? 's' : '' ?><?= $smScope > 1 ? ' dans l’unité et ses ' . ($smScope - 1) . ' sous-unité' . ($smScope > 2 ? 's' : '') : '' ?>, par grade.
            Cliquez sur un nom pour ouvrir sa fiche.
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Membres de l’unité</caption>
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th scope="col" class="py-2 pr-4 font-semibold">Membre</th>
                        <th scope="col" class="py-2 pr-4 font-semibold">Grade</th>
                        <th scope="col" class="py-2 pr-4 font-semibold">Unité</th>
                        <th scope="col" class="py-2 font-semibold">Rôle ou emploi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($smRows as $m): ?>
                        <tr>
                            <th scope="row" class="py-2 pr-4 font-semibold text-slate-900">
                                <a class="hover:underline" href="<?= $h(effectifs_workspace_url('membres/' . (int) $m['id'])) ?>" translate="no"><?= $h($m['label']) ?></a>
                                <?php if ($m['name'] !== ''): ?><span class="block text-xs font-normal text-slate-500" translate="no"><?= $h($m['name']) ?></span><?php endif; ?>
                            </th>
                            <td class="py-2 pr-4 text-slate-700"><?= $h($m['grade']) ?></td>
                            <td class="py-2 pr-4 text-slate-700"><?= $h($m['unit']) ?></td>
                            <td class="py-2 text-slate-700"><?= $h($m['role']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($sm['truncated'])): ?>
            <p class="text-sm text-slate-600">Seuls les <?= (int) \App\Support\UnitSpaceTabs::MEMBER_CAP ?> premiers membres sont chargés ici : le tableur des effectifs, filtré sur l’unité, donne la liste complète.</p>
        <?php endif; ?>
        <?php if ($smPages > 1): ?>
            <nav class="flex flex-wrap items-center gap-3" aria-label="Pages des membres">
                <?php if ($smPage > 1): ?>
                    <a class="ath-btn" rel="prev" href="<?= $h(url(\App\Support\UnitSpaceTabs::path($unitIdNow, 'membres', $smPage - 1)) . '#onglet-membres') ?>">Page précédente</a>
                <?php endif; ?>
                <span class="text-sm text-slate-600">Page <?= $smPage ?> sur <?= $smPages ?></span>
                <?php if ($smPage < $smPages): ?>
                    <a class="ath-btn" rel="next" href="<?= $h(url(\App\Support\UnitSpaceTabs::path($unitIdNow, 'membres', $smPage + 1)) . '#onglet-membres') ?>">Page suivante</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
