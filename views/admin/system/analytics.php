<?php
declare(strict_types=1);

use App\Services\Analytics\TenantAnalyticsLabels;

/** @var array{tenants_with_events: int, events_24h: int, top_tenants: list<array{tenant_id: int, name: string, events: int}>} $platformAnalyticsSnapshot */
/** @var list<array{day: string, events: int}> $platformDailyEvents */
/** @var list<array{category: string, events: int}> $platformCategoryBreakdown */
/** @var int $platformAnalyticsDays */
/** @var array<string, mixed> $platformOperationalKpis */
/** @var list<array{name: string, events: int}> $platformTopEventNames */

$snap = $platformAnalyticsSnapshot ?? ['tenants_with_events' => 0, 'events_24h' => 0, 'top_tenants' => []];
$platformDailyEvents = $platformDailyEvents ?? [];
$platformCategoryBreakdown = $platformCategoryBreakdown ?? [];
$platformAnalyticsDays = (int) ($platformAnalyticsDays ?? 7);
$kpis = is_array($platformOperationalKpis ?? null) ? $platformOperationalKpis : [
    'communities_total' => 0,
    'communities_with_active_members' => 0,
    'users_active_total' => 0,
    'users_registered_in_period' => 0,
    'audit_actions_in_period' => 0,
    'enlistments_created_in_period' => 0,
    'forum_topics_in_period' => 0,
    'forum_posts_in_period' => 0,
    'training_enrollments_assigned_in_period' => 0,
    'training_completions_in_period' => 0,
    'usage_events_in_period' => 0,
    'usage_distinct_actors_in_period' => 0,
    'usage_avg_duration_seconds' => null,
];
$platformTopEventNames = is_array($platformTopEventNames ?? null) ? $platformTopEventNames : [];

$fmt = static function (int|float $n): string {
    return number_format((float) $n, 0, ',', "\u{202f}");
};

$periodOpts = [1 => '24 h', 7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
$periodLabel = $periodOpts[$platformAnalyticsDays] ?? ((string) $platformAnalyticsDays . ' jours');

$dailyMax = 0;
$dailyTotal = 0;
foreach ($platformDailyEvents as $de) {
    $c = (int) ($de['events'] ?? 0);
    $dailyMax = max($dailyMax, $c);
    $dailyTotal += $c;
}

$topTenants = is_array($snap['top_tenants'] ?? null) ? $snap['top_tenants'] : [];
$tenantMax = 0;
$tenantTotal = 0;
foreach ($topTenants as $t) {
    $c = (int) ($t['events'] ?? 0);
    $tenantMax = max($tenantMax, $c);
    $tenantTotal += $c;
}

$eventMax = 0;
$eventTotal = 0;
foreach ($platformTopEventNames as $row) {
    $c = (int) ($row['events'] ?? 0);
    $eventMax = max($eventMax, $c);
    $eventTotal += $c;
}

$catMax = 0;
$catTotal = 0;
foreach ($platformCategoryBreakdown as $row) {
    $c = (int) ($row['events'] ?? 0);
    $catMax = max($catMax, $c);
    $catTotal += $c;
}

$activityRows = [
    ['group' => 'Communautés', 'label' => 'Communautés enregistrées', 'value' => (int) ($kpis['communities_total'] ?? 0), 'hint' => 'Total sur le portail'],
    ['group' => 'Communautés', 'label' => 'Avec au moins un membre actif', 'value' => (int) ($kpis['communities_with_active_members'] ?? 0), 'hint' => 'Communautés vivantes'],
    ['group' => 'Membres', 'label' => 'Membres actifs', 'value' => (int) ($kpis['users_active_total'] ?? 0), 'hint' => 'Toutes communautés'],
    ['group' => 'Membres', 'label' => 'Nouveaux comptes', 'value' => (int) ($kpis['users_registered_in_period'] ?? 0), 'hint' => 'Sur la période'],
    ['group' => 'Recrutement', 'label' => 'Candidatures déposées', 'value' => (int) ($kpis['enlistments_created_in_period'] ?? 0), 'hint' => 'Sur la période'],
    ['group' => 'Forum', 'label' => 'Sujets créés', 'value' => (int) ($kpis['forum_topics_in_period'] ?? 0), 'hint' => 'Sur la période'],
    ['group' => 'Forum', 'label' => 'Messages publiés', 'value' => (int) ($kpis['forum_posts_in_period'] ?? 0), 'hint' => 'Sur la période'],
    ['group' => 'Formations', 'label' => 'Inscriptions aux parcours', 'value' => (int) ($kpis['training_enrollments_assigned_in_period'] ?? 0), 'hint' => 'Sur la période'],
    ['group' => 'Formations', 'label' => 'Parcours terminés', 'value' => (int) ($kpis['training_completions_in_period'] ?? 0), 'hint' => 'Sur la période', 'accent' => true],
    ['group' => 'Pilotage', 'label' => 'Écritures au journal d’activité', 'value' => (int) ($kpis['audit_actions_in_period'] ?? 0), 'hint' => 'Sur la période'],
    ['group' => 'Usage', 'label' => 'Actions mesurées', 'value' => (int) ($kpis['usage_events_in_period'] ?? 0), 'hint' => 'Suivi d’audience'],
    ['group' => 'Usage', 'label' => 'Membres distincts mesurés', 'value' => (int) ($kpis['usage_distinct_actors_in_period'] ?? 0), 'hint' => 'Suivi d’audience'],
];

$avgDuration = isset($kpis['usage_avg_duration_seconds']) && $kpis['usage_avg_duration_seconds'] !== null
    ? (int) round((float) $kpis['usage_avg_duration_seconds'])
    : null;

$priorityRows = [
    [
        'title' => 'Visibilité de l’usage',
        'summary' => 'Comprendre comment le portail est utilisé au quotidien.',
        'signal' => (int) ($kpis['usage_events_in_period'] ?? 0),
        'signal_label' => 'Actions mesurées',
        'status' => ((int) ($kpis['usage_events_in_period'] ?? 0) > 0) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Contrôle des droits',
        'summary' => 'Garder une trace des actions sensibles d’administration.',
        'signal' => (int) ($kpis['audit_actions_in_period'] ?? 0),
        'signal_label' => 'Écritures journal',
        'status' => ((int) ($kpis['audit_actions_in_period'] ?? 0) >= 10) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Flux recrutements & formations',
        'summary' => 'Suivre les inscriptions et candidatures en cours.',
        'signal' => (int) ($kpis['enlistments_created_in_period'] ?? 0) + (int) ($kpis['training_enrollments_assigned_in_period'] ?? 0),
        'signal_label' => 'Flux enregistrés',
        'status' => (((int) ($kpis['enlistments_created_in_period'] ?? 0) + (int) ($kpis['training_enrollments_assigned_in_period'] ?? 0)) > 0) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Adoption par les membres',
        'summary' => 'Nombre de personnes distinctes ayant généré une mesure.',
        'signal' => (int) ($kpis['usage_distinct_actors_in_period'] ?? 0),
        'signal_label' => 'Personnes mesurées',
        'status' => ((int) ($kpis['usage_distinct_actors_in_period'] ?? 0) > 0) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Vigilance communauté',
        'summary' => 'Croiser activité forum et journal pour détecter les zones à surveiller.',
        'signal' => (int) ($kpis['audit_actions_in_period'] ?? 0) + (int) ($kpis['forum_posts_in_period'] ?? 0),
        'signal_label' => 'Signaux croisés',
        'status' => (((int) ($kpis['audit_actions_in_period'] ?? 0) + (int) ($kpis['forum_posts_in_period'] ?? 0)) > 0) ? 'watch' : 'risk',
    ],
];

$statusUi = [
    'ok' => ['label' => 'En place', 'dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
    'watch' => ['label' => 'À consolider', 'dot' => 'bg-amber-500', 'chip' => 'bg-amber-50 text-amber-900 ring-amber-200'],
    'risk' => ['label' => 'À initialiser', 'dot' => 'bg-rose-500', 'chip' => 'bg-rose-50 text-rose-800 ring-rose-200'],
];

$share = static function (int $value, int $total): string {
    if ($total < 1) {
        return '—';
    }

    return number_format(100 * $value / $total, 1, ',', "\u{202f}") . "\u{00a0}%";
};

$barPct = static function (int $value, int $max): int {
    if ($max < 1) {
        return 0;
    }

    return max($value > 0 ? 4 : 0, (int) round(100 * $value / $max));
};
?>
<style>
  .sys-an-table { width: 100%; border-collapse: separate; border-spacing: 0; }
  .sys-an-table thead th {
    position: sticky; top: 0; z-index: 1;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.625rem;
    font-weight: 900;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #64748b;
    white-space: nowrap;
  }
  .sys-an-table thead th.num { text-align: right; }
  .sys-an-table tbody td {
    padding: 0.85rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
    font-size: 0.875rem;
    color: #0f172a;
  }
  .sys-an-table tbody tr:last-child td { border-bottom: none; }
  .sys-an-table tbody tr { transition: background 0.12s ease; }
  .sys-an-table tbody tr:hover td { background: #f8fafc; }
  .sys-an-table tbody tr.is-accent td { background: #ecfdf5; }
  .sys-an-table tbody tr.is-accent:hover td { background: #d1fae5; }
  .sys-an-table .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 700; }
  .sys-an-bar {
    height: 0.4rem;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
    min-width: 5rem;
  }
  .sys-an-bar > span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #059669, #34d399);
  }
  .sys-an-bar--indigo > span { background: linear-gradient(90deg, #4f46e5, #818cf8); }
  .sys-an-bar--sky > span { background: linear-gradient(90deg, #0284c7, #38bdf8); }
  .sys-an-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.65rem;
    height: 1.65rem;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 900;
    background: #f1f5f9;
    color: #475569;
  }
  .sys-an-rank--1 { background: #fef3c7; color: #92400e; }
  .sys-an-rank--2 { background: #e2e8f0; color: #334155; }
  .sys-an-rank--3 { background: #ffedd5; color: #9a3412; }
  .sys-an-scroll { max-height: min(28rem, 60vh); overflow: auto; }
</style>

<div class="w-full px-4 py-8 sm:px-6 lg:px-8 xl:px-10">
    <header class="mb-8 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-700">Athena · Administration site</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-900">Indicateurs transverses</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                Lecture consolidée de l’activité sur tout le portail — communautés, membres, forum, formations et mesures d’usage.
                Les durées moyennes ne concernent que les visites où une mesure d’audience a été acceptée.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex flex-wrap items-center gap-1 rounded-2xl border border-slate-200 bg-white p-1 shadow-sm" role="group" aria-label="Période d’analyse">
                <?php foreach ($periodOpts as $d => $label):
                    $active = $d === $platformAnalyticsDays;
                    ?>
                    <a
                        href="<?= htmlspecialchars(url('admin/analytics') . '?days=' . $d, ENT_QUOTES, 'UTF-8') ?>"
                        class="rounded-xl px-3 py-2 text-xs font-bold transition <?= $active ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>"
                        <?= $active ? 'aria-current="page"' : '' ?>
                    ><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </div>
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm hover:border-slate-300 hover:text-slate-900">
                Tableau de bord
            </a>
        </div>
    </header>

    <section class="mb-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Synthèse rapide">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Mesures · 24 h</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-slate-900"><?= htmlspecialchars($fmt((int) $snap['events_24h']), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-slate-500">Actions mesurées hier et aujourd’hui</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Communautés mesurées</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-slate-900"><?= htmlspecialchars($fmt((int) $snap['tenants_with_events']), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-slate-500">Au moins une mesure sur <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Membres actifs</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-slate-900"><?= htmlspecialchars($fmt((int) ($kpis['users_active_total'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-slate-500">Toutes communautés confondues</p>
        </article>
        <article class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-800">Parcours terminés</p>
            <p class="mt-2 text-3xl font-black tabular-nums text-emerald-950"><?= htmlspecialchars($fmt((int) ($kpis['training_completions_in_period'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="mt-1 text-xs text-emerald-900/70">Sur <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
        </article>
    </section>

    <section class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-800">Activité enregistrée</h2>
                <p class="mt-1 text-xs text-slate-500">Comptages sur <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?> — indépendants du suivi d’audience optionnel.</p>
            </div>
            <?php if ($avgDuration !== null): ?>
                <p class="text-xs font-semibold text-slate-600">Durée moyenne mesurée&nbsp;: <span class="tabular-nums text-slate-900"><?= (int) $avgDuration ?>&nbsp;s</span></p>
            <?php endif; ?>
        </div>
        <div class="sys-an-scroll">
            <table class="sys-an-table">
                <thead>
                    <tr>
                        <th>Domaine</th>
                        <th>Indicateur</th>
                        <th class="num">Valeur</th>
                        <th>Répartition relative</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $actMax = 0;
                    foreach ($activityRows as $ar) {
                        $actMax = max($actMax, (int) $ar['value']);
                    }
                    $prevGroup = null;
                    foreach ($activityRows as $ar):
                        $val = (int) $ar['value'];
                        $pct = $barPct($val, $actMax);
                        $showGroup = $prevGroup !== $ar['group'];
                        $prevGroup = $ar['group'];
                        ?>
                        <tr class="<?= !empty($ar['accent']) ? 'is-accent' : '' ?>">
                            <td class="w-[9rem]">
                                <?php if ($showGroup): ?>
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-slate-600"><?= htmlspecialchars($ar['group'], ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars($ar['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-0.5 text-[11px] text-slate-500"><?= htmlspecialchars($ar['hint'], ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="num text-base"><?= htmlspecialchars($fmt($val), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="w-[40%]">
                                <div class="sys-an-bar" title="<?= (int) $pct ?>&nbsp;% du maximum de cette liste">
                                    <span style="width: <?= (int) $pct ?>%"></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="mb-8 grid gap-8 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-800">Communautés les plus actives</h2>
                <p class="mt-1 text-xs text-slate-500">Classement par actions mesurées · <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="sys-an-scroll">
                <table class="sys-an-table">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Communauté</th>
                            <th class="num">Actions</th>
                            <th class="num">Part</th>
                            <th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($topTenants === []): ?>
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">Aucune mesure sur cette période.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topTenants as $i => $t):
                            $rank = $i + 1;
                            $val = (int) ($t['events'] ?? 0);
                            $pct = $barPct($val, $tenantMax);
                            $rankCls = $rank <= 3 ? 'sys-an-rank sys-an-rank--' . $rank : 'sys-an-rank';
                            ?>
                            <tr>
                                <td><span class="<?= htmlspecialchars($rankCls, ENT_QUOTES, 'UTF-8') ?>"><?= (int) $rank ?></span></td>
                                <td class="font-semibold"><?= htmlspecialchars((string) ($t['name'] ?? 'Communauté'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= htmlspecialchars($fmt($val), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num text-slate-500 font-semibold"><?= htmlspecialchars($share($val, $tenantTotal), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="w-[30%]">
                                    <div class="sys-an-bar sys-an-bar--indigo"><span style="width: <?= (int) $pct ?>%"></span></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-800">Volume jour par jour</h2>
                <p class="mt-1 text-xs text-slate-500">
                    <?= htmlspecialchars($fmt($dailyTotal), ENT_QUOTES, 'UTF-8') ?> actions au total
                    <?php if ($dailyMax > 0): ?>
                        · pic <?= htmlspecialchars($fmt($dailyMax), ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($platformDailyEvents === []): ?>
                <p class="px-5 py-10 text-center text-sm text-slate-500">Aucune donnée à afficher.</p>
            <?php else: ?>
                <div class="border-b border-slate-100 px-4 pb-2 pt-4">
                    <div class="flex h-28 items-end gap-1 px-1" role="img" aria-label="Histogramme des actions mesurées par jour">
                        <?php foreach ($platformDailyEvents as $de):
                            $cnt = (int) ($de['events'] ?? 0);
                            $barPx = $dailyMax > 0 ? max($cnt > 0 ? 3 : 0, (int) round(100 * $cnt / $dailyMax)) : 0;
                            $dayRaw = (string) ($de['day'] ?? '');
                            $dayLabel = $dayRaw !== '' ? date('d/m', strtotime($dayRaw)) : '—';
                            ?>
                            <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end">
                                <div class="w-full max-w-[18px] rounded-t bg-indigo-500/90" style="height: <?= (int) $barPx ?>px" title="<?= (int) $cnt ?> le <?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?>"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="sys-an-scroll">
                    <table class="sys-an-table">
                        <thead>
                            <tr>
                                <th>Jour</th>
                                <th class="num">Actions</th>
                                <th class="num">Part</th>
                                <th>Volume</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_reverse($platformDailyEvents) as $de):
                                $cnt = (int) ($de['events'] ?? 0);
                                $dayRaw = (string) ($de['day'] ?? '');
                                $dayFull = $dayRaw !== '' ? date('d/m/Y', strtotime($dayRaw)) : '—';
                                $pct = $barPct($cnt, $dailyMax);
                                ?>
                                <tr>
                                    <td class="font-semibold"><?= htmlspecialchars($dayFull, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num"><?= htmlspecialchars($fmt($cnt), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="num text-slate-500 font-semibold"><?= htmlspecialchars($share($cnt, $dailyTotal), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="w-[35%]">
                                        <div class="sys-an-bar sys-an-bar--indigo"><span style="width: <?= (int) $pct ?>%"></span></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="mb-8 grid gap-8 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-800">Actions les plus fréquentes</h2>
                <p class="mt-1 text-xs text-slate-500">Détail du suivi d’usage · <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="sys-an-scroll">
                <table class="sys-an-table">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Type d’action</th>
                            <th class="num">Occurrences</th>
                            <th class="num">Part</th>
                            <th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($platformTopEventNames === []): ?>
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">Aucune action recensée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($platformTopEventNames as $i => $row):
                            $rank = $i + 1;
                            $val = (int) ($row['events'] ?? 0);
                            $pct = $barPct($val, $eventMax);
                            $rankCls = $rank <= 3 ? 'sys-an-rank sys-an-rank--' . $rank : 'sys-an-rank';
                            ?>
                            <tr>
                                <td><span class="<?= htmlspecialchars($rankCls, ENT_QUOTES, 'UTF-8') ?>"><?= (int) $rank ?></span></td>
                                <td class="font-medium"><?= htmlspecialchars(TenantAnalyticsLabels::eventNameLabel((string) ($row['name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= htmlspecialchars($fmt($val), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num text-slate-500 font-semibold"><?= htmlspecialchars($share($val, $eventTotal), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="w-[28%]">
                                    <div class="sys-an-bar"><span style="width: <?= (int) $pct ?>%"></span></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-800">Volume par rubrique</h2>
                <p class="mt-1 text-xs text-slate-500">Répartition des mesures · <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <div class="sys-an-scroll">
                <table class="sys-an-table">
                    <thead>
                        <tr>
                            <th>Rubrique</th>
                            <th class="num">Actions</th>
                            <th class="num">Part</th>
                            <th>Volume</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($platformCategoryBreakdown === []): ?>
                        <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">Aucune rubrique détectée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($platformCategoryBreakdown as $row):
                            $val = (int) ($row['events'] ?? 0);
                            $pct = $barPct($val, $catMax);
                            $catKey = (string) ($row['category'] ?? '');
                            ?>
                            <tr>
                                <td class="font-semibold"><?= htmlspecialchars(TenantAnalyticsLabels::categoryLabel($catKey), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num"><?= htmlspecialchars($fmt($val), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="num text-slate-500 font-semibold"><?= htmlspecialchars($share($val, $catTotal), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="w-[35%]">
                                    <div class="sys-an-bar sys-an-bar--sky"><span style="width: <?= (int) $pct ?>%"></span></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-800">Priorités de pilotage</h2>
            <p class="mt-1 text-xs text-slate-500">Lecture rapide des chantiers à consolider, d’après les signaux de la période.</p>
        </div>
        <div class="sys-an-scroll">
            <table class="sys-an-table">
                <thead>
                    <tr>
                        <th>Priorité</th>
                        <th>Signal</th>
                        <th class="num">Valeur</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($priorityRows as $row):
                        $st = (string) ($row['status'] ?? 'watch');
                        $ui = $statusUi[$st] ?? $statusUi['watch'];
                        ?>
                        <tr>
                            <td>
                                <p class="font-semibold text-slate-900"><?= htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                <p class="mt-0.5 max-w-md text-[11px] text-slate-500"><?= htmlspecialchars((string) $row['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                            </td>
                            <td class="text-slate-600"><?= htmlspecialchars((string) $row['signal_label'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="num text-base"><?= htmlspecialchars($fmt((int) $row['signal']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset <?= htmlspecialchars($ui['chip'], ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="h-1.5 w-1.5 rounded-full <?= htmlspecialchars($ui['dot'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></span>
                                    <?= htmlspecialchars($ui['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
