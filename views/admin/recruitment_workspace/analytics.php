<?php
declare(strict_types=1);
$weekly = is_array($weeklyCreated ?? null) ? $weeklyCreated : [];
$via = is_array($submittedViaCounts ?? null) ? $submittedViaCounts : [];
$statusCounts = is_array($enlistmentCounts ?? null) ? $enlistmentCounts : [];
$topOpenings = is_array($topOpenings ?? null) ? $topOpenings : [];
$maxWeek = 0;
foreach ($weekly as $w) {
    $maxWeek = max($maxWeek, (int) ($w['c'] ?? 0));
}
$statusLabels = [
    'submitted' => 'À traiter',
    'reviewed' => 'Acceptées',
    'rejected' => 'Refusées',
    'blocked' => 'Non admis',
];
$viaLabel = static function (string $k): string {
    return match ($k) {
        'guest' => 'Sans compte (invité)',
        'account', 'user' => 'Compte membre',
        default => $k !== '' ? $k : 'Autre',
    };
};
require base_path('views/admin/recruitment_workspace/partials/command_shell_open.php');
?>
                <header class="recruitment-cmd-panel">
                    <p class="recruitment-cmd-kicker">Indicateurs</p>
                    <h1 class="recruitment-cmd-title">Analyses candidatures</h1>
                    <p class="text-sm text-stone-600 max-w-2xl leading-relaxed">
                        Synthèses calculées à partir des dossiers enregistrés sur cette communauté (pas d’export fichier dans cette version).
                    </p>
                </header>

                <section class="recruitment-cmd-panel">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-[#1c2d41] mb-4">Répartition par statut</h2>
                    <ul class="grid sm:grid-cols-2 gap-3">
                        <?php foreach ($statusLabels as $key => $lab): ?>
                            <?php $n = (int) ($statusCounts[$key] ?? 0); ?>
                            <li class="rounded-xl border border-stone-200 bg-stone-50/80 px-4 py-3">
                                <p class="text-[10px] font-black uppercase tracking-wider text-stone-500"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-1 text-2xl font-black tabular-nums text-[#1c2d41]"><?= $n ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <?php if ($weekly !== []): ?>
                <section class="recruitment-cmd-panel">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-[#1c2d41] mb-4">Volume hebdomadaire</h2>
                    <p class="text-xs text-stone-600 mb-4">Hauteur proportionnelle au maximum de la période affichée.</p>
                    <div class="flex items-end gap-1 sm:gap-2 h-40 border-b border-stone-200 pb-1">
                        <?php foreach ($weekly as $row): ?>
                            <?php
                            $c = (int) ($row['c'] ?? 0);
                            $pct = $maxWeek > 0 ? (int) round(100 * $c / $maxWeek) : 0;
                            $pct = max($c > 0 ? 8 : 0, $pct);
                            ?>
                            <div class="flex-1 min-w-0 flex flex-col items-center gap-1" title="<?= (int) $c ?> sur la semaine du <?= htmlspecialchars((string) ($row['week_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <div class="w-full max-w-[2.5rem] rounded-t-md bg-gradient-to-t from-[#1c2d41] to-[#c9a227]/90" style="height: <?= $pct ?>%; min-height: <?= $c > 0 ? '4px' : '0' ?>"></div>
                                <span class="text-[9px] font-bold text-stone-500 truncate w-full text-center"><?= htmlspecialchars(mb_substr((string) ($row['week_start'] ?? ''), 5), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <section class="recruitment-cmd-panel">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-[#1c2d41] mb-4">Canal de dépôt</h2>
                    <?php if ($via === []): ?>
                        <p class="text-sm text-stone-600">Indicateur non disponible sur cette base (colonnes compte / invité absentes).</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($via as $k => $n): ?>
                                <li class="flex justify-between gap-3 text-sm border-b border-stone-100 pb-2 last:border-0">
                                    <span><?= htmlspecialchars($viaLabel((string) $k), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="font-mono font-bold"><?= (int) $n ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="recruitment-cmd-panel">
                    <h2 class="text-xs font-black uppercase tracking-[0.2em] text-[#1c2d41] mb-4">Offres liées (volume)</h2>
                    <?php if ($topOpenings === []): ?>
                        <p class="text-sm text-stone-600">Aucune offre liée sur la période ou module offres non déployé.</p>
                    <?php else: ?>
                        <ul class="space-y-2">
                            <?php foreach ($topOpenings as $ro): ?>
                                <li class="flex flex-wrap justify-between gap-2 text-sm border-b border-stone-100 pb-2 last:border-0">
                                    <span class="font-medium text-stone-800"><?= htmlspecialchars((string) ($ro['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-stone-600 tabular-nums"><?= (int) ($ro['c'] ?? 0) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

<?php require base_path('views/admin/recruitment_workspace/partials/command_shell_close.php'); ?>
