<?php

declare(strict_types=1);

/**
 * Espace d'unité › Qualifications : la matrice des qualifications des effectifs (même vue, mêmes cases et même droit
 * que Effectifs › Qualifications), limitée aux membres de l'unité et de ses sous-unités, une page à la fois.
 * Inclus par unit_sheet.php (mêmes variables).
 *
 * @var array{matrix: array<string, mixed>, total: int, truncated: bool, page: int, pages: int} $spaceQualifications
 */
$sq = is_array($spaceQualifications ?? null) ? $spaceQualifications : ['matrix' => [], 'total' => 0, 'truncated' => false, 'page' => 1, 'pages' => 1];
$sqPage = (int) ($sq['page'] ?? 1);
$sqPages = (int) ($sq['pages'] ?? 1);
$sqTotal = (int) ($sq['total'] ?? 0);
$sqNav = static function () use ($h, $unitIdNow, $sqPage, $sqPages): string {
    if ($sqPages < 2) {
        return '';
    }
    $out = '<nav class="flex flex-wrap items-center gap-3" aria-label="Pages des membres">';
    if ($sqPage > 1) {
        $out .= '<a class="ath-btn" rel="prev" href="' . $h(url(\App\Support\UnitSpaceTabs::path($unitIdNow, 'qualifications', $sqPage - 1)) . '#onglet-qualifications') . '">Page précédente</a>';
    }
    $out .= '<span class="text-sm text-slate-600">Page ' . $sqPage . ' sur ' . $sqPages . '</span>';
    if ($sqPage < $sqPages) {
        $out .= '<a class="ath-btn" rel="next" href="' . $h(url(\App\Support\UnitSpaceTabs::path($unitIdNow, 'qualifications', $sqPage + 1)) . '#onglet-qualifications') . '">Page suivante</a>';
    }

    return $out . '</nav>';
};
?>
<section class="space-y-4" aria-labelledby="unit-quals-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="unit-quals-title" class="text-lg font-black uppercase tracking-tight text-slate-900">Qualifications</h2>
        <p class="text-sm text-slate-600"><?= $sqTotal ?> membre<?= $sqTotal > 1 ? 's' : '' ?> dans l’unité et ses sous-unités<?= !empty($sq['truncated']) ? ' (les ' . (int) \App\Support\UnitSpaceTabs::MEMBER_CAP . ' premiers)' : '' ?></p>
    </div>
    <?php if ($sqTotal === 0): ?>
        <p class="rounded-3xl border border-slate-200 bg-white p-6 text-sm text-slate-600">Aucun membre actif n’est rattaché à cette unité.</p>
    <?php else: ?>
        <?= $sqNav() ?>
        <?= \App\Core\Response::view('admin.effectifs_workspace.qualifications', ['qualificationMatrix' => $sq['matrix'], 'qmxEmbedded' => true])->body() ?>
        <?= $sqNav() ?>
    <?php endif; ?>
</section>
