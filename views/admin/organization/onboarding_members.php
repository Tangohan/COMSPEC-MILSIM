<?php
/** @var list<array<string,mixed>> $onboardingRows */
/** @var array<string,mixed> $onboardingKpis */
$rows = is_array($onboardingRows ?? null) ? $onboardingRows : [];
$kpis = is_array($onboardingKpis ?? null) ? $onboardingKpis : [];

$fmtPct = static function (mixed $v): string {
    $n = is_numeric($v) ? (float) $v : 0.0;

    return number_format(max(0.0, $n), 1, ',', ' ') . ' %';
};
?>
<div class="mx-auto max-w-7xl px-4 py-10">
    <h1 class="text-2xl font-black tracking-tight text-slate-900">Suivi onboarding membres</h1>
    <p class="mt-2 text-sm text-slate-600">Vue staff cross-modules (profil, forum, document essentiel, formation, événement) avec relances contextuelles.</p>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <article class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Complétion onboarding J7</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= htmlspecialchars($fmtPct($kpis['j7_completion_rate'] ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500">Cohorte: <?= (int) ($kpis['cohort_j7'] ?? 0) ?> membre(s)</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Complétion onboarding J14</p>
            <p class="mt-2 text-2xl font-black text-slate-900"><?= htmlspecialchars($fmtPct($kpis['j14_completion_rate'] ?? 0)) ?></p>
            <p class="mt-1 text-xs text-slate-500">Cohorte: <?= (int) ($kpis['cohort_j14'] ?? 0) ?> membre(s)</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 shadow-sm sm:col-span-2 xl:col-span-1">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Activation 3 modules (J0-J14)</p>
            <p class="mt-2 text-2xl font-black text-emerald-900"><?= htmlspecialchars($fmtPct($kpis['cross_modules_rate'] ?? 0)) ?></p>
            <p class="mt-1 text-xs text-emerald-800">Cohorte: <?= (int) ($kpis['cohort_cross'] ?? 0) ?> membre(s)</p>
        </article>
    </section>

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-bold text-slate-900">Nouveaux membres (30 jours)</h2>
        </div>
        <?php if ($rows === []): ?>
            <p class="px-4 py-6 text-sm text-slate-600">Aucun membre récent à afficher.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-600">
                    <tr>
                        <th class="px-4 py-3 text-left">Membre</th>
                        <th class="px-4 py-3 text-left">Plan</th>
                        <th class="px-4 py-3 text-left">Progression</th>
                        <th class="px-4 py-3 text-left">Modules actifs</th>
                        <th class="px-4 py-3 text-left">Ancienneté</th>
                        <th class="px-4 py-3 text-left">Nudge</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $pct = (int) ($row['percent'] ?? 0);
                        $done = (int) ($row['completed_count'] ?? 0);
                        $tot = (int) ($row['total_count'] ?? 0);
                        ?>
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars(trim((string) ($row['display_name'] ?? '')) !== '' ? (string) $row['display_name'] : 'Membre #' . (int) ($row['user_id'] ?? 0)) ?></p>
                                <p class="text-xs text-slate-500"><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-bold uppercase tracking-wide text-slate-700"><?= htmlspecialchars((string) ($row['plan'] ?? 'membre')) ?></span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="h-2 w-36 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-emerald-500" style="width: <?= max(0, min(100, $pct)) ?>%"></div>
                                </div>
                                <p class="mt-1 text-xs text-slate-600"><?= $done ?>/<?= $tot ?> (<?= $pct ?>%)</p>
                            </td>
                            <td class="px-4 py-3 text-slate-700"><?= (int) ($row['modules_done_count'] ?? 0) ?> / 5</td>
                            <td class="px-4 py-3 text-slate-700">J+<?= (int) ($row['age_days'] ?? 0) ?></td>
                            <td class="px-4 py-3 text-xs font-medium <?= ((string) ($row['nudge'] ?? '') === 'RAS') ? 'text-emerald-700' : 'text-amber-800' ?>">
                                <?= htmlspecialchars((string) ($row['nudge'] ?? 'RAS')) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
