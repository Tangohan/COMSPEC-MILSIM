<?php
declare(strict_types=1);

use App\Services\Analytics\TenantAnalyticsLabels;

/** @var int $activeApprox */
/** @var int $dashboardEvents */
/** @var string $since */
/** @var int $analyticsDays */
/** @var list<array<string, mixed>> $trainingCourseStats */
/** @var list<array<string, mixed>> $recruitmentOpeningStats */
/** @var array{public_views: int, public_duration_avg: ?float, enlistment_opens: int, enlistment_submits: int, cta_clicks: int} $publicEngagement */
/** @var list<array{category: string, events: int}> $tenantCategoryBreakdown */
/** @var list<array{actor_label: string, events: int}> $tenantTopActors */
/** @var array{total_events: int, distinct_actors: int, events_with_duration: int, avg_duration_seconds: ?float} $tenantUsageSummary */
/** @var list<array{day: string, events: int}> $tenantDailyEvents */
/** @var list<array{name: string, events: int}> $tenantTopEventNames */
/** @var int $trainingCatalogViews */
$trainingCourseStats = $trainingCourseStats ?? [];
$recruitmentOpeningStats = $recruitmentOpeningStats ?? [];
$tenantCategoryBreakdown = $tenantCategoryBreakdown ?? [];
$tenantTopActors = $tenantTopActors ?? [];
$tenantDailyEvents = $tenantDailyEvents ?? [];
$tenantTopEventNames = $tenantTopEventNames ?? [];
$trainingCatalogViews = (int) ($trainingCatalogViews ?? 0);
$tenantUsageSummary = $tenantUsageSummary ?? [
    'total_events' => 0,
    'distinct_actors' => 0,
    'events_with_duration' => 0,
    'avg_duration_seconds' => null,
];
$publicEngagement = $publicEngagement ?? [
    'public_views' => 0,
    'public_duration_avg' => null,
    'enlistment_opens' => 0,
    'enlistment_submits' => 0,
    'cta_clicks' => 0,
];
$analyticsDays = (int) ($analyticsDays ?? 30);

$dailyMax = 0;
foreach ($tenantDailyEvents as $de) {
    $dailyMax = max($dailyMax, (int) ($de['events'] ?? 0));
}

$periodLinks = static function (int $current): string {
    $base = url('back-office/analytics');
    $opts = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
    $parts = [];
    foreach ($opts as $d => $label) {
        $active = $d === $current ? ' font-black text-emerald-700' : ' text-slate-600 hover:text-slate-900';
        $parts[] = '<a class="text-sm' . $active . '" href="' . htmlspecialchars($base . '?days=' . $d, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return implode('<span class="text-slate-300 mx-2">|</span>', $parts);
};

$ratioPct = static function (int $num, int $den): string {
    if ($den < 1) {
        return '—';
    }

    return number_format(100.0 * $num / $den, 1, ',', ' ') . ' %';
};
?>
<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Indicateurs d’usage</h1>
            <p class="text-sm text-slate-600 mt-1">Synthèse pour votre communauté (depuis le <?= htmlspecialchars($since) ?> — fuseau serveur).</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <?= $periodLinks($analyticsDays) ?>
            <a href="<?= htmlspecialchars(url('back-office'), ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-500 hover:text-slate-800 ml-2">Retour</a>
        </div>
    </div>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Volume d’activité (suivi d’usage)</h2>
        <p class="text-sm text-slate-600 mb-4 max-w-3xl">Indicateurs agrégés à partir du journal d’usage du portail (y compris actions anonymes ou sans membre identifié). Les durées moyennes ne portent que sur les visites où une mesure a été acceptée.</p>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Événements enregistrés (période)</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) ($tenantUsageSummary['total_events'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Membres distincts (connectés)</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) ($tenantUsageSummary['distinct_actors'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Mesures de durée reçues</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) ($tenantUsageSummary['events_with_duration'] ?? 0) ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Durée moyenne (ces mesures)</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= isset($tenantUsageSummary['avg_duration_seconds']) && $tenantUsageSummary['avg_duration_seconds'] !== null ? (int) round((float) $tenantUsageSummary['avg_duration_seconds']) . ' s' : '—' ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-emerald-50/80 shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-emerald-900">Ouvertures du catalogue formations</dt>
                <dd class="text-3xl font-black text-emerald-950 mt-1"><?= $trainingCatalogViews ?></dd>
            </div>
        </dl>

        <div class="border border-slate-200 rounded-xl p-5 bg-white shadow-sm mb-6">
            <h3 class="text-sm font-bold text-slate-800 mb-4">Répartition par jour</h3>
            <?php if ($tenantDailyEvents === []): ?>
                <p class="text-sm text-slate-500 py-6 text-center">Pas encore de données journalières sur cette période.</p>
            <?php else: ?>
                <div class="flex items-end gap-1 h-36 px-1" role="img" aria-label="Histogramme du nombre d’événements par jour">
                    <?php foreach ($tenantDailyEvents as $de):
                        $cnt = (int) ($de['events'] ?? 0);
                        $barPx = $dailyMax > 0 ? max($cnt > 0 ? 3 : 0, (int) round(132 * $cnt / $dailyMax)) : 0;
                        $dayRaw = (string) ($de['day'] ?? '');
                        $dayLabel = $dayRaw !== '' ? date('d/m', strtotime($dayRaw)) : '—';
                        ?>
                    <div class="flex-1 min-w-[18px] flex flex-col items-center justify-end h-full">
                        <span class="text-[10px] font-semibold text-slate-600 mb-0.5 tabular-nums"><?= $cnt > 0 ? (string) $cnt : '' ?></span>
                        <div class="w-full max-w-[22px] mx-auto rounded-t bg-emerald-500/90 shrink-0" style="height: <?= $barPx ?>px" title="<?= (int) $cnt ?> événement(s) le <?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?>"></div>
                        <span class="text-[9px] text-slate-500 mt-1.5 leading-none text-center w-full truncate"><?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3 font-bold">Type d’action</th>
                        <th class="px-4 py-3 font-bold text-right">Occurrences</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($tenantTopEventNames === []): ?>
                    <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">Aucune action recensée sur la période.</td></tr>
                <?php else: ?>
                    <?php foreach ($tenantTopEventNames as $row): ?>
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars(TenantAnalyticsLabels::eventNameLabel((string) ($row['name'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['events'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Espace membre</h2>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Comptes actifs (audit, période)</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $activeApprox ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Ouvertures du tableau de bord</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $dashboardEvents ?></dd>
            </div>
        </dl>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Fiche publique &amp; recrutement</h2>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Consultations de la fiche publique</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $publicEngagement['public_views'] ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Temps moyen sur la fiche (visiteurs ayant accepté la mesure d’audience)</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= $publicEngagement['public_duration_avg'] !== null ? (int) round((float) $publicEngagement['public_duration_avg']) . ' s' : '—' ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Clics vers le formulaire de candidature</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $publicEngagement['cta_clicks'] ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Ouvertures du formulaire de candidature</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $publicEngagement['enlistment_opens'] ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-white shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-slate-500">Candidatures envoyées (période)</dt>
                <dd class="text-3xl font-black text-slate-900 mt-1"><?= (int) $publicEngagement['enlistment_submits'] ?></dd>
            </div>
            <div class="border border-slate-200 rounded-xl p-4 bg-emerald-50/80 shadow-sm">
                <dt class="text-[10px] uppercase tracking-wider text-emerald-800">Taux de passage au dépôt (sur ouvertures du formulaire)</dt>
                <dd class="text-3xl font-black text-emerald-900 mt-1"><?= $ratioPct((int) $publicEngagement['enlistment_submits'], (int) $publicEngagement['enlistment_opens']) ?></dd>
            </div>
        </dl>
        <p class="text-xs text-slate-500 leading-relaxed max-w-3xl">Les durées et certains clics ne sont comptés que si le visiteur a accepté les cookies « mesure d’audience » sur le portail.</p>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Détails back-office (usage interne)</h2>
        <p class="text-sm text-slate-600 mb-4 max-w-3xl">Membres les plus actifs sur le suivi d’usage, et volume par grande rubrique (formations, fiche publique, recrutement, portail).</p>
        <div class="grid lg:grid-cols-2 gap-4 mb-4">
            <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3 font-bold">Membre</th>
                            <th class="px-4 py-3 font-bold text-right">Événements</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($tenantTopActors === []): ?>
                        <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">Aucun acteur interne identifiable pour cette période.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tenantTopActors as $row): ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars((string) ($row['actor_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['events'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="border border-slate-200 rounded-xl overflow-hidden bg-white shadow-sm">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3 font-bold">Rubrique</th>
                            <th class="px-4 py-3 font-bold text-right">Événements</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($tenantCategoryBreakdown === []): ?>
                        <tr><td colspan="2" class="px-4 py-8 text-center text-slate-500">Aucune rubrique recensée sur la période.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tenantCategoryBreakdown as $row): ?>
                        <?php
                        $catKey = (string) ($row['category'] ?? $row['label'] ?? '');
                        ?>
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars(TenantAnalyticsLabels::categoryLabel($catKey), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['events'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-xs text-slate-500 max-w-3xl">La colonne « Membres » ne compte que les événements liés à un compte connecté. Les visites publiques sans compte apparaissent surtout dans les rubriques « Fiche publique » et « Recrutement » ci-dessus.</p>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Formations (catalogue)</h2>
        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3 font-bold">Parcours</th>
                        <th class="px-4 py-3 font-bold text-right">Consultations</th>
                        <th class="px-4 py-3 font-bold text-right">Temps moyen (fiche)</th>
                        <th class="px-4 py-3 font-bold text-right">Favoris</th>
                        <th class="px-4 py-3 font-bold text-right">J’aime</th>
                        <th class="px-4 py-3 font-bold text-right">Commentaires</th>
                        <th class="px-4 py-3 font-bold text-right">Avis publiés</th>
                        <th class="px-4 py-3 font-bold text-right">Accès par code</th>
                        <th class="px-4 py-3 font-bold text-right">Terminées</th>
                        <th class="px-4 py-3 font-bold text-right">Inscriptions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($trainingCourseStats === []): ?>
                    <tr><td colspan="10" class="px-4 py-8 text-center text-slate-500">Aucun parcours à afficher pour le moment.</td></tr>
                <?php else: ?>
                    <?php foreach ($trainingCourseStats as $row): ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['views_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= isset($row['avg_page_seconds']) && $row['avg_page_seconds'] !== null ? (int) round((float) $row['avg_page_seconds']) . ' s' : '—' ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['favorites_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['likes_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['comments_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['reviews_count'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['code_uses'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['enrollments_completed'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($row['enrollments_total'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-10">
        <h2 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Postes ouverts (avis de vacance)</h2>
        <div class="overflow-x-auto border border-slate-200 rounded-xl bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] uppercase tracking-wider text-slate-500">
                        <th class="px-4 py-3 font-bold">Intitulé</th>
                        <th class="px-4 py-3 font-bold">Référence</th>
                        <th class="px-4 py-3 font-bold text-right">Consultations (fiche)</th>
                        <th class="px-4 py-3 font-bold text-right">Temps moyen</th>
                        <th class="px-4 py-3 font-bold text-right">Candidatures (période)</th>
                        <th class="px-4 py-3 font-bold text-right">Candidatures (total)</th>
                        <th class="px-4 py-3 font-bold text-right">Conversion (période)</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($recruitmentOpeningStats === []): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">Aucun poste publié ou archivé à analyser.</td></tr>
                <?php else: ?>
                    <?php foreach ($recruitmentOpeningStats as $ro): ?>
                    <?php
                    $views = (int) ($ro['views_count'] ?? 0);
                    $appsP = (int) ($ro['applications_period'] ?? 0);
                    ?>
                    <tr class="border-b border-slate-100 hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-semibold text-slate-900"><?= htmlspecialchars((string) ($ro['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-slate-600 font-mono text-xs"><?= htmlspecialchars((string) ($ro['reference_public'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= $views ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= isset($ro['avg_page_seconds']) && $ro['avg_page_seconds'] !== null ? (int) round((float) $ro['avg_page_seconds']) . ' s' : '—' ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= $appsP ?></td>
                        <td class="px-4 py-3 text-right tabular-nums"><?= (int) ($ro['applications_total'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-right tabular-nums font-medium text-slate-800"><?= $ratioPct($appsP, $views) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-500 mt-3 max-w-3xl">La conversion compare les candidatures enregistrées sur la période aux consultations de la fiche poste sur la même période (approximation indicative).</p>
    </section>
</div>
