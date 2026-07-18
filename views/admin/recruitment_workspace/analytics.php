<?php
declare(strict_types=1);
$weekly = is_array($weeklyCreated ?? null) ? $weeklyCreated : [];
$via = is_array($submittedViaCounts ?? null) ? $submittedViaCounts : [];
$statusCounts = is_array($enlistmentCounts ?? null) ? $enlistmentCounts : [];
$topOpenings = is_array($topOpenings ?? null) ? $topOpenings : [];
$weeksCount = max(1, (int) ($analyticsWeeksCount ?? 12));
$periodStartRaw = trim((string) ($analyticsPeriodStart ?? ''));
$periodEndRaw = trim((string) ($analyticsPeriodEnd ?? ''));
$generatedAtRaw = trim((string) ($analyticsGeneratedAt ?? ''));
$createdInPeriod = (int) ($analyticsCreatedInPeriod ?? 0);
$nTotal = array_sum($statusCounts);
$nSubmitted = (int) ($statusCounts['submitted'] ?? 0);
$nReviewed = (int) ($statusCounts['reviewed'] ?? 0);
$nRejected = (int) ($statusCounts['rejected'] ?? 0);
$nBlocked = (int) ($statusCounts['blocked'] ?? 0);
$maxWeek = 0;
foreach ($weekly as $w) {
    $maxWeek = max($maxWeek, (int) ($w['c'] ?? 0));
}
$viaTotal = array_sum($via);
$openingMax = 0;
foreach ($topOpenings as $ro) {
    $openingMax = max($openingMax, (int) ($ro['c'] ?? 0));
}

$fmtDate = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $t = strtotime($raw);

    return $t !== false ? date('d/m/Y', $t) : '—';
};
$fmtDateTime = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $t = strtotime($raw);

    return $t !== false ? date('d/m/Y à H:i', $t) : '—';
};
$fmtWeekRange = static function (string $weekStart): string {
    $weekStart = trim($weekStart);
    if ($weekStart === '') {
        return '—';
    }
    try {
        $start = new DateTimeImmutable($weekStart);
        $end = $start->modify('+6 days');

        return $start->format('d/m') . ' → ' . $end->format('d/m/Y');
    } catch (Exception) {
        return $weekStart;
    }
};
$fmtWeekShort = static function (string $weekStart): string {
    $weekStart = trim($weekStart);
    if ($weekStart === '') {
        return '—';
    }
    $t = strtotime($weekStart);

    return $t !== false ? date('d/m', $t) : '—';
};
$viaLabel = static function (string $k): string {
    return match ($k) {
        'guest' => 'Sans compte (invité)',
        'account', 'user' => 'Compte membre',
        default => $k !== '' ? $k : 'Autre',
    };
};
$periodLabel = $fmtDate($periodStartRaw) . ' – ' . $fmtDate($periodEndRaw);
?>
<div class="max-w-7xl w-full space-y-8" style="--rw-athena: #059669;">
        <div class="lms-infobanner" role="note">
            <span class="lms-infobanner__icon" aria-hidden="true">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </span>
            <p><strong>Tableau de bord candidatures.</strong> Synthèse calculée à partir des dossiers de cette communauté — pour compléter la <a href="<?= htmlspecialchars(recruitment_workspace_url(), ENT_QUOTES, 'UTF-8') ?>" class="font-semibold text-[#059669] hover:underline">vue d’ensemble</a>.</p>
        </div>

        <header class="lms-panel relative overflow-hidden rounded-[2rem] border border-emerald-200/70 p-6 md:p-8">
            <div class="absolute top-0 left-0 h-[3px] w-full bg-gradient-to-r from-[#059669] via-emerald-400/70 to-transparent" aria-hidden="true"></div>
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="min-w-0">
                    <p class="mb-3 text-[9px] font-black uppercase tracking-[0.45em] text-[#059669]">Pilotage · Indicateurs</p>
                    <h1 class="text-3xl font-black uppercase leading-tight tracking-tight text-slate-900 md:text-4xl">Analyses candidatures</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-stone-600">
                        Volumes, canaux de dépôt et offres les plus sollicitées sur la période affichée.
                    </p>
                </div>
                <div class="grid shrink-0 gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/60 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-emerald-900/70">Période d’analyse</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-emerald-950"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-0.5 text-[11px] text-emerald-900/65"><?= (int) $weeksCount ?> semaines glissantes</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Dernière actualisation</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-slate-900"><?= htmlspecialchars($fmtDateTime($generatedAtRaw), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="mt-0.5 text-[11px] text-stone-500">Heure du serveur</p>
                    </div>
                </div>
            </div>
        </header>

        <section aria-label="Indicateurs clés" class="grid grid-cols-2 gap-3 xl:grid-cols-6">
            <article class="lms-panel rounded-2xl border border-slate-200/90 p-4 md:p-5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Total dossiers</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-900 md:text-3xl"><?= (int) $nTotal ?></p>
                <p class="mt-1 text-[11px] text-stone-500">Tous statuts confondus</p>
            </article>
            <article class="lms-panel rounded-2xl border border-amber-200/90 bg-amber-50/40 p-4 md:p-5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-900/70">À traiter</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-amber-950 md:text-3xl"><?= $nSubmitted ?></p>
                <p class="mt-1 text-[11px] text-amber-900/60">File en attente</p>
            </article>
            <article class="lms-panel rounded-2xl border border-emerald-200/90 bg-emerald-50/50 p-4 md:p-5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-900/70">Acceptées</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-emerald-950 md:text-3xl"><?= $nReviewed ?></p>
                <p class="mt-1 text-[11px] text-emerald-900/60">Décision favorable</p>
            </article>
            <article class="lms-panel rounded-2xl border border-rose-200/80 bg-rose-50/40 p-4 md:p-5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-rose-800/70">Refusées</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-rose-950 md:text-3xl"><?= $nRejected ?></p>
                <p class="mt-1 text-[11px] text-rose-800/55">Décision défavorable</p>
            </article>
            <article class="lms-panel rounded-2xl border border-stone-300/80 bg-stone-50/70 p-4 md:p-5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-stone-600">Non admis</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-stone-900 md:text-3xl"><?= $nBlocked ?></p>
                <p class="mt-1 text-[11px] text-stone-500">Accès non ouvert</p>
            </article>
            <article class="lms-panel rounded-2xl border border-emerald-300/80 bg-emerald-50/70 p-4 md:p-5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#059669]">Créées sur la période</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-[#047857] md:text-3xl"><?= $createdInPeriod ?></p>
                <p class="mt-1 text-[11px] text-emerald-900/60"><?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </article>
        </section>

        <section class="lms-panel rounded-[2rem] border border-slate-200/80 p-5 md:p-7" aria-labelledby="rw-analytics-volume-heading">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.35em] text-[#059669]">Graphique</p>
                    <h2 id="rw-analytics-volume-heading" class="mt-1 text-sm font-black uppercase tracking-[0.14em] text-slate-900">Volume hebdomadaire</h2>
                    <p class="mt-1 text-xs text-stone-600">
                        Arrivées de dossiers par semaine (lundi → dimanche). Hauteur proportionnelle au maximum de la période
                        <?php if ($maxWeek > 0): ?>
                            (pic&nbsp;: <span class="font-semibold tabular-nums text-slate-800"><?= $maxWeek ?></span>).
                        <?php else: ?>
                            .
                        <?php endif; ?>
                    </p>
                </div>
                <p class="shrink-0 rounded-xl border border-emerald-200/80 bg-emerald-50/50 px-3 py-2 text-[11px] font-semibold text-emerald-950">
                    Du <?= htmlspecialchars($fmtDate($periodStartRaw), ENT_QUOTES, 'UTF-8') ?>
                    au <?= htmlspecialchars($fmtDate($periodEndRaw), ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
            <?php if ($weekly === []): ?>
                <p class="text-sm text-stone-600">Aucun volume à afficher pour cette période.</p>
            <?php else: ?>
                <div class="flex h-44 items-end gap-1 border-b border-stone-200 pb-1 sm:gap-1.5 md:h-52">
                    <?php foreach ($weekly as $row): ?>
                        <?php
                        $c = (int) ($row['c'] ?? 0);
                        $ws = (string) ($row['week_start'] ?? '');
                        $pct = $maxWeek > 0 ? (int) round(100 * $c / $maxWeek) : 0;
                        $pct = max($c > 0 ? 8 : 0, $pct);
                        $rangeLabel = $fmtWeekRange($ws);
                        $title = $c . ' dossier' . ($c > 1 ? 's' : '') . ' — semaine du ' . $rangeLabel;
                        ?>
                        <div class="flex min-w-0 flex-1 flex-col items-center gap-1.5" title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="text-[10px] font-bold tabular-nums text-slate-700"><?= $c > 0 ? $c : '' ?></span>
                            <div
                                class="w-full max-w-[2.75rem] rounded-t-md bg-gradient-to-t from-[#047857] to-[#059669]"
                                style="height: <?= $pct ?>%; min-height: <?= $c > 0 ? '4px' : '0' ?>"
                                role="img"
                                aria-label="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                            ></div>
                            <span class="w-full truncate text-center text-[9px] font-bold tabular-nums text-stone-500"><?= htmlspecialchars($fmtWeekShort($ws), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 overflow-x-auto rounded-xl border border-stone-200/90 bg-white/70">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-stone-200 bg-stone-50/80 text-[10px] font-black uppercase tracking-wider text-stone-500">
                                <th class="px-3 py-2.5">Semaine (lundi → dimanche)</th>
                                <th class="px-3 py-2.5 text-right">Candidatures</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($weekly) as $row): ?>
                                <?php
                                $c = (int) ($row['c'] ?? 0);
                                $ws = (string) ($row['week_start'] ?? '');
                                ?>
                                <tr class="border-b border-stone-100 last:border-0">
                                    <td class="px-3 py-2 text-stone-800"><?= htmlspecialchars($fmtWeekRange($ws), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-slate-900"><?= $c ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
            <section class="lms-panel rounded-[2rem] border border-slate-200/80 p-5 md:p-7" aria-labelledby="rw-analytics-via-heading">
                <p class="text-[9px] font-black uppercase tracking-[0.35em] text-[#059669]">Répartition</p>
                <h2 id="rw-analytics-via-heading" class="mt-1 text-sm font-black uppercase tracking-[0.14em] text-slate-900">Canal de dépôt</h2>
                <p class="mt-1 mb-4 text-xs text-stone-600">Comment les dossiers ont été transmis (tous historiques confondus).</p>
                <?php if ($via === []): ?>
                    <p class="text-sm text-stone-600">Indicateur non disponible sur cette communauté pour le moment.</p>
                <?php else: ?>
                    <ul class="space-y-3">
                        <?php foreach ($via as $k => $n): ?>
                            <?php
                            $n = (int) $n;
                            $pctVia = $viaTotal > 0 ? (int) round(100 * $n / $viaTotal) : 0;
                            ?>
                            <li>
                                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                                    <span class="font-medium text-stone-800"><?= htmlspecialchars($viaLabel((string) $k), ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="shrink-0 tabular-nums font-bold text-[#047857]"><?= $n ?> <span class="font-semibold text-stone-400">(<?= $pctVia ?>&nbsp;%)</span></span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                    <div class="h-full rounded-full bg-[#059669]" style="width: <?= max($n > 0 ? 4 : 0, $pctVia) ?>%"></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="lms-panel rounded-[2rem] border border-slate-200/80 p-5 md:p-7" aria-labelledby="rw-analytics-openings-heading">
                <p class="text-[9px] font-black uppercase tracking-[0.35em] text-[#059669]">Classement</p>
                <h2 id="rw-analytics-openings-heading" class="mt-1 text-sm font-black uppercase tracking-[0.14em] text-slate-900">Offres les plus sollicitées</h2>
                <p class="mt-1 mb-4 text-xs text-stone-600">Volumes liés aux offres publiées (historique complet).</p>
                <?php if ($topOpenings === []): ?>
                    <p class="text-sm text-stone-600">Aucune offre liée pour le moment, ou module offres non déployé.</p>
                <?php else: ?>
                    <ol class="space-y-2.5">
                        <?php foreach ($topOpenings as $idx => $ro): ?>
                            <?php
                            $c = (int) ($ro['c'] ?? 0);
                            $pctOp = $openingMax > 0 ? (int) round(100 * $c / $openingMax) : 0;
                            ?>
                            <li class="rounded-xl border border-stone-100 bg-stone-50/50 px-3 py-2.5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <span class="mr-2 inline-flex h-5 w-5 items-center justify-center rounded-md bg-[#059669]/10 text-[10px] font-black tabular-nums text-[#047857]"><?= (int) $idx + 1 ?></span>
                                        <span class="text-sm font-semibold text-stone-900"><?= htmlspecialchars((string) ($ro['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <span class="shrink-0 text-sm font-bold tabular-nums text-slate-800"><?= $c ?></span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white" aria-hidden="true">
                                    <div class="h-full rounded-full bg-gradient-to-r from-[#047857] to-[#059669]" style="width: <?= max($c > 0 ? 6 : 0, $pctOp) ?>%"></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>

        <p class="text-center text-[11px] text-stone-500">
            Données arrêtées au <?= htmlspecialchars($fmtDateTime($generatedAtRaw), ENT_QUOTES, 'UTF-8') ?>
            · Période graphique <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>
        </p>
</div>
