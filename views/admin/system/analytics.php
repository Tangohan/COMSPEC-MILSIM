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

$periodLinks = static function (int $current): string {
    $opts = [1 => '24 h', 7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
    $base = url('admin/analytics');
    $parts = [];
    foreach ($opts as $d => $label) {
        $active = $d === $current ? ' font-black text-emerald-700' : ' text-slate-600 hover:text-slate-900';
        $parts[] = '<a class="text-sm' . $active . '" href="' . htmlspecialchars($base . '?days=' . $d, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return implode('<span class="text-slate-300 mx-2">|</span>', $parts);
};

$dailyMax = 0;
foreach ($platformDailyEvents as $de) {
    $dailyMax = max($dailyMax, (int) ($de['events'] ?? 0));
}

$transverseTracks = [
    [
        'title' => 'Observabilité produit + technique',
        'summary' => 'Normaliser les événements d’usage et piloter la santé plateforme (erreurs, latence, jobs, emails, cron).',
        'kpi_label' => 'Événements de suivi (période)',
        'kpi_value' => (int) ($kpis['usage_events_in_period'] ?? 0),
        'target_label' => 'Traçabilité active',
        'status' => ((int) ($kpis['usage_events_in_period'] ?? 0) > 0) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Gouvernance des permissions',
        'summary' => 'Auditer les droits sensibles et recertifier régulièrement les rôles critiques.',
        'kpi_label' => 'Écritures journal d’audit (période)',
        'kpi_value' => (int) ($kpis['audit_actions_in_period'] ?? 0),
        'target_label' => 'Contrôle continu',
        'status' => ((int) ($kpis['audit_actions_in_period'] ?? 0) >= 10) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Fiabilité des workflows asynchrones',
        'summary' => 'Sécuriser retries, idempotence, dead-letter queue et alerting sur échecs répétés.',
        'kpi_label' => 'Relances opérationnelles',
        'kpi_value' => (int) ($kpis['enlistments_created_in_period'] ?? 0) + (int) ($kpis['training_enrollments_assigned_in_period'] ?? 0),
        'target_label' => 'Flux suivis',
        'status' => (((int) ($kpis['enlistments_created_in_period'] ?? 0) + (int) ($kpis['training_enrollments_assigned_in_period'] ?? 0)) > 0) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Qualité UX et design system léger',
        'summary' => 'Standardiser feedback (succès/erreur/vide/chargement) et harmoniser la terminologie produit.',
        'kpi_label' => 'Actions utilisateurs distinctes',
        'kpi_value' => (int) ($kpis['usage_distinct_actors_in_period'] ?? 0),
        'target_label' => 'Adoption transverse',
        'status' => ((int) ($kpis['usage_distinct_actors_in_period'] ?? 0) > 0) ? 'ok' : 'watch',
    ],
    [
        'title' => 'Stratégie anti-abus et confiance',
        'summary' => 'Déployer scores de risque, throttling progressif et mode dégradé sécurisé.',
        'kpi_label' => 'Signal de vigilance',
        'kpi_value' => (int) ($kpis['audit_actions_in_period'] ?? 0) + (int) ($kpis['forum_posts_in_period'] ?? 0),
        'target_label' => 'Monitoring actif',
        'status' => (((int) ($kpis['audit_actions_in_period'] ?? 0) + (int) ($kpis['forum_posts_in_period'] ?? 0)) > 0) ? 'watch' : 'risk',
    ],
];

$statusUi = [
    'ok' => ['label' => 'En place', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
    'watch' => ['label' => 'À consolider', 'class' => 'bg-amber-50 text-amber-800 border-amber-200'],
    'risk' => ['label' => 'À initialiser', 'class' => 'bg-rose-50 text-rose-800 border-rose-200'],
];
?>
<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Indicateurs transverses</h1>
            <p class="text-sm text-slate-600 mt-1 max-w-2xl">
                Vue d’ensemble du portail : d’abord les comptages issus des données enregistrées (toutes communautés),
                puis le journal de suivi d’usage lorsqu’il est disponible. Les durées moyennes ne concernent que les visites
                où une mesure d’audience a été acceptée.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?= $periodLinks($platformAnalyticsDays) ?>
            <a href="<?= htmlspecialchars(url('admin'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-500 hover:text-slate-800 ml-1">Tableau de bord</a>
        </div>
    </div>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Chantiers techniques transverses (mise en œuvre système)</h2>
        <p class="text-sm text-slate-600 mb-4 max-w-3xl">
            Cette section implémente le pilotage des 5 chantiers dans l’interface d’administration site avec une lecture
            directe des signaux disponibles sur la période sélectionnée.
        </p>
        <div class="grid lg:grid-cols-2 gap-4">
            <?php foreach ($transverseTracks as $track):
                $status = (string) ($track['status'] ?? 'watch');
                $ui = $statusUi[$status] ?? $statusUi['watch'];
                ?>
                <article class="rounded-xl border border-slate-200 bg-white shadow-sm p-4">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars((string) ($track['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold <?= htmlspecialchars((string) ($ui['class'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) ($ui['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-slate-600"><?= htmlspecialchars((string) ($track['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-2">
                            <dt class="uppercase tracking-wide text-slate-500"><?= htmlspecialchars((string) ($track['kpi_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dt>
                            <dd class="mt-1 text-base font-black text-slate-900 tabular-nums"><?= (int) ($track['kpi_value'] ?? 0) ?></dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-2">
                            <dt class="uppercase tracking-wide text-slate-500">Objectif</dt>
                            <dd class="mt-1 text-sm font-semibold text-slate-800"><?= htmlspecialchars((string) ($track['target_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Activité enregistrée (tout le site)</h2>
        <p class="text-sm text-slate-600 mb-4 max-w-3xl">
            Agrégats sur les <strong class="font-semibold text-slate-800"><?= (int) $platformAnalyticsDays ?> derniers jours</strong> (fuseau serveur),
            indépendamment du suivi d’usage optionnel.
        </p>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4">
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Communautés (total)</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['communities_total'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Communautés avec au moins un membre actif</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['communities_with_active_members'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Membres actifs (toutes communautés)</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['users_active_total'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Nouveaux comptes (période)</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['users_registered_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Écritures journal d’audit</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['audit_actions_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Candidatures déposées</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['enlistments_created_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Sujets de forum créés</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['forum_topics_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Messages de forum</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['forum_posts_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Inscriptions aux parcours</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['training_enrollments_assigned_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-emerald-50/80 shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-emerald-900">Parcours terminés</dt>
                <dd class="text-2xl font-black text-emerald-950 mt-1 tabular-nums"><?= (int) ($kpis['training_completions_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50 shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-600">Événements de suivi d’usage (période)</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['usage_events_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50 shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-600">Membres distincts (suivi d’usage)</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) ($kpis['usage_distinct_actors_in_period'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50 shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-600">Durée moyenne (mesures reçues)</dt>
                <dd class="text-2xl font-black text-slate-900 mt-1"><?= isset($kpis['usage_avg_duration_seconds']) && $kpis['usage_avg_duration_seconds'] !== null ? (int) round((float) $kpis['usage_avg_duration_seconds']) . ' s' : '—' ?></dd>
            </div>
        </dl>
    </section>

    <dl class="grid sm:grid-cols-2 gap-4 mb-10">
        <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
            <dt class="text-[10px] uppercase tracking-wider text-slate-500">Événements de suivi (dernières 24 h)</dt>
            <dd class="text-3xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) $snap['events_24h'] ?></dd>
        </div>
        <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
            <dt class="text-[10px] uppercase tracking-wider text-slate-500">Communautés avec au moins un événement mesuré (<?= (int) $platformAnalyticsDays ?> j.)</dt>
            <dd class="text-3xl font-black text-slate-900 mt-1 tabular-nums"><?= (int) $snap['tenants_with_events'] ?></dd>
        </div>
    </dl>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Communautés les plus actives (suivi d’usage · <?= (int) $platformAnalyticsDays ?> j.)</h2>
        <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <th class="px-4 py-3 font-bold">Communauté</th>
                        <th class="px-4 py-3 font-bold text-right">Événements</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (($snap['top_tenants'] ?? []) === []): ?>
                    <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">Aucune donnée de suivi sur cette fenêtre.</td></tr>
                <?php else: ?>
                    <?php foreach ($snap['top_tenants'] as $t): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($t['events'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Volume de suivi par jour (<?= (int) $platformAnalyticsDays ?> j.)</h2>
        <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm">
            <?php if ($platformDailyEvents === []): ?>
                <p class="text-sm text-slate-500 py-6 text-center">Aucune donnée à afficher.</p>
            <?php else: ?>
                <div class="flex items-end gap-1 h-40 px-1" role="img" aria-label="Histogramme du nombre d’événements de suivi par jour">
                    <?php foreach ($platformDailyEvents as $de):
                        $cnt = (int) ($de['events'] ?? 0);
                        $barPx = $dailyMax > 0 ? max($cnt > 0 ? 3 : 0, (int) round(144 * $cnt / $dailyMax)) : 0;
                        $dayRaw = (string) ($de['day'] ?? '');
                        $dayLabel = $dayRaw !== '' ? date('d/m', strtotime($dayRaw)) : '—';
                        ?>
                    <div class="flex-1 min-w-[14px] flex flex-col items-center justify-end h-full">
                        <span class="text-[10px] font-semibold text-slate-600 mb-0.5 tabular-nums"><?= $cnt > 0 ? (string) $cnt : '' ?></span>
                        <div class="w-full max-w-[20px] mx-auto rounded-t bg-indigo-500/90 shrink-0" style="height: <?= $barPx ?>px" title="<?= (int) $cnt ?> événement(s) le <?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?>"></div>
                        <span class="text-[9px] text-slate-500 mt-1.5 leading-none text-center w-full truncate"><?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Détail du suivi d’usage (<?= (int) $platformAnalyticsDays ?> j.)</h2>
        <div class="grid lg:grid-cols-2 gap-4">
            <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/80">
                    <h3 class="text-sm font-bold text-slate-800">Actions les plus fréquentes</h3>
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-2 font-bold">Type d’action</th>
                            <th class="px-4 py-2 font-bold text-right">Occurrences</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($platformTopEventNames === []): ?>
                        <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">Aucune action recensée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($platformTopEventNames as $row): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-2.5 font-medium text-slate-900"><?= htmlspecialchars(TenantAnalyticsLabels::eventNameLabel((string) ($row['name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-2.5 text-right tabular-nums"><?= (int) ($row['events'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 bg-slate-50/80">
                    <h3 class="text-sm font-bold text-slate-800">Volume par rubrique</h3>
                </div>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                            <th class="px-4 py-2 font-bold">Rubrique</th>
                            <th class="px-4 py-2 font-bold text-right">Événements</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($platformCategoryBreakdown === []): ?>
                        <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">Aucune rubrique détectée.</td></tr>
                    <?php else: ?>
                        <?php foreach ($platformCategoryBreakdown as $row): ?>
                        <?php $catKey = (string) ($row['category'] ?? ''); ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-2.5 font-medium text-slate-900"><?= htmlspecialchars(TenantAnalyticsLabels::categoryLabel($catKey), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-2.5 text-right tabular-nums"><?= (int) ($row['events'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
