<?php

declare(strict_types=1);

/**
 * Espace d'unité › Chaîne de commandement : la chaîne au-dessus de l'unité, le chef de l'unité et de ses sous-unités,
 * les écarts d'adéquation. Données de la page Effectifs › Chaîne de commandement (mêmes services), restreintes à l'unité ;
 * la désignation des chefs se fait toujours sur cette page, avec son droit (canManageAssignments).
 * Inclus par unit_sheet.php (mêmes variables).
 *
 * @var array{ancestors: list<array<string, mixed>>, rows: list<array<string, mixed>>, missing: int, unit_adequacy: array<string, mixed>|null, issue_counts: array<int, int>} $spaceChain
 */
$sc = is_array($spaceChain ?? null) ? $spaceChain : ['ancestors' => [], 'rows' => [], 'missing' => 0, 'unit_adequacy' => null, 'issue_counts' => []];
$scAncestors = is_array($sc['ancestors'] ?? null) ? $sc['ancestors'] : [];
$scRows = is_array($sc['rows'] ?? null) ? $sc['rows'] : [];
$scMissing = (int) ($sc['missing'] ?? 0);
$scIssueCounts = is_array($sc['issue_counts'] ?? null) ? $sc['issue_counts'] : [];
$scAdequacy = is_array($sc['unit_adequacy'] ?? null) ? $sc['unit_adequacy'] : null;
$scCanManage = !empty($canManageAssignments);
?>
<section class="rounded-3xl border border-slate-200 bg-white p-6 space-y-4" aria-labelledby="unit-chain-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="unit-chain-title" class="text-lg font-black uppercase tracking-tight text-slate-900">Chaîne de commandement</h2>
        <a class="ath-btn" href="<?= $h(effectifs_workspace_url('chaine')) ?>"><?= $scCanManage ? 'Désigner les chefs' : 'Ouvrir la chaîne complète' ?></a>
    </div>

    <?php if ($scAncestors !== []): ?>
        <p class="text-sm text-slate-600">
            Au-dessus de l’unité :
            <?php foreach ($scAncestors as $i => $anc): ?>
                <?= $i > 0 ? ' › ' : '' ?><strong class="text-slate-900"><?= $h($anc['name'] ?? '') ?></strong> (<?= $h(($anc['commander_label'] ?? '') !== '' ? $anc['commander_label'] : 'chef à désigner') ?>)
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?php if ($scMissing > 0): ?>
        <p class="text-sm text-amber-700"><?= $scMissing ?> unité<?= $scMissing > 1 ? 's' : '' ?> sans chef désigné dans cette partie de l’organigramme.</p>
    <?php endif; ?>

    <?php if ($scRows === []): ?>
        <p class="text-sm text-slate-600">Cette unité n’apparaît pas dans la chaîne de commandement.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Chef de l’unité et de ses sous-unités</caption>
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500">
                        <th scope="col" class="py-2 pr-4 font-semibold">Unité</th>
                        <th scope="col" class="py-2 pr-4 font-semibold">Chef</th>
                        <th scope="col" class="py-2 pr-4 font-semibold">Effectif</th>
                        <th scope="col" class="py-2 font-semibold">Écarts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($scRows as $r):
                        $rid = (int) ($r['id'] ?? 0);
                        $issues = (int) ($scIssueCounts[$rid] ?? 0);
                        $chief = trim((string) ($r['commander_label'] ?? ''));
                        ?>
                        <tr>
                            <th scope="row" class="py-2 pr-4 font-semibold text-slate-900" style="padding-left: <?= (int) ($r['rel_depth'] ?? 0) * 1.25 ?>rem">
                                <?php if ($rid !== $unitIdNow): ?>
                                    <a class="hover:underline" href="<?= $h(url(\App\Support\UnitSpaceTabs::path($rid, 'chaine')) . '#onglet-chaine') ?>"><?= $h($r['name'] ?? '') ?></a>
                                <?php else: ?>
                                    <?= $h($r['name'] ?? '') ?>
                                <?php endif; ?>
                            </th>
                            <td class="py-2 pr-4 <?= $chief === '' ? 'text-amber-700' : 'text-slate-700' ?>" translate="no"><?= $chief !== '' ? $h($chief) : 'À désigner' ?></td>
                            <td class="py-2 pr-4 text-slate-700"><?= (int) ($r['member_count'] ?? 0) ?></td>
                            <td class="py-2 <?= $issues > 0 ? 'text-amber-700' : 'text-slate-700' ?>"><?= $issues > 0 ? $issues . ' écart' . ($issues > 1 ? 's' : '') : 'Aucun' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($scAdequacy !== null):
        require_once base_path('views/admin/effectifs_workspace/partials/adequacy.php');
        $scIssues = is_array($scAdequacy['issues_list'] ?? null) ? $scAdequacy['issues_list'] : [];
        ?>
        <?= adq_styles() ?>
        <div class="adq-scope space-y-3" id="adequation-chaine">
            <p class="text-sm text-slate-600">
                Adéquation de l’unité :
                <?= $scIssues === [] ? 'chef, adjoint et effectif correspondent à ce que l’échelon attend.' : count($scIssues) . ' écart' . (count($scIssues) > 1 ? 's' : '') . ' à examiner.' ?>
            </p>
            <?php if ($scIssues !== []): ?>
                <?= adq_issue_list($scIssues, is_array($scAdequacy['people'] ?? null) ? $scAdequacy['people'] : [], static fn (int $pid): string => effectifs_workspace_url('membres/' . $pid), $scCanManage) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
