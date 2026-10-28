<?php
declare(strict_types=1);

use App\Services\Analytics\TenantAnalyticsLabels;

/**
 * Indicateurs d’usage — back-office communauté (/back-office/analytics et /back-office/analytics/conversion).
 *
 * L’en-tête de page est rendu par la coque back-office. Mise en page : barre de période et de vue,
 * synthèse « En bref », sommaire collant à gauche, puis une section par thème. Chaque section porte
 * l’origine de ses chiffres : « Base » (comptés dans la base du portail, fiables) ou « Mesure d’audience »
 * (suivi d’usage, dépend du consentement des visiteurs, peut sous-estimer la réalité).
 * Sur /conversion, la section recrutement passe en tête.
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
 * @var array{total_events: int, distinct_actors: int, external_actors?: int, events_with_duration: int, avg_duration_seconds: ?float} $tenantUsageSummary
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
    'external_actors' => 0,
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
$funnelLast7 += ['visits' => 0, 'cta_clicks' => 0, 'applications' => 0, 'accepted' => 0];
$funnelPrev7Only = is_array($funnelPrev7Only ?? null) ? $funnelPrev7Only : [];
$funnelPrev7Only += ['visits' => 0, 'cta_clicks' => 0, 'applications' => 0, 'accepted' => 0];

// ── Mise en forme ────────────────────────────────────────────────────────

$int = static fn (mixed $v): string => number_format((int) $v, 0, ',', ' ');
$ratioPct = static function (int $num, int $den): string {
    if ($den < 1) {
        return '—';
    }

    return number_format(100.0 * $num / $den, 1, ',', ' ') . ' %';
};
$barPct = static function (int $num, int $den): float {
    if ($den < 1 || $num < 1) {
        return 0.0;
    }

    return round(max(0.0, min(100.0, 100.0 * $num / $den)), 1);
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
$fmtDay = static function (string $raw): string {
    $t = strtotime($raw);

    return $t ? date('d/m', $t) : $raw;
};
// Écart entre deux semaines : « +12 % », « −8 % », « nouveau » ou « stable ».
$delta = static function (int $now, int $before): array {
    if ($before < 1) {
        return $now > 0 ? ['nouveau', 'up'] : ['—', 'flat'];
    }
    $pct = (int) round(100.0 * ($now - $before) / $before);
    if ($pct === 0) {
        return ['stable', 'flat'];
    }

    return [($pct > 0 ? '+' : '−') . abs($pct) . ' %', $pct > 0 ? 'up' : 'down'];
};

$icons = [
    'pulse' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'funnel' => '<path d="M3 4h18l-7 8.5V19l-4 2v-8.5z"/>',
    'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16"/>',
    'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
    'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    'bulb' => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.6 10.8c.6.5 1 1.2 1 2V16h5.2v-.2c0-.8.4-1.5 1-2A6 6 0 0 0 12 3z"/>',
];
$icon = static function (string $name, string $class = 'bo-an-icon') use ($icons): string {
    return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
};
$source = static function (bool $audience): string {
    return $audience
        ? '<span class="bo-an-source bo-an-source--audience" title="Dépend du consentement « mesure d’audience » des visiteurs : peut sous-estimer la réalité.">Mesure d’audience</span>'
        : '<span class="bo-an-source" title="Compté directement dans la base du portail : fiable en toute circonstance.">Base</span>';
};

/**
 * Liste classée avec barre proportionnelle.
 *
 * @param list<array{0: string, 1: int}> $rows
 */
$rankList = static function (array $rows, string $empty, bool $memberContent = false) use ($h, $int, $ratioPct, $barPct): void {
    if ($rows === []) {
        echo '<p class="bo-an-empty">' . $h($empty) . '</p>';

        return;
    }
    $max = 0;
    $sum = 0;
    foreach ($rows as $r) {
        $max = max($max, $r[1]);
        $sum += $r[1];
    }
    echo '<ol class="bo-an-rank">';
    foreach ($rows as $i => $r) {
        echo '<li class="bo-an-rank__row">'
            . '<span class="bo-an-rank__pos">' . ($i + 1) . '</span>'
            . '<span class="bo-an-rank__label"' . ($memberContent ? ' translate="no"' : '') . '>' . $h($r[0]) . '</span>'
            . '<span class="bo-an-rank__value">' . $h($int($r[1])) . '</span>'
            . '<span class="bo-an-rank__share">' . $h($ratioPct($r[1], $sum)) . '</span>'
            . '<span class="bo-an-rank__bar" aria-hidden="true"><span style="width:' . $barPct($r[1], $max) . '%"></span></span>'
            . '</li>';
    }
    echo '</ol>';
};

// ── Calculs ──────────────────────────────────────────────────────────────

// Activité quotidienne : total, moyenne, pic, et 7 derniers jours contre les 7 précédents.
$dailyMax = 0;
$dailyTotal = 0;
$peakDay = '';
foreach ($tenantDailyEvents as $de) {
    $n = (int) ($de['events'] ?? 0);
    $dailyTotal += $n;
    if ($n > $dailyMax) {
        $dailyMax = $n;
        $peakDay = (string) ($de['day'] ?? '');
    }
}
$dailyCount = count($tenantDailyEvents);
$dailyAvg = $dailyCount > 0 ? $dailyTotal / $dailyCount : 0.0;
$last7Events = 0;
$prev7Events = 0;
foreach (array_slice($tenantDailyEvents, -7) as $de) {
    $last7Events += (int) ($de['events'] ?? 0);
}
foreach (array_slice($tenantDailyEvents, -14, 7) as $de) {
    $prev7Events += (int) ($de['events'] ?? 0);
}
$weekDelta = $dailyCount >= 14 ? $delta($last7Events, $prev7Events) : null;

$membersTotal = (int) $operationalKpis['members_active_total'];
$assigned = (int) $operationalKpis['training_enrollments_assigned_in_period'];
$completed = (int) $operationalKpis['training_completions_in_period'];

$enlistTotal = 0;
foreach ($enlistmentStatusBreakdown as $es) {
    $enlistTotal += (int) ($es['count'] ?? 0);
}
$enlistmentStatusMeta = static function (string $status): array {
    return match ($status) {
        'submitted' => ['En attente de décision', 'warn'],
        'reviewed' => ['Traitée', 'ok'],
        'rejected' => ['Rejetée', 'muted'],
        'blocked' => ['Bloquée', 'danger'],
        default => ['Autre état', 'info'],
    };
};

// Conseil de pilotage : calculé sur les 7 derniers jours contre les 7 précédents.
$ctaRateLast7 = (int) $funnelLast7['visits'] > 0 ? (int) $funnelLast7['cta_clicks'] / (int) $funnelLast7['visits'] : null;
$ctaRatePrev7 = (int) $funnelPrev7Only['visits'] > 0 ? (int) $funnelPrev7Only['cta_clicks'] / (int) $funnelPrev7Only['visits'] : null;
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
$suggestionsAlert = $suggestions !== [];
if ($suggestions === []) {
    $suggestions[] = 'Conversion stable cette semaine : poursuivez les essais sur vos accroches et maintenez la fréquence de réponse.';
}

$funnelSteps = [
    ['Visites publiques', (int) $conversionFunnel['visits'], 'visits'],
    ['Clics d’action', (int) $conversionFunnel['cta_clicks'], 'cta_clicks'],
    ['Candidatures', (int) $conversionFunnel['applications'], 'applications'],
    ['Acceptations', (int) $conversionFunnel['accepted'], 'accepted'],
];
$funnelTop = max(1, (int) $conversionFunnel['visits'], (int) $conversionFunnel['cta_clicks'], (int) $conversionFunnel['applications'], (int) $conversionFunnel['accepted']);
$medianHours = $conversionFunnel['median_visit_to_first_contact_hours'];

$docsTotal = (int) $documentInsights['total_documents'];
$docsPublished = (int) $documentInsights['published_documents'];
$docsOverdue = (int) $documentInsights['review_overdue_documents'];
$docsStale = (int) $documentInsights['stale_published_documents'];
$docsExpiring = (int) $documentInsights['expiring_soon_documents'];
$topTypes = is_array($documentInsights['top_types'] ?? null) ? $documentInsights['top_types'] : [];

// ── Navigation ───────────────────────────────────────────────────────────

$basePath = $analyticsFocus === 'conversion' ? 'back-office/analytics/conversion' : 'back-office/analytics';
$periodOptions = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
$sinceTs = $since !== '' ? strtotime($since) : false;
$windowLabel = $sinceTs ? 'Du ' . date('d/m/Y', $sinceTs) . ' à aujourd’hui' : $analyticsDays . ' jours glissants';

$sections = [
    'activite' => ['Activité du portail', 'pulse'],
    'usage' => ['Qui utilise le portail', 'users'],
    'recrutement' => ['Recrutement & conversion', 'funnel'],
    'formations' => ['Formations', 'book'],
    'documents' => ['Documents', 'file'],
];
if ($analyticsFocus === 'conversion') {
    $sections = ['recrutement' => $sections['recrutement']] + $sections;
}
?>
<div class="bo-an" data-an-root>

<div class="bo-an__toolbar">
    <nav class="bo-an-seg" aria-label="Période d’observation">
        <?php foreach ($periodOptions as $days => $label): ?>
        <a href="<?= $h(url($basePath) . '?days=' . $days) ?>" class="bo-an-seg__opt"<?= $days === $analyticsDays ? ' aria-current="true"' : '' ?>><?= $h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="bo-an__window"><?= $h($windowLabel) ?></span>
    <span class="bo-an__spacer" aria-hidden="true"></span>
    <nav class="bo-an-seg" aria-label="Vue">
        <a href="<?= $h(url('back-office/analytics') . '?days=' . $analyticsDays) ?>" class="bo-an-seg__opt"<?= $analyticsFocus !== 'conversion' ? ' aria-current="page"' : '' ?>>Vue d’ensemble</a>
        <a href="<?= $h(url('back-office/analytics/conversion') . '?days=' . $analyticsDays) ?>" class="bo-an-seg__opt"<?= $analyticsFocus === 'conversion' ? ' aria-current="page"' : '' ?>>Entonnoir de conversion</a>
    </nav>
</div>

<section class="bo-an-brief" aria-label="En bref">
    <div class="bo-an-brief__card">
        <span class="bo-an-brief__label">Membres actifs</span>
        <span class="bo-an-brief__value"><?= $h($int($membersTotal)) ?></span>
        <span class="bo-an-brief__meta"><strong>+<?= $h($int($operationalKpis['members_registered_in_period'])) ?></strong> arrivée<?= (int) $operationalKpis['members_registered_in_period'] > 1 ? 's' : '' ?> sur la période</span>
    </div>
    <div class="bo-an-brief__card">
        <span class="bo-an-brief__label">Membres vus actifs</span>
        <span class="bo-an-brief__value"><?= $h($int($activeApprox)) ?></span>
        <span class="bo-an-brief__meta"><?= $membersTotal > 0 ? $h($ratioPct($activeApprox, $membersTotal)) . ' des membres' : 'estimation sur la période' ?></span>
    </div>
    <div class="bo-an-brief__card">
        <span class="bo-an-brief__label">Visites publiques</span>
        <span class="bo-an-brief__value"><?= $h($int($publicEngagement['public_views'])) ?></span>
        <span class="bo-an-brief__meta"><?= $h($int($publicEngagement['cta_clicks'])) ?> clics d’action</span>
    </div>
    <div class="bo-an-brief__card">
        <span class="bo-an-brief__label">Candidatures</span>
        <span class="bo-an-brief__value"><?= $h($int($operationalKpis['enlistments_created_in_period'])) ?></span>
        <span class="bo-an-brief__meta"><strong><?= $h($int($conversionFunnel['accepted'])) ?></strong> acceptée<?= (int) $conversionFunnel['accepted'] > 1 ? 's' : '' ?></span>
    </div>
</section>

<div class="bo-an__layout">
    <nav class="bo-an-nav" aria-label="Sections de la page">
        <p class="bo-an-nav__kicker">Sections</p>
        <ul class="bo-an-nav__list">
            <?php foreach ($sections as $id => [$label, $ic]): ?>
            <li><a class="bo-an-nav__link" href="#an-<?= $h($id) ?>" data-an-nav="an-<?= $h($id) ?>"><?= $icon($ic) ?><span><?= $h($label) ?></span></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="bo-an-nav__legend">
            <p><?= $source(false) ?> comptés dans la base du portail, fiables.</p>
            <p><?= $source(true) ?> suivi d’usage, dépend du consentement des visiteurs.</p>
        </div>
    </nav>

    <div class="bo-an__main">
<?php foreach (array_keys($sections) as $sectionId): ?>
<?php if ($sectionId === 'activite'): ?>
        <section class="bo-an-section" id="an-activite" aria-labelledby="an-activite-t">
            <header class="bo-an-section__head">
                <span class="bo-an-section__icon"><?= $icon('pulse') ?></span>
                <div>
                    <h2 class="bo-an-section__title" id="an-activite-t">Activité du portail</h2>
                    <p class="bo-an-section__lead">Ce que font les membres sur la période : connexions, échanges, formations.</p>
                </div>
            </header>

            <div class="bo-an-block">
                <div class="bo-an-block__head">
                    <h3 class="bo-an-block__title">Événements par jour</h3>
                    <?= $source(true) ?>
                </div>
                <?php if ($tenantDailyEvents === [] || $dailyTotal === 0): ?>
                <p class="bo-an-empty">Aucun événement enregistré sur la période.</p>
                <?php else: ?>
                <dl class="bo-an-figures">
                    <div><dt>Total</dt><dd><?= $h($int($dailyTotal)) ?></dd></div>
                    <div><dt>Moyenne par jour</dt><dd><?= $h(number_format($dailyAvg, 0, ',', ' ')) ?></dd></div>
                    <div><dt>Jour le plus actif</dt><dd><?= $h($fmtDay($peakDay)) ?> · <?= $h($int($dailyMax)) ?></dd></div>
                    <?php if ($weekDelta !== null): ?>
                    <div><dt>7 derniers jours</dt><dd><?= $h($int($last7Events)) ?> <span class="bo-an-delta bo-an-delta--<?= $h($weekDelta[1]) ?>"><?= $h($weekDelta[0]) ?></span></dd></div>
                    <?php endif; ?>
                </dl>
                <div class="bo-an-chart" role="img" aria-label="Événements par jour sur <?= (int) $analyticsDays ?> jours, pic de <?= $h($int($dailyMax)) ?>">
                    <div class="bo-an-chart__grid" aria-hidden="true">
                        <span data-v="<?= $h($int($dailyMax)) ?>"></span>
                        <span data-v="<?= $h($int((int) round($dailyMax / 2))) ?>"></span>
                        <span data-v="0"></span>
                    </div>
                    <div class="bo-an-chart__bars<?= $dailyCount > 45 ? ' bo-an-chart__bars--dense' : '' ?>">
                        <?php foreach ($tenantDailyEvents as $de): ?>
                            <?php
                            $n = (int) ($de['events'] ?? 0);
                            $dayRaw = (string) ($de['day'] ?? '');
                            $ts = strtotime($dayRaw);
                            $weekend = $ts !== false && (int) date('N', $ts) >= 6;
                            $pct = $dailyMax > 0 ? max(1.5, round($n / $dailyMax * 100, 1)) : 1.5;
                            ?>
                        <span class="bo-an-chart__col<?= $weekend ? ' is-weekend' : '' ?><?= $n === 0 ? ' is-empty' : '' ?>" title="<?= $h($fmtDay($dayRaw) . ' : ' . $int($n)) ?>">
                            <span class="bo-an-chart__bar" style="height:<?= $pct ?>%"></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <div class="bo-an-chart__axis" aria-hidden="true">
                        <span><?= $h($fmtDay((string) ($tenantDailyEvents[0]['day'] ?? ''))) ?></span>
                        <span><?= $h($fmtDay((string) ($tenantDailyEvents[intdiv($dailyCount, 2)]['day'] ?? ''))) ?></span>
                        <span><?= $h($fmtDay((string) ($tenantDailyEvents[$dailyCount - 1]['day'] ?? ''))) ?></span>
                    </div>
                </div>
                <p class="bo-an-foot"><span class="bo-an-swatch bo-an-swatch--weekend" aria-hidden="true"></span>Samedis et dimanches en teinte plus claire. Survolez une barre pour lire le jour et sa valeur.</p>
                <?php endif; ?>
            </div>

            <div class="bo-an-metrics">
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Actions journalisées</span>
                    <span class="bo-an-metric__value"><?= $h($int($operationalKpis['audit_actions_in_period'])) ?></span>
                    <span class="bo-an-metric__note">journal d’audit <?= $source(false) ?></span>
                </div>
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Formations achevées</span>
                    <span class="bo-an-metric__value"><?= $h($int($completed)) ?> <small>/ <?= $h($int($assigned)) ?></small></span>
                    <span class="bo-an-metric__meter" aria-hidden="true"><span style="width:<?= $barPct($completed, max(1, $assigned)) ?>%"></span></span>
                    <span class="bo-an-metric__note">sur les inscriptions de la période <?= $source(false) ?></span>
                </div>
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Consultations du hub</span>
                    <span class="bo-an-metric__value"><?= $h($int($dashboardEvents)) ?></span>
                    <span class="bo-an-metric__note">ouvertures du tableau de bord <?= $source(true) ?></span>
                </div>
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Catalogue des formations</span>
                    <span class="bo-an-metric__value"><?= $h($int($trainingCatalogViews)) ?></span>
                    <span class="bo-an-metric__note">consultations <?= $source(true) ?></span>
                </div>
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Durée moyenne</span>
                    <span class="bo-an-metric__value"><?= $h($seconds($tenantUsageSummary['avg_duration_seconds'])) ?></span>
                    <span class="bo-an-metric__note"><?= $h($int($tenantUsageSummary['events_with_duration'])) ?> consultations mesurées <?= $source(true) ?></span>
                </div>
            </div>
        </section>
<?php elseif ($sectionId === 'usage'): ?>
        <section class="bo-an-section" id="an-usage" aria-labelledby="an-usage-t">
            <header class="bo-an-section__head">
                <span class="bo-an-section__icon"><?= $icon('users') ?></span>
                <div>
                    <h2 class="bo-an-section__title" id="an-usage-t">Qui utilise le portail</h2>
                    <?php if ((int) $tenantUsageSummary['external_actors'] > 0): ?>
                    <p class="bo-an-section__lead"><?= $h($int($tenantUsageSummary['total_events'])) ?> événements enregistrés, par <?= $h($int($tenantUsageSummary['distinct_actors'])) ?> membres de la communauté et <?= $h($int($tenantUsageSummary['external_actors'])) ?> visiteurs d’autres communautés, non nommés.</p>
                    <?php else: ?>
                    <p class="bo-an-section__lead"><?= $h($int($tenantUsageSummary['total_events'])) ?> événements enregistrés, par <?= $h($int($tenantUsageSummary['distinct_actors'])) ?> membres de la communauté.</p>
                    <?php endif; ?>
                </div>
                <?= $source(true) ?>
            </header>

            <div class="bo-an-cols">
                <div class="bo-an-block">
                    <div class="bo-an-block__head"><h3 class="bo-an-block__title">Membres les plus actifs</h3></div>
                    <?php
                    $rows = [];
                    foreach ($tenantTopActors as $row) {
                        $rows[] = [(string) ($row['actor_label'] ?? '—'), (int) ($row['events'] ?? 0)];
                    }
                    $rankList($rows, 'Aucun acteur identifié sur la période.', true);
                    ?>
                    <?php if ($rows !== []): ?>
                    <p class="bo-an-foot">Part calculée sur les membres listés, pas sur toute la communauté.</p>
                    <?php endif; ?>
                </div>
                <div class="bo-an-block">
                    <div class="bo-an-block__head"><h3 class="bo-an-block__title">Volume par rubrique</h3></div>
                    <?php
                    $rows = [];
                    foreach ($tenantCategoryBreakdown as $row) {
                        $rows[] = [TenantAnalyticsLabels::categoryLabel((string) ($row['category'] ?? '')), (int) ($row['events'] ?? 0)];
                    }
                    $rankList($rows, 'Aucun événement enregistré sur la période.');
                    ?>
                </div>
            </div>

            <div class="bo-an-block">
                <div class="bo-an-block__head"><h3 class="bo-an-block__title">Actions les plus fréquentes</h3></div>
                <?php
                $rows = [];
                foreach ($tenantTopEventNames as $row) {
                    $rows[] = [TenantAnalyticsLabels::eventNameLabel((string) ($row['name'] ?? '')), (int) ($row['events'] ?? 0)];
                }
                $rankList($rows, 'Aucune action enregistrée sur la période.');
                ?>
            </div>
        </section>
<?php elseif ($sectionId === 'recrutement'): ?>
        <section class="bo-an-section<?= $analyticsFocus === 'conversion' ? ' is-focus' : '' ?>" id="an-recrutement" aria-labelledby="an-recrutement-t">
            <header class="bo-an-section__head">
                <span class="bo-an-section__icon"><?= $icon('funnel') ?></span>
                <div>
                    <h2 class="bo-an-section__title" id="an-recrutement-t">Recrutement &amp; conversion</h2>
                    <p class="bo-an-section__lead">Combien de visiteurs de la fiche publique deviennent candidats, puis membres.</p>
                </div>
            </header>

            <div class="bo-an-block" id="conversion-funnel">
                <div class="bo-an-block__head">
                    <h3 class="bo-an-block__title">Entonnoir de conversion</h3>
                    <?= $source(true) ?>
                </div>
                <ol class="bo-an-funnel">
                    <?php foreach ($funnelSteps as $i => [$label, $value]): ?>
                    <li class="bo-an-funnel__step">
                        <?php if ($i > 0 && $funnelSteps[$i - 1][1] > 0): ?>
                        <span class="bo-an-funnel__rate"><?= $h($ratioPct($value, $funnelSteps[$i - 1][1])) ?> de l’étape précédente</span>
                        <?php endif; ?>
                        <div class="bo-an-funnel__row">
                            <span class="bo-an-funnel__label"><?= $h($label) ?></span>
                            <span class="bo-an-funnel__track" aria-hidden="true"><span class="bo-an-funnel__bar bo-an-funnel__bar--s<?= $i ?>" style="width:<?= max(0.6, $barPct($value, $funnelTop)) ?>%"></span></span>
                            <span class="bo-an-funnel__value"><?= $h($int($value)) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <dl class="bo-an-figures bo-an-figures--tight">
                    <div><dt>Visite → clic d’action</dt><dd><?= $h($ratioPct((int) $conversionFunnel['cta_clicks'], (int) $conversionFunnel['visits'])) ?></dd></div>
                    <div><dt>Candidature → acceptation</dt><dd><?= $h($ratioPct((int) $conversionFunnel['accepted'], (int) $conversionFunnel['applications'])) ?></dd></div>
                    <div><dt>Délai médian jusqu’au premier contact</dt><dd><?= $medianHours !== null ? $h(number_format((float) $medianHours, 1, ',', ' ') . ' h') : '—' ?></dd></div>
                </dl>
                <p class="bo-an-foot">Le délai est approché par la date de revue de la candidature.</p>
            </div>

            <div class="bo-an-cols">
                <div class="bo-an-block">
                    <div class="bo-an-block__head">
                        <h3 class="bo-an-block__title">Cette semaine contre la précédente</h3>
                    </div>
                    <table class="bo-an-table bo-an-table--compact">
                        <thead><tr><th scope="col">Étape</th><th scope="col" class="is-num">Cette semaine</th><th scope="col" class="is-num">Précédente</th><th scope="col" class="is-num">Écart</th></tr></thead>
                        <tbody>
                        <?php foreach ($funnelSteps as [$label, , $key]): ?>
                            <?php [$dLabel, $dTone] = $delta((int) $funnelLast7[$key], (int) $funnelPrev7Only[$key]); ?>
                            <tr>
                                <th scope="row"><?= $h($label) ?></th>
                                <td class="is-num"><?= $h($int($funnelLast7[$key])) ?></td>
                                <td class="is-num"><?= $h($int($funnelPrev7Only[$key])) ?></td>
                                <td class="is-num"><span class="bo-an-delta bo-an-delta--<?= $h($dTone) ?>"><?= $h($dLabel) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bo-an-advice<?= $suggestionsAlert ? ' bo-an-advice--warn' : '' ?>">
                    <span class="bo-an-advice__icon"><?= $icon('bulb') ?></span>
                    <div>
                        <p class="bo-an-advice__title">Prochaine action recommandée</p>
                        <?php foreach ($suggestions as $suggestion): ?>
                        <p class="bo-an-advice__text"><?= $h($suggestion) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="bo-an-cols">
                <div class="bo-an-block">
                    <div class="bo-an-block__head">
                        <h3 class="bo-an-block__title">Candidatures de la période, par état</h3>
                        <?= $source(false) ?>
                    </div>
                    <?php if ($enlistmentStatusBreakdown === [] || $enlistTotal === 0): ?>
                    <p class="bo-an-empty">Aucune candidature déposée sur la période.</p>
                    <?php else: ?>
                    <div class="bo-an-stack" aria-hidden="true">
                        <?php foreach ($enlistmentStatusBreakdown as $es): ?>
                            <?php $n = (int) ($es['count'] ?? 0); [, $tone] = $enlistmentStatusMeta((string) ($es['status'] ?? '')); ?>
                            <?php if ($n > 0): ?>
                        <span class="bo-an-stack__seg bo-an-tone--<?= $h($tone) ?>" style="flex-grow:<?= $n ?>"></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <ul class="bo-an-legend">
                        <?php foreach ($enlistmentStatusBreakdown as $es): ?>
                            <?php $n = (int) ($es['count'] ?? 0); [$label, $tone] = $enlistmentStatusMeta((string) ($es['status'] ?? '')); ?>
                        <li><span class="bo-an-dot bo-an-tone--<?= $h($tone) ?>" aria-hidden="true"></span><span class="bo-an-legend__label"><?= $h($label) ?></span><strong><?= $h($int($n)) ?></strong><span class="bo-an-legend__share"><?= $h($ratioPct($n, $enlistTotal)) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="bo-an-foot">Compté à la date de dépôt, indépendamment de la date de décision.</p>
                    <?php endif; ?>
                </div>
                <div class="bo-an-block">
                    <div class="bo-an-block__head">
                        <h3 class="bo-an-block__title">Parcours du candidat</h3>
                        <?= $source(true) ?>
                    </div>
                    <dl class="bo-an-figures bo-an-figures--grid">
                        <div><dt>Durée moyenne sur la fiche</dt><dd><?= $h($seconds($publicEngagement['public_duration_avg'])) ?></dd></div>
                        <div><dt>Clics d’action</dt><dd><?= $h($int($publicEngagement['cta_clicks'])) ?><?php if ((int) $publicEngagement['public_views'] > 0): ?> <small><?= $h($ratioPct((int) $publicEngagement['cta_clicks'], (int) $publicEngagement['public_views'])) ?> des visites</small><?php endif; ?></dd></div>
                        <div><dt>Formulaires ouverts</dt><dd><?= $h($int($publicEngagement['enlistment_opens'])) ?></dd></div>
                        <div><dt>Candidatures envoyées</dt><dd><?= $h($int($publicEngagement['enlistment_submits'])) ?><?php if ((int) $publicEngagement['enlistment_opens'] > 0): ?> <small><?= $h($ratioPct((int) $publicEngagement['enlistment_submits'], (int) $publicEngagement['enlistment_opens'])) ?> des formulaires</small><?php endif; ?></dd></div>
                    </dl>
                </div>
            </div>

            <div class="bo-an-block">
                <div class="bo-an-block__head">
                    <h3 class="bo-an-block__title">Postes ouverts</h3>
                    <span class="bo-an-count"><?= count($recruitmentOpeningStats) ?></span>
                </div>
                <?php if ($recruitmentOpeningStats === []): ?>
                <p class="bo-an-empty">Aucun poste ouvert consulté sur la période.</p>
                <?php else: ?>
                <div class="bo-an-scroll">
                    <table class="bo-an-table">
                        <thead><tr><th scope="col">Poste</th><th scope="col">Référence</th><th scope="col" class="is-num">Vues</th><th scope="col" class="is-num">Durée moy.</th><th scope="col" class="is-num">Candidatures période</th><th scope="col" class="is-num">Candidatures total</th></tr></thead>
                        <tbody>
                        <?php foreach ($recruitmentOpeningStats as $ro): ?>
                            <tr>
                                <th scope="row" translate="no"><?= $h((string) ($ro['title'] ?? '—')) ?></th>
                                <td><span class="bo-an-mono"><?= $h((string) ($ro['reference_public'] ?? '—')) ?></span></td>
                                <td class="is-num"><?= $h($int((int) ($ro['views_count'] ?? 0))) ?></td>
                                <td class="is-num"><?= $h($seconds($ro['avg_page_seconds'] ?? null)) ?></td>
                                <td class="is-num"><strong><?= $h($int((int) ($ro['applications_period'] ?? 0))) ?></strong></td>
                                <td class="is-num"><?= $h($int((int) ($ro['applications_total'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="bo-an-foot">Les durées ne sont comptées que si le visiteur a accepté la mesure d’audience.</p>
                <?php endif; ?>
            </div>
        </section>
<?php elseif ($sectionId === 'formations'): ?>
        <section class="bo-an-section" id="an-formations" aria-labelledby="an-formations-t">
            <header class="bo-an-section__head">
                <span class="bo-an-section__icon"><?= $icon('book') ?></span>
                <div>
                    <h2 class="bo-an-section__title" id="an-formations-t">Formations</h2>
                    <p class="bo-an-section__lead">Consultations et engagement par formation du catalogue.</p>
                </div>
                <span class="bo-an-count"><?= count($trainingCourseStats) ?></span>
            </header>
            <?php if ($trainingCourseStats === []): ?>
            <p class="bo-an-empty">Aucune formation consultée sur la période.</p>
            <?php else: ?>
            <div class="bo-an-scroll">
                <table class="bo-an-table">
                    <thead>
                        <tr>
                            <th scope="col">Formation</th>
                            <th scope="col" class="is-num">Vues</th>
                            <th scope="col" class="is-num">Durée moy.</th>
                            <th scope="col">Achèvement</th>
                            <th scope="col" class="is-num">Favoris</th>
                            <th scope="col" class="is-num">Avis</th>
                            <th scope="col" class="is-num" title="Mentions « j’aime »">J’aime</th>
                            <th scope="col" class="is-num" title="Commentaires">Comm.</th>
                            <th scope="col" class="is-num" title="Accès par code de partage">Codes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($trainingCourseStats as $row): ?>
                        <?php
                        $enrolled = (int) ($row['enrollments_total'] ?? 0);
                        $done = (int) ($row['enrollments_completed'] ?? 0);
                        ?>
                        <tr>
                            <th scope="row" translate="no"><?= $h((string) ($row['title'] ?? '—')) ?></th>
                            <td class="is-num"><?= $h($int((int) ($row['views_count'] ?? 0))) ?></td>
                            <td class="is-num"><?= $h($seconds($row['avg_page_seconds'] ?? null)) ?></td>
                            <td>
                                <?php if ($enrolled > 0): ?>
                                <span class="bo-an-progress">
                                    <span class="bo-an-progress__track" aria-hidden="true"><span style="width:<?= $barPct($done, $enrolled) ?>%"></span></span>
                                    <span class="bo-an-progress__text"><?= $h($int($done)) ?> / <?= $h($int($enrolled)) ?> · <?= $h($ratioPct($done, $enrolled)) ?></span>
                                </span>
                                <?php else: ?>
                                <span class="bo-an-muted">Aucun inscrit</span>
                                <?php endif; ?>
                            </td>
                            <td class="is-num"><?= $h($int((int) ($row['favorites_count'] ?? 0))) ?></td>
                            <td class="is-num"><?= $h($int((int) ($row['reviews_count'] ?? 0))) ?></td>
                            <td class="is-num"><?= $h($int((int) ($row['likes_count'] ?? 0))) ?></td>
                            <td class="is-num"><?= $h($int((int) ($row['comments_count'] ?? 0))) ?></td>
                            <td class="is-num"><?= $h($int((int) ($row['code_uses'] ?? 0))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="bo-an-foot">L’achèvement compare les formations achevées aux inscriptions, toutes périodes confondues. Vues et durées : mesure d’audience.</p>
            <?php endif; ?>
        </section>
<?php elseif ($sectionId === 'documents'): ?>
        <section class="bo-an-section" id="an-documents" aria-labelledby="an-documents-t">
            <header class="bo-an-section__head">
                <span class="bo-an-section__icon"><?= $icon('file') ?></span>
                <div>
                    <h2 class="bo-an-section__title" id="an-documents-t">Documents</h2>
                    <p class="bo-an-section__lead">État de la documentation : ce qui est publié, à jour ou à revoir.</p>
                </div>
                <?= $source(false) ?>
            </header>
            <div class="bo-an-metrics">
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Documents</span>
                    <span class="bo-an-metric__value"><?= $h($int($docsTotal)) ?></span>
                    <span class="bo-an-metric__note">toutes catégories</span>
                </div>
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Publiés</span>
                    <span class="bo-an-metric__value"><?= $h($int($docsPublished)) ?></span>
                    <span class="bo-an-metric__meter" aria-hidden="true"><span style="width:<?= $barPct($docsPublished, max(1, $docsTotal)) ?>%"></span></span>
                    <span class="bo-an-metric__note"><?= $docsTotal > 0 ? $h($ratioPct($docsPublished, $docsTotal)) . ' ' : '' ?>visibles des membres</span>
                </div>
                <div class="bo-an-metric">
                    <span class="bo-an-metric__label">Mis à jour</span>
                    <span class="bo-an-metric__value"><?= $h($int($documentInsights['updated_in_period'])) ?></span>
                    <span class="bo-an-metric__note">sur la période</span>
                </div>
                <div class="bo-an-metric<?= $docsOverdue > 0 ? ' bo-an-metric--danger' : '' ?>">
                    <span class="bo-an-metric__label">Revue en retard</span>
                    <span class="bo-an-metric__value"><?= $h($int($docsOverdue)) ?></span>
                    <span class="bo-an-metric__note">
                        <span><?= $h($int($docsStale)) ?> publiés sans révision</span><?php if ($docsExpiring > 0): ?> · <strong><?= $h($int($docsExpiring)) ?> à échéance</strong><?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="bo-an-block">
                <div class="bo-an-block__head"><h3 class="bo-an-block__title">Types de documents les plus représentés</h3></div>
                <?php
                $rows = [];
                foreach ($topTypes as $t) {
                    $rows[] = [(string) ($t['document_type'] ?? '—'), (int) ($t['count'] ?? 0)];
                }
                $rankList($rows, 'Aucun document enregistré.');
                ?>
            </div>
        </section>
<?php endif; ?>
<?php endforeach; ?>
    </div>
</div>
</div>
<script src="<?= $h(url('assets/js/back-office-analytics.js')) ?>" defer></script>
