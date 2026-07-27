<?php
declare(strict_types=1);

use App\Services\Analytics\TenantAnalyticsLabels;

/**
 * Indicateurs d’usage — charte ATHENA.
 *
 * L’en-tête de page est rendu par la coque back-office. Deux familles de chiffres sont
 * distinguées : ceux comptés dans la base du portail (fiables en toute circonstance) et
 * ceux issus du suivi d’usage, qui dépendent du consentement « mesure d’audience ».
 *
 * @var int $activeApprox
 * @var int $dashboardEvents
 * @var string $since
 * @var int $analyticsDays
 * @var list<array<string, mixed>> $trainingCourseStats
 * @var list<array<string, mixed>> $recruitmentOpeningStats
 * @var array{public_views: int, public_duration_avg: ?float, enlistment_opens: int, enlistment_submits: int, cta_clicks: int} $publicEngagement
 * @var list<array{category: string, events: int}> $tenantCategoryBreakdown
 * @var list<array{actor_label: string, events: int}> $tenantTopActors
 * @var array{total_events: int, distinct_actors: int, events_with_duration: int, avg_duration_seconds: ?float} $tenantUsageSummary
 * @var list<array{day: string, events: int}> $tenantDailyEvents
 * @var list<array{name: string, events: int}> $tenantTopEventNames
 * @var int $trainingCatalogViews
 * @var array<string, mixed> $conversionFunnel
 * @var array<string, mixed> $funnelLast7
 * @var array<string, mixed> $funnelPrev7Only
 * @var array<string, mixed> $operationalKpis
 * @var list<array{status: string, count: int}> $enlistmentStatusBreakdown
 * @var array<string, mixed> $documentInsights
 * @var string $analyticsFocus
 */

$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

$trainingCourseStats = is_array($trainingCourseStats ?? null) ? $trainingCourseStats : [];
$recruitmentOpeningStats = is_array($recruitmentOpeningStats ?? null) ? $recruitmentOpeningStats : [];
$tenantCategoryBreakdown = is_array($tenantCategoryBreakdown ?? null) ? $tenantCategoryBreakdown : [];
$tenantTopActors = is_array($tenantTopActors ?? null) ? $tenantTopActors : [];
$tenantDailyEvents = is_array($tenantDailyEvents ?? null) ? $tenantDailyEvents : [];
$tenantTopEventNames = is_array($tenantTopEventNames ?? null) ? $tenantTopEventNames : [];
$trainingCatalogViews = (int) ($trainingCatalogViews ?? 0);
$activeApprox = (int) ($activeApprox ?? 0);
$dashboardEvents = (int) ($dashboardEvents ?? 0);
$since = (string) ($since ?? '');
$analyticsDays = (int) ($analyticsDays ?? 30);
$analyticsFocus = (string) ($analyticsFocus ?? 'overview');

$tenantUsageSummary = is_array($tenantUsageSummary ?? null) ? $tenantUsageSummary : [];
$tenantUsageSummary += [
    'total_events' => 0,
    'distinct_actors' => 0,
    'events_with_duration' => 0,
    'avg_duration_seconds' => null,
];
$publicEngagement = is_array($publicEngagement ?? null) ? $publicEngagement : [];
$publicEngagement += [
    'public_views' => 0,
    'public_duration_avg' => null,
    'enlistment_opens' => 0,
    'enlistment_submits' => 0,
    'cta_clicks' => 0,
];
$operationalKpis = is_array($operationalKpis ?? null) ? $operationalKpis : [];
$operationalKpis += [
    'members_active_total' => 0,
    'members_registered_in_period' => 0,
    'audit_actions_in_period' => 0,
    'enlistments_created_in_period' => 0,
    'forum_topics_in_period' => 0,
    'forum_posts_in_period' => 0,
    'training_enrollments_assigned_in_period' => 0,
    'training_completions_in_period' => 0,
];
$enlistmentStatusBreakdown = is_array($enlistmentStatusBreakdown ?? null) ? $enlistmentStatusBreakdown : [];
$documentInsights = is_array($documentInsights ?? null) ? $documentInsights : [];
$documentInsights += [
    'total_documents' => 0,
    'published_documents' => 0,
    'updated_in_period' => 0,
    'stale_published_documents' => 0,
    'review_overdue_documents' => 0,
    'expiring_soon_documents' => 0,
    'top_types' => [],
];
$conversionFunnel = is_array($conversionFunnel ?? null) ? $conversionFunnel : [];
$conversionFunnel += [
    'visits' => 0,
    'cta_clicks' => 0,
    'applications' => 0,
    'accepted' => 0,
    'median_visit_to_first_contact_hours' => null,
];
$funnelLast7 = is_array($funnelLast7 ?? null) ? $funnelLast7 : $conversionFunnel;
$funnelPrev7Only = is_array($funnelPrev7Only ?? null) ? $funnelPrev7Only : [];
$funnelPrev7Only += ['visits' => 0, 'cta_clicks' => 0, 'applications' => 0, 'accepted' => 0];

$enlistmentStatusLabelAnalytics = static function (string $status): string {
    return match ($status) {
        'submitted' => 'En attente de décision',
        'reviewed' => 'Traitée',
        'rejected' => 'Rejetée',
        'blocked' => 'Bloquée',
        default => 'Autre état',
    };
};

$int = static fn (mixed $v): string => number_format((int) $v, 0, ',', ' ');
$ratioPct = static function (int $num, int $den): string {
    if ($den < 1) {
        return '—';
    }

    return number_format(100.0 * $num / $den, 1, ',', ' ') . ' %';
};
$clampPct = static function (int $num, int $den): string {
    if ($den < 1) {
        return '0%';
    }

    return (string) max(0, min(100, (int) round(100.0 * $num / $den))) . '%';
};
$seconds = static function (mixed $v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    $s = (int) round((float) $v);
    if ($s < 60) {
        return $s . ' s';
    }

    return intdiv($s, 60) . ' min ' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) . ' s';
};

// Conseil de pilotage : calculé sur les 7 derniers jours contre les 7 précédents.
$ctaRateLast7 = ((int) ($funnelLast7['visits'] ?? 0)) > 0
    ? (int) ($funnelLast7['cta_clicks'] ?? 0) / (int) ($funnelLast7['visits'] ?? 0)
    : null;
$ctaRatePrev7 = ((int) ($funnelPrev7Only['visits'] ?? 0)) > 0
    ? (int) ($funnelPrev7Only['cta_clicks'] ?? 0) / (int) ($funnelPrev7Only['visits'] ?? 0)
    : null;
$ctaRateDrop = $ctaRateLast7 !== null && $ctaRatePrev7 !== null && $ctaRateLast7 < $ctaRatePrev7;

$suggestions = [];
if ($ctaRateDrop) {
    $suggestions[] = 'La conversion visite → CTA baisse sur 7 jours : mettez en avant un appel à l’action principal unique (Rejoindre, Candidater ou Contacter) dans la première section de la page publique.';
}
if ((int) $conversionFunnel['applications'] > 0 && (int) $conversionFunnel['accepted'] === 0) {
    $suggestions[] = 'Des candidatures sont déposées mais aucune n’est acceptée : revoyez le délai de traitement et les critères de qualification.';
}
if ($conversionFunnel['median_visit_to_first_contact_hours'] !== null
    && (float) $conversionFunnel['median_visit_to_first_contact_hours'] > 48.0) {
    $suggestions[] = 'Le délai médian entre la visite et le premier contact dépasse 48 h : instaurez une revue quotidienne des candidatures.';
}
if ($suggestions === []) {
    $suggestions[] = 'Conversion stable cette semaine : poursuivez les essais sur vos accroches et maintenez la fréquence de réponse.';
}

$periodOptions = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
?>
<nav class="ath-periods" aria-label="Période d’observation">
    <span class="ath-periods__label">Période</span>
    <?php foreach ($periodOptions as $days => $label): ?>
    <a href="<?= $h(url('back-office/analytics') . '?days=' . $days) ?>" class="ath-btn"<?= $days === $analyticsDays ? ' aria-current="true"' : '' ?>><?= $h($label) ?></a>
    <?php endforeach; ?>
    <span class="ath-table-toolbar__spacer" aria-hidden="true"></span>
    <a href="<?= $h(url('back-office/analytics/conversion') . '?days=' . $analyticsDays) ?>" class="ath-btn"<?= $analyticsFocus === 'conversion' ? ' aria-current="true"' : '' ?>>Entonnoir de conversion</a>
</nav>

<div class="ath-note">
    <p class="ath-note__title">Deux familles de chiffres</p>
    <p class="ath-note__text">
        Les indicateurs « activité portail » sont comptés directement dans la base (comptes, journal d’audit, forum,
        candidatures, formations) : ils restent fiables en toute circonstance. Le « suivi d’usage » et les durées
        dépendent du consentement « mesure d’audience » des visiteurs et peuvent donc sous-estimer la réalité.
        Fenêtre observée : <?= (int) $analyticsDays ?> jours glissants<?= $since !== '' ? ', à partir du ' . $h($since) : '' ?> (fuseau du serveur).
    </p>
</div>

<h2 class="ath-section-title">Activité portail</h2>

<?php
$membersTotal = max(1, (int) $operationalKpis['members_active_total']);
$athKpis = [
    ['label' => 'MEMBRES ACTIFS', 'value' => $int($operationalKpis['members_active_total']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'comptes en activité'],
    ['label' => 'ARRIVÉES', 'value' => $int($operationalKpis['members_registered_in_period']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $clampPct((int) $operationalKpis['members_registered_in_period'], $membersTotal), 'note' => 'sur la période'],
    ['label' => 'ACTIONS JOURNALISÉES', 'value' => $int($operationalKpis['audit_actions_in_period']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'journal d’audit'],
    ['label' => 'CANDIDATURES', 'value' => $int($operationalKpis['enlistments_created_in_period']), 'delta' => '', 'tone' => '#c98a12', 'pct' => '100%', 'note' => 'déposées sur la période'],
];
require base_path('views/partials/ath_kpis.php');

$athKpis = [
    ['label' => 'SUJETS DE FORUM', 'value' => $int($operationalKpis['forum_topics_in_period']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'ouverts sur la période'],
    ['label' => 'MESSAGES DE FORUM', 'value' => $int($operationalKpis['forum_posts_in_period']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'publiés sur la période'],
    ['label' => 'FORMATIONS ASSIGNÉES', 'value' => $int($operationalKpis['training_enrollments_assigned_in_period']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'inscriptions créées'],
    [
        'label' => 'FORMATIONS ACHEVÉES',
        'value' => $int($operationalKpis['training_completions_in_period']),
        'delta' => '',
        'tone' => '#0b8a5c',
        'pct' => $clampPct((int) $operationalKpis['training_completions_in_period'], max(1, (int) $operationalKpis['training_enrollments_assigned_in_period'])),
        'note' => 'sur les inscriptions de la période',
    ],
];
require base_path('views/partials/ath_kpis.php');

$enlistTotal = 0;
foreach ($enlistmentStatusBreakdown as $es) {
    $enlistTotal += (int) ($es['count'] ?? 0);
}
$athTableTitle = 'Candidatures de la période, par état';
$athTableCount = $enlistTotal;
$athTableCols = ['ÉTAT|b', 'CANDIDATURES|r', 'PART|r'];
$athTableRows = [];
foreach ($enlistmentStatusBreakdown as $es) {
    $n = (int) ($es['count'] ?? 0);
    $athTableRows[] = [
        $enlistmentStatusLabelAnalytics((string) ($es['status'] ?? '')),
        $int($n),
        $ratioPct($n, $enlistTotal),
    ];
}
$athTableFilters = [];
$athTableMinWidth = '680px';
$athTableShowCheckbox = false;
$athTableExportUrl = null;
$athTablePager = null;
$athTableRowHrefs = null;
$athTableRowActions = null;
$athTableFoot = $enlistmentStatusBreakdown === []
    ? 'Aucune candidature déposée sur la période.'
    : 'Compté à la date de dépôt, indépendamment de la date de décision.';
require base_path('views/partials/ath_table.php');
?>

<h2 class="ath-section-title">Suivi d’usage</h2>

<?php
$athKpis = [
    ['label' => 'ÉVÉNEMENTS', 'value' => $int($tenantUsageSummary['total_events']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'sur la période'],
    ['label' => 'PERSONNES DISTINCTES', 'value' => $int($tenantUsageSummary['distinct_actors']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'acteurs identifiés'],
    ['label' => 'AVEC DURÉE MESURÉE', 'value' => $int($tenantUsageSummary['events_with_duration']), 'delta' => '', 'tone' => '#c98a12', 'pct' => $clampPct((int) $tenantUsageSummary['events_with_duration'], max(1, (int) $tenantUsageSummary['total_events'])), 'note' => 'nécessite le consentement'],
    ['label' => 'DURÉE MOYENNE', 'value' => $seconds($tenantUsageSummary['avg_duration_seconds']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'par consultation mesurée'],
];
require base_path('views/partials/ath_kpis.php');
?>

<?php if ($tenantDailyEvents !== []): ?>
<?php
$dailyMax = 0;
foreach ($tenantDailyEvents as $de) {
    $dailyMax = max($dailyMax, (int) ($de['events'] ?? 0));
}
$firstDay = (string) ($tenantDailyEvents[0]['day'] ?? '');
$lastDay = (string) ($tenantDailyEvents[count($tenantDailyEvents) - 1]['day'] ?? '');
$fmtDay = static function (string $raw): string {
    $t = strtotime($raw);

    return $t ? date('d/m', $t) : $raw;
};
?>
<div class="ath-panel ath-rise">
    <h2 class="ath-panel__title" style="margin-top:0;">Répartition par jour</h2>
    <p class="ath-panel__lead">Un bâtonnet par jour sur <?= (int) $analyticsDays ?> jours. Sommet de l’échelle : <?= $int($dailyMax) ?> événement<?= $dailyMax > 1 ? 's' : '' ?>.</p>
    <div class="ath-spark" role="img" aria-label="Activité quotidienne sur <?= (int) $analyticsDays ?> jours">
        <?php foreach ($tenantDailyEvents as $de): ?>
            <?php
            $n = (int) ($de['events'] ?? 0);
            $day = $fmtDay((string) ($de['day'] ?? ''));
            $pct = $dailyMax > 0 ? max(2, (int) round($n / $dailyMax * 100)) : 2;
            ?>
        <span class="ath-spark__bar<?= $n === 0 ? ' ath-spark__bar--empty' : '' ?>"
              style="height:<?= $pct ?>%"
              title="<?= $h($day . ' — ' . $int($n) . ' événement' . ($n > 1 ? 's' : '')) ?>"></span>
        <?php endforeach; ?>
    </div>
    <div class="ath-spark__axis">
        <span><?= $h($fmtDay($firstDay)) ?></span>
        <span><?= $h($fmtDay($lastDay)) ?></span>
    </div>
</div>
<?php endif; ?>

<?php
$actorsTotal = 0;
foreach ($tenantTopActors as $row) {
    $actorsTotal += (int) ($row['events'] ?? 0);
}
$athTableTitle = 'Membres les plus actifs';
$athTableCount = count($tenantTopActors);
$athTableCols = ['MEMBRE', 'ÉVÉNEMENTS|r', 'PART|r'];
$athTableRows = [];
foreach ($tenantTopActors as $row) {
    $n = (int) ($row['events'] ?? 0);
    $athTableRows[] = [
        (string) ($row['actor_label'] ?? '—'),
        $int($n),
        $ratioPct($n, $actorsTotal),
    ];
}
$athTableMinWidth = '760px';
$athTableFoot = $tenantTopActors === []
    ? 'Aucun acteur identifié sur la période.'
    : 'Part calculée sur les événements des membres listés, pas sur le total de la communauté.';
require base_path('views/partials/ath_table.php');

$catTotal = 0;
foreach ($tenantCategoryBreakdown as $row) {
    $catTotal += (int) ($row['events'] ?? 0);
}
$athTableTitle = 'Volume par rubrique';
$athTableCount = count($tenantCategoryBreakdown);
$athTableCols = ['RUBRIQUE', 'ÉVÉNEMENTS|r', 'PART|r'];
$athTableRows = [];
foreach ($tenantCategoryBreakdown as $row) {
    $n = (int) ($row['events'] ?? 0);
    $athTableRows[] = [
        TenantAnalyticsLabels::categoryLabel((string) ($row['category'] ?? '')),
        $int($n),
        $ratioPct($n, $catTotal),
    ];
}
$athTableMinWidth = '760px';
$athTableFoot = $tenantCategoryBreakdown === []
    ? 'Aucun événement enregistré sur la période.'
    : 'Formations, fiche publique, recrutement et portail.';
require base_path('views/partials/ath_table.php');

$athTableTitle = 'Actions les plus fréquentes';
$athTableCount = count($tenantTopEventNames);
$athTableCols = ['ACTION', 'OCCURRENCES|r'];
$athTableRows = [];
foreach ($tenantTopEventNames as $row) {
    $athTableRows[] = [
        TenantAnalyticsLabels::eventNameLabel((string) ($row['name'] ?? '')),
        $int((int) ($row['events'] ?? 0)),
    ];
}
$athTableMinWidth = '640px';
$athTableFoot = $tenantTopEventNames === [] ? 'Aucune action enregistrée sur la période.' : null;
require base_path('views/partials/ath_table.php');
?>

<h2 class="ath-section-title">Gouvernance documentaire</h2>

<?php
$docsTotal = max(1, (int) $documentInsights['total_documents']);
$athKpis = [
    ['label' => 'DOCUMENTS', 'value' => $int($documentInsights['total_documents']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'toutes catégories'],
    ['label' => 'PUBLIÉS', 'value' => $int($documentInsights['published_documents']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $clampPct((int) $documentInsights['published_documents'], $docsTotal), 'note' => 'visibles des membres'],
    ['label' => 'MIS À JOUR', 'value' => $int($documentInsights['updated_in_period']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $clampPct((int) $documentInsights['updated_in_period'], $docsTotal), 'note' => 'sur la période'],
    [
        'label' => 'REVUE EN RETARD',
        'value' => $int($documentInsights['review_overdue_documents']),
        'delta' => (int) $documentInsights['expiring_soon_documents'] > 0 ? $int($documentInsights['expiring_soon_documents']) . ' à échéance' : '',
        'tone' => (int) $documentInsights['review_overdue_documents'] === 0 ? '#0b8a5c' : '#c72e2e',
        'pct' => $clampPct((int) $documentInsights['review_overdue_documents'], $docsTotal),
        'note' => 'dont ' . $int($documentInsights['stale_published_documents']) . ' publiés sans révision',
    ],
];
require base_path('views/partials/ath_kpis.php');

$topTypes = is_array($documentInsights['top_types'] ?? null) ? $documentInsights['top_types'] : [];
$athTableTitle = 'Types de documents les plus représentés';
$athTableCount = count($topTypes);
$athTableCols = ['TYPE', 'DOCUMENTS|r'];
$athTableRows = [];
foreach ($topTypes as $t) {
    $athTableRows[] = [
        (string) ($t['document_type'] ?? '—'),
        $int((int) ($t['count'] ?? 0)),
    ];
}
$athTableMinWidth = '620px';
$athTableFoot = $topTypes === [] ? 'Aucun document enregistré.' : null;
require base_path('views/partials/ath_table.php');
?>

<h2 class="ath-section-title">Espace membre</h2>

<?php
$athKpis = [
    ['label' => 'MEMBRES VUS ACTIFS', 'value' => $int($activeApprox), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => '100%', 'note' => 'estimation sur la période'],
    ['label' => 'CONSULTATIONS DU HUB', 'value' => $int($dashboardEvents), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'ouvertures du tableau de bord'],
    ['label' => 'CATALOGUE FORMATIONS', 'value' => $int($trainingCatalogViews), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'consultations du catalogue'],
];
require base_path('views/partials/ath_kpis.php');
?>

<h2 class="ath-section-title">Fiche publique &amp; recrutement</h2>

<?php
$athKpis = [
    ['label' => 'VISITES PUBLIQUES', 'value' => $int($publicEngagement['public_views']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'pages de la vitrine'],
    ['label' => 'DURÉE MOYENNE', 'value' => $seconds($publicEngagement['public_duration_avg']), 'delta' => '', 'tone' => '#1e4f80', 'pct' => '100%', 'note' => 'si mesure autorisée'],
    ['label' => 'CLICS D’ACTION', 'value' => $int($publicEngagement['cta_clicks']), 'delta' => '', 'tone' => '#0b8a5c', 'pct' => $clampPct((int) $publicEngagement['cta_clicks'], max(1, (int) $publicEngagement['public_views'])), 'note' => 'sur les visites publiques'],
    ['label' => 'FORMULAIRES OUVERTS', 'value' => $int($publicEngagement['enlistment_opens']), 'delta' => '', 'tone' => '#c98a12', 'pct' => '100%', 'note' => 'page de candidature'],
    [
        'label' => 'CANDIDATURES ENVOYÉES',
        'value' => $int($publicEngagement['enlistment_submits']),
        'delta' => '',
        'tone' => '#0b8a5c',
        'pct' => $clampPct((int) $publicEngagement['enlistment_submits'], max(1, (int) $publicEngagement['enlistment_opens'])),
        'note' => 'sur les formulaires ouverts',
    ],
];
require base_path('views/partials/ath_kpis.php');
?>

<h2 class="ath-section-title" id="conversion-funnel">Entonnoir de conversion</h2>

<div class="ath-panel<?= $analyticsFocus === 'conversion' ? '' : ' ath-panel--dashed' ?> ath-rise">
    <div class="ath-stat-grid">
        <div class="ath-stat">
            <p class="ath-stat__value"><?= $int($conversionFunnel['visits']) ?></p>
            <p class="ath-stat__label">Visites publiques</p>
        </div>
        <div class="ath-stat">
            <p class="ath-stat__value"><?= $int($conversionFunnel['cta_clicks']) ?></p>
            <p class="ath-stat__label">Clics d’action</p>
        </div>
        <div class="ath-stat">
            <p class="ath-stat__value"><?= $int($conversionFunnel['applications']) ?></p>
            <p class="ath-stat__label">Candidatures</p>
        </div>
        <div class="ath-stat ath-stat--add">
            <p class="ath-stat__value"><?= $int($conversionFunnel['accepted']) ?></p>
            <p class="ath-stat__label">Acceptations</p>
        </div>
    </div>
    <p class="ath-panel__lead" style="margin-top:13px;">
        Visite → clic d’action : <strong><?= $h($ratioPct((int) $conversionFunnel['cta_clicks'], (int) $conversionFunnel['visits'])) ?></strong> ·
        candidature → acceptation : <strong><?= $h($ratioPct((int) $conversionFunnel['accepted'], (int) $conversionFunnel['applications'])) ?></strong> ·
        délai médian visite → premier contact :
        <strong><?= $conversionFunnel['median_visit_to_first_contact_hours'] !== null
            ? $h(number_format((float) $conversionFunnel['median_visit_to_first_contact_hours'], 1, ',', ' ') . ' h')
            : '—' ?></strong>
        (approché par la date de revue de la candidature).
    </p>
</div>

<?php
// Un recul de la conversion sur 7 jours passe le bandeau en ambre : c’est un signal,
// pas une erreur, d’où le ton d’avertissement plutôt que rouge.
$noteStyle = $ctaRateDrop ? 'background:#fdf3e2;border-color:#f2ddb4;' : '';
$noteTextStyle = $ctaRateDrop ? 'color:#8a5a06;' : '';
?>
<div class="ath-note" style="<?= $h($noteStyle) ?>">
    <p class="ath-note__title" style="<?= $h($noteTextStyle) ?>">Prochaine action recommandée</p>
    <?php foreach ($suggestions as $suggestion): ?>
    <p class="ath-note__text" style="<?= $h($noteTextStyle) ?>"><?= $h($suggestion) ?></p>
    <?php endforeach; ?>
</div>

<h2 class="ath-section-title">Formations du catalogue</h2>

<?php
$athTableTitle = 'Consultations et engagement par formation';
$athTableCount = count($trainingCourseStats);
$athTableCols = ['FORMATION', 'VUES|r', 'DURÉE MOY.|r', 'INSCRITS|r', 'ACHEVÉES|r', 'TAUX|r', 'FAVORIS|r', 'AVIS|r', 'MENTIONS|r', 'COMMENTAIRES|r', 'ACCÈS PAR CODE|r'];
$athTableRows = [];
foreach ($trainingCourseStats as $row) {
    $enrolled = (int) ($row['enrollments_total'] ?? 0);
    $done = (int) ($row['enrollments_completed'] ?? 0);
    $athTableRows[] = [
        (string) ($row['title'] ?? '—'),
        $int((int) ($row['views_count'] ?? 0)),
        $seconds($row['avg_page_seconds'] ?? null),
        $int($enrolled),
        $int($done),
        $ratioPct($done, $enrolled),
        $int((int) ($row['favorites_count'] ?? 0)),
        $int((int) ($row['reviews_count'] ?? 0)),
        $int((int) ($row['likes_count'] ?? 0)),
        $int((int) ($row['comments_count'] ?? 0)),
        $int((int) ($row['code_uses'] ?? 0)),
    ];
}
$athTableMinWidth = '1620px';
$athTableFoot = $trainingCourseStats === []
    ? 'Aucune formation consultée sur la période.'
    : 'Le taux compare les formations achevées aux inscriptions, toutes périodes confondues.';
require base_path('views/partials/ath_table.php');
?>

<h2 class="ath-section-title">Postes ouverts</h2>

<?php
$athTableTitle = 'Avis de vacance et candidatures';
$athTableCount = count($recruitmentOpeningStats);
$athTableCols = ['POSTE', 'RÉFÉRENCE|m', 'VUES|r', 'DURÉE MOY.|r', 'CANDIDATURES PÉRIODE|r', 'CANDIDATURES TOTAL|r'];
$athTableRows = [];
foreach ($recruitmentOpeningStats as $ro) {
    $athTableRows[] = [
        (string) ($ro['title'] ?? '—'),
        (string) ($ro['reference_public'] ?? '—'),
        $int((int) ($ro['views_count'] ?? 0)),
        $seconds($ro['avg_page_seconds'] ?? null),
        $int((int) ($ro['applications_period'] ?? 0)),
        $int((int) ($ro['applications_total'] ?? 0)),
    ];
}
$athTableMinWidth = '1180px';
$athTableFoot = $recruitmentOpeningStats === []
    ? 'Aucun poste ouvert consulté sur la période.'
    : 'Les durées ne sont comptées que si le visiteur a accepté la mesure d’audience.';
require base_path('views/partials/ath_table.php');
