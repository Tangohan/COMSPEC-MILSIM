<?php
declare(strict_types=1);

$heritage = is_array($heritage ?? null) ? $heritage : [];
$period = (string) ($heritagePeriod ?? 'all');
$live = is_array($heritage['live'] ?? null) ? $heritage['live'] : [];
$timeline = is_array($heritage['timeline'] ?? null) ? $heritage['timeline'] : [];
$chart = is_array($heritage['chart'] ?? null) ? $heritage['chart'] : [];
$versions = is_array($heritage['versions'] ?? null) ? $heritage['versions'] : [];
$nextGoal = is_array($heritage['next_goal'] ?? null) ? $heritage['next_goal'] : null;
$honesty = (string) ($heritage['honesty_note'] ?? __('site.hist_honesty_note'));
$loggedIn = (bool) \App\Core\Session::get('user_id');
$createHref = $loggedIn ? url('communities/create') : url('register');
$base = rtrim(url(''), '/');
$heroImage = $base . '/assets/images/fog-team.jpg';
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$fmtNum = static function (int|float|null $n, int $decimals = 0): string {
    if ($n === null) {
        return '—';
    }
    [$dec, $thousands] = (function_exists('locale') && locale() === 'en') ? ['.', ','] : [',', ' '];

    return number_format((float) $n, $decimals, $dec, $thousands);
};

$formatDateLabel = static function (?string $occurredAt, string $precision): string {
    if ($occurredAt === null || $occurredAt === '' || $precision === 'unknown') {
        return (string) __('site.hist_date_unknown');
    }
    $day = substr($occurredAt, 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        return (string) __('site.hist_date_unknown');
    }
    try {
        $dt = new DateTimeImmutable($day);
        $locale = function_exists('locale') && locale() === 'en' ? 'en_GB' : 'fr_FR';
        if (class_exists(IntlDateFormatter::class)) {
            $fmt = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE);
            $label = (string) $fmt->format($dt);
        } else {
            $label = $dt->format('d/m/Y');
        }
    } catch (Throwable) {
        $label = $day;
    }
    if ($precision === 'day') {
        return (string) __('site.hist_date_approx_day') . ' ' . $label;
    }
    if ($precision === 'month') {
        return substr($day, 0, 7);
    }

    return $label;
};

$kindLabel = static function (string $kind): string {
    return match ($kind) {
        'version' => (string) __('site.hist_kind_version'),
        'threshold' => (string) __('site.hist_kind_threshold'),
        'first' => (string) __('site.hist_kind_first'),
        default => (string) __('site.hist_kind_custom'),
    };
};

$emptyStats = [
    'scope' => 'platform', 'community_name' => null, 'founded_at' => null, 'members' => 0, 'operations' => 0,
    'flight_hours' => 0, 'academy_validated' => 0, 'orbat_units' => 0, 'years' => [], 'highlights' => [],
];
$platformStats = is_array($platformStats ?? null) ? $platformStats + $emptyStats : $emptyStats;
$communityStats = is_array($communityStats ?? null) ? $communityStats + $emptyStats : null;

$platformYear = is_string($platformStats['founded_at']) && preg_match('/^(\d{4})-\d{2}-\d{2}$/', $platformStats['founded_at'], $m) === 1
    ? $m[1]
    : null;
$heroFacts = [
    ['v' => $platformYear ?? '—', 'k' => (string) __('site.hist_fact_since')],
    ['v' => $fmtNum((int) $platformStats['members']), 'k' => (string) __('site.hist_key_members')],
    ['v' => $fmtNum((int) $platformStats['operations']), 'k' => (string) __('site.hist_key_operations')],
    ['v' => $fmtNum((int) $platformStats['flight_hours']) . ' h', 'k' => (string) __('site.hist_key_flight')],
];

// Cartes « chiffres clés » : un compteur à zéro affiche « À venir » plutôt qu’un 0 sec.
$keyCards = static function (array $stats, bool $community) use ($fmtNum, $formatDateLabel): array {
    $founded = isset($stats['founded_at']) && is_string($stats['founded_at']) ? $stats['founded_at'] : null;
    $cards = [
        ['n' => null, 'v' => $founded !== null ? $formatDateLabel($founded . ' 00:00:00', 'exact') : '', 'k' => 'hist_key_founded', 'h' => $community ? 'hist_key_founded_comm_h' : 'hist_key_founded_h', 'icon' => 'flag'],
        ['n' => (int) ($stats['members'] ?? 0), 'k' => 'hist_key_members', 'h' => 'hist_key_members_h', 'icon' => 'members'],
        ['n' => (int) ($stats['operations'] ?? 0), 'k' => 'hist_key_operations', 'h' => 'hist_key_operations_h', 'icon' => 'target'],
        ['n' => (int) ($stats['flight_hours'] ?? 0), 'suffix' => ' h', 'k' => 'hist_key_flight', 'h' => 'hist_key_flight_h', 'icon' => 'flight'],
        ['n' => (int) ($stats['academy_validated'] ?? 0), 'k' => 'hist_key_academy', 'h' => 'hist_key_academy_h', 'icon' => 'academy'],
        ['n' => (int) ($stats['orbat_units'] ?? 0), 'k' => 'hist_key_orbat', 'h' => 'hist_key_orbat_h', 'icon' => 'orbat'],
    ];
    $out = [];
    foreach ($cards as $c) {
        $pending = $c['n'] === null ? ($c['v'] ?? '') === '' : $c['n'] <= 0;
        $value = $c['n'] === null ? (string) ($c['v'] ?? '') : $fmtNum($c['n']) . ($c['suffix'] ?? '');
        $out[] = [
            'v' => $pending ? (string) __('site.hist_key_pending') : $value,
            'k' => (string) __('site.' . $c['k']),
            'h' => $pending ? (string) __('site.hist_key_pending_h') : (string) __('site.' . $c['h']),
            'icon' => $c['icon'],
            'pending' => $pending,
        ];
    }

    return $out;
};

$icons = [
    'flag' => '<path d="M5 21V4m0 0h11l-2 4 2 4H5"/>',
    'members' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.6-3.4 3-5.4 6-5.4s5.4 2 6 5.4"/><path d="M16 4.6a3 3 0 0 1 0 6M18.5 20c-.3-2-1.2-3.6-2.6-4.6"/>',
    'target' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
    'flight' => '<path d="M2.5 13.5 21 6l-4.5 13-4-5.5-6 3 1.5-4.5z"/>',
    'academy' => '<path d="m2.5 9 9.5-4.5L21.5 9 12 13.5z"/><path d="M6.5 11v5c3 2.2 8 2.2 11 0v-5"/>',
    'orbat' => '<rect x="9" y="3" width="6" height="4.5" rx="1"/><rect x="2.5" y="16.5" width="6" height="4.5" rx="1"/><rect x="15.5" y="16.5" width="6" height="4.5" rx="1"/><path d="M12 7.5V12M5.5 16.5V12h13v4.5"/>',
];

$renderKeyGrid = static function (array $cards) use ($e, $icons): void {
    echo '<ul class="site-history-keys" role="list">';
    foreach ($cards as $card) {
        echo '<li class="site-history-key' . ($card['pending'] ? ' is-pending' : '') . '">';
        echo '<span class="site-history-key__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$card['icon']] ?? '') . '</svg></span>';
        echo '<span class="site-history-key__k">' . $e($card['k']) . '</span>';
        echo '<span class="site-history-key__v">' . $e($card['v']) . '</span>';
        echo '<span class="site-history-key__h">' . $e($card['h']) . '</span>';
        echo '</li>';
    }
    echo '</ul>';
};

// Frise annuelle : la barre est proportionnelle aux opérations de l’année la plus active.
$renderYears = static function (array $years) use ($e, $fmtNum): void {
    $years = array_values(array_filter($years, 'is_array'));
    if ($years === []) {
        echo '<p class="site-history__empty">' . $e(__('site.hist_years_empty')) . '</p>';

        return;
    }
    $maxOps = max(1, ...array_map(static fn (array $y): int => (int) ($y['operations'] ?? 0), $years));
    echo '<ol class="site-history-years">';
    foreach (array_reverse($years) as $y) {
        $ops = (int) ($y['operations'] ?? 0);
        $pct = $ops > 0 ? max(4, (int) round(($ops / $maxOps) * 100)) : 0;
        echo '<li class="site-history-year">';
        echo '<span class="site-history-year__y">' . $e($y['year'] ?? '') . '</span>';
        echo '<div class="site-history-year__body">';
        echo '<span class="site-history-year__bar" aria-hidden="true"><span style="width:' . $pct . '%"></span></span>';
        echo '<span class="site-history-year__facts">';
        echo '<span><strong>' . $e($fmtNum($ops)) . '</strong> ' . $e(__('site.hist_years_ops')) . '</span>';
        echo '<span><strong>' . $e($fmtNum((int) ($y['members'] ?? 0))) . '</strong> ' . $e(__('site.hist_years_members')) . '</span>';
        echo '<span><strong>' . $e($fmtNum((int) ($y['flight_hours'] ?? 0))) . '</strong> ' . $e(__('site.hist_years_flight')) . '</span>';
        echo '</span></div></li>';
    }
    echo '</ol>';
};

$chartPayload = [
    'labels' => array_values(is_array($chart['labels'] ?? null) ? $chart['labels'] : []),
    'operators' => array_values(is_array($chart['operators'] ?? null) ? $chart['operators'] : []),
    'organizations' => array_values(is_array($chart['organizations'] ?? null) ? $chart['organizations'] : []),
    'markers' => array_values(is_array($chart['markers'] ?? null) ? $chart['markers'] : []),
    'i18n' => [
        'operators' => (string) __('site.hist_chart_operators'),
        'orgs' => (string) __('site.hist_chart_orgs'),
    ],
];
?>
<article class="site-history" data-site-history>
    <section class="site-history-hero site-history-rise" aria-labelledby="hist-hero-title">
        <div class="site-history-hero__media" aria-hidden="true">
            <img src="<?= $e($heroImage) ?>" alt="" width="1920" height="1080" decoding="async" fetchpriority="high">
        </div>
        <div class="site-history-hero__scrim" aria-hidden="true"></div>
        <div class="site-history-hero__inner">
            <div class="site-history-hero__copy">
                <p class="site-history-hero__pill">
                    <span class="site-history-hero__pulse" aria-hidden="true"></span>
                    <?= $e(__('site.hist_pill')) ?>
                </p>
                <p class="hi-kicker text-emerald-400/90"><?= $e(__('site.hist_kicker')) ?></p>
                <p class="site-history-hero__brand" aria-hidden="true">ATHENA</p>
                <h1 id="hist-hero-title" class="hi-display hi-display-md site-history-hero__title"><?= $e(__('site.hist_title')) ?></h1>
                <p class="hi-body site-history-hero__lead"><?= $e(__('site.hist_lead')) ?></p>
                <div class="site-page__cta site-history-hero__actions">
                    <a href="#frise" class="hi-cta hi-cta-solid"><?= $e(__('site.hist_cta_timeline')) ?></a>
                    <a href="<?= $e(url('a-propos')) ?>" class="hi-cta hi-cta-ghost"><?= $e(__('site.hist_cta_about')) ?></a>
                </div>
            </div>
        </div>
        <div class="site-history-hero__facts">
            <div class="site-history-hero__facts-grid">
                <?php foreach ($heroFacts as $fact): ?>
                    <div class="site-history-hero__fact">
                        <div class="site-history-hero__fact-v"><?= $e($fact['v']) ?></div>
                        <div class="site-history-hero__fact-k"><?= $e(mb_strtoupper($fact['k'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="site-page site-page--wide site-history__body">
        <section id="chiffres" class="site-sse-block site-history-rise" aria-labelledby="hist-keys-title">
            <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_key_kicker')) ?></p>
            <h2 id="hist-keys-title" class="site-sse-block__title"><?= $e(__('site.hist_key_title')) ?></h2>
            <p class="site-sse-block__lead"><?= $e(__('site.hist_key_lead')) ?></p>
            <?php $renderKeyGrid($keyCards($platformStats, false)); ?>
        </section>

        <?php if ($communityStats !== null): ?>
        <section id="communaute" class="site-sse-block site-history-community site-history-rise" aria-labelledby="hist-comm-title">
            <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_comm_kicker')) ?></p>
            <h2 id="hist-comm-title" class="site-sse-block__title"><?= $e(
                !empty($communityStats['community_name'])
                    ? __('site.hist_comm_title', ['name' => (string) $communityStats['community_name']])
                    : __('site.hist_comm_title_generic')
            ) ?></h2>
            <p class="site-sse-block__lead"><?= $e(__('site.hist_comm_lead')) ?></p>
            <?php $renderKeyGrid($keyCards($communityStats, true)); ?>

            <h3 class="site-history-community__sub"><?= $e(__('site.hist_comm_highlights')) ?></h3>
            <?php $highlights = is_array($communityStats['highlights'] ?? null) ? $communityStats['highlights'] : []; ?>
            <?php if ($highlights === []): ?>
                <p class="site-history__empty"><?= $e(__('site.hist_comm_empty')) ?></p>
            <?php else: ?>
                <ol class="site-history-moments">
                    <?php foreach ($highlights as $h): ?>
                        <?php if (!is_array($h)) {
                            continue;
                        }
                        $hKind = ($h['kind'] ?? '') === 'article' ? 'article' : 'operation';
                        ?>
                        <li class="site-history-moment site-history-moment--<?= $e($hKind) ?>">
                            <span class="site-history-moment__meta">
                                <span class="site-history__chip"><?= $e(__('site.hist_comm_kind_' . $hKind)) ?></span>
                                <time class="site-history__when" datetime="<?= $e($h['date'] ?? '') ?>"><?= $e($formatDateLabel(((string) ($h['date'] ?? '')) . ' 00:00:00', 'exact')) ?></time>
                            </span>
                            <span class="site-history-moment__title"><?= $e($h['title'] ?? '') ?></span>
                            <?php if (isset($h['value']) && (int) $h['value'] > 0): ?>
                                <span class="site-history__node-value"><?= $e(__('site.hist_comm_participants', ['value' => $fmtNum((int) $h['value'])])) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <h3 class="site-history-community__sub"><?= $e(__('site.hist_years_title')) ?></h3>
            <?php $renderYears(is_array($communityStats['years'] ?? null) ? $communityStats['years'] : []); ?>
        </section>
        <?php endif; ?>

        <section id="annees" class="site-sse-block site-history-rise" aria-labelledby="hist-years-title">
            <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_years_kicker')) ?></p>
            <h2 id="hist-years-title" class="site-sse-block__title"><?= $e(__('site.hist_years_title')) ?></h2>
            <p class="site-sse-block__lead"><?= $e(__('site.hist_years_lead')) ?></p>
            <?php $renderYears(is_array($platformStats['years'] ?? null) ? $platformStats['years'] : []); ?>
        </section>

        <section class="site-sse-block site-history-rise" aria-labelledby="hist-chart-title">
            <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_chart_kicker')) ?></p>
            <h2 id="hist-chart-title" class="site-sse-block__title"><?= $e(__('site.hist_chart_title')) ?></h2>
            <p class="site-sse-block__lead"><?= $e(__('site.hist_chart_lead')) ?></p>
            <div class="site-history__period" role="group" aria-label="<?= $e(__('site.hist_chart_period')) ?>">
                <?php
                // Clés numériques castées en int par PHP → forcer string avant rawurlencode / comparaisons.
                $periods = [
                    'all' => __('site.hist_chart_period_all'),
                    '365' => __('site.hist_chart_period_365'),
                    '90' => __('site.hist_chart_period_90'),
                ];
                foreach ($periods as $key => $label):
                    $periodKey = (string) $key;
                    $href = url('notre-histoire') . ($periodKey === 'all' ? '' : ('?periode=' . rawurlencode($periodKey)));
                    $active = $period === $periodKey;
                ?>
                    <a href="<?= $e($href) ?>" class="site-history__period-btn<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="site-prose site-history__chart-panel">
                <div class="site-history__chart-wrap">
                    <canvas id="hist-chart" width="960" height="360" role="img" aria-label="<?= $e(__('site.hist_chart_title')) ?>"></canvas>
                    <div class="site-history__chart-legend" aria-hidden="true">
                        <span class="site-history__legend site-history__legend--ops"><?= $e(__('site.hist_chart_operators')) ?></span>
                        <span class="site-history__legend site-history__legend--org"><?= $e(__('site.hist_chart_orgs')) ?></span>
                    </div>
                </div>
            </div>
            <script type="application/json" id="hist-chart-data"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
        </section>

        <section id="frise" class="site-sse-block site-history-rise" aria-labelledby="hist-timeline-title">
            <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_timeline_kicker')) ?></p>
            <h2 id="hist-timeline-title" class="site-sse-block__title"><?= $e(__('site.hist_timeline_title')) ?></h2>
            <p class="site-sse-block__lead"><?= $e(__('site.hist_timeline_lead')) ?></p>

            <?php if ($timeline === []): ?>
                <p class="site-history__empty"><?= $e(__('site.hist_empty_timeline')) ?></p>
            <?php else: ?>
                <ol class="site-sse-steps site-history__timeline">
                    <?php foreach ($timeline as $index => $item): ?>
                        <?php
                        if (!is_array($item)) {
                            continue;
                        }
                        $kind = (string) ($item['kind'] ?? 'custom');
                        $precision = (string) ($item['date_precision'] ?? 'unknown');
                        $stats = is_array($item['stats'] ?? null) ? $item['stats'] : null;
                        $key = (string) ($item['key'] ?? '');
                        $n = sprintf('%02d', $index + 1);
                        ?>
                        <li class="site-sse-step site-history__node site-history__node--<?= $e(preg_replace('/[^a-z0-9\-]/i', '', $kind) ?? 'custom') ?><?= $index === 0 ? ' is-open' : '' ?>" id="<?= $e($key) ?>">
                            <span class="site-sse-step__n"><?= $e($n) ?></span>
                            <div class="site-history__node-body">
                                <button type="button" class="site-history__node-toggle" data-hist-toggle aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                                    <span class="site-history__node-meta">
                                        <span class="site-history__chip"><?= $e($kindLabel($kind)) ?></span>
                                        <time class="site-history__when"><?= $e($formatDateLabel(isset($item['occurred_at']) ? (string) $item['occurred_at'] : null, $precision)) ?></time>
                                    </span>
                                    <span class="site-history__node-title"><?= $e($item['title'] ?? '') ?></span>
                                    <?php if (!empty($item['value_label'])): ?>
                                        <span class="site-history__node-value"><?= $e($item['value_label']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($item['narrative'])): ?>
                                        <span class="site-history__node-excerpt"><?= $e($item['narrative']) ?></span>
                                    <?php endif; ?>
                                </button>
                                <div class="site-history__node-panel"<?= $index === 0 ? '' : ' hidden' ?>>
                                    <p class="site-history__narrative"><?= $e($item['narrative'] ?? '') ?></p>
                                    <?php if ($stats !== null): ?>
                                        <dl class="site-history__statgrid">
                                            <div>
                                                <dt><?= $e(__('site.hist_stats_operators')) ?></dt>
                                                <dd><?= $e($fmtNum((int) ($stats['operators_total'] ?? 0))) ?></dd>
                                            </div>
                                            <div>
                                                <dt><?= $e(__('site.hist_stats_orgs')) ?></dt>
                                                <dd><?= $e($fmtNum((int) ($stats['organizations_total'] ?? 0))) ?></dd>
                                            </div>
                                            <div>
                                                <dt><?= $e(__('site.hist_stats_sessions')) ?></dt>
                                                <dd><?= $e($fmtNum((int) ($stats['sessions_total'] ?? 0))) ?></dd>
                                            </div>
                                            <div>
                                                <dt><?= $e(__('site.hist_stats_distance')) ?></dt>
                                                <dd><?= $e($fmtNum((float) ($stats['distance_km'] ?? 0), 0)) ?> km</dd>
                                            </div>
                                            <div>
                                                <dt><?= $e(__('site.hist_stats_playtime')) ?></dt>
                                                <dd><?= $e($fmtNum((float) ($stats['playtime_hours'] ?? 0), 0)) ?> h</dd>
                                            </div>
                                            <?php if (isset($stats['version_adoption_pct']) && $stats['version_adoption_pct'] !== null): ?>
                                                <div>
                                                    <dt><?= $e(__('site.hist_stats_adoption')) ?></dt>
                                                    <dd><?= $e($fmtNum((float) $stats['version_adoption_pct'], 1)) ?> %</dd>
                                                </div>
                                            <?php endif; ?>
                                        </dl>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>

        <?php if ($versions !== []): ?>
        <section class="site-sse-block site-history-rise" aria-labelledby="hist-versions-title">
            <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_kind_version')) ?></p>
            <h2 id="hist-versions-title" class="site-sse-block__title"><?= $e(__('site.hist_kind_version')) ?> x.0.0</h2>
            <div class="site-page__grid site-page__grid--2 site-history__versions">
                <?php foreach ($versions as $ver): ?>
                    <?php if (!is_array($ver)) {
                        continue;
                    }
                    $vStats = is_array($ver['stats'] ?? null) ? $ver['stats'] : [];
                    ?>
                    <article class="site-prose site-history__version" id="ver-<?= $e(str_replace('.', '-', (string) ($ver['version'] ?? ''))) ?>">
                        <p class="site-history__version-tag"><?= $e($ver['version'] ?? '') ?></p>
                        <h2><?= $e($ver['title'] ?? '') ?></h2>
                        <p><?= $e($ver['summary'] ?? '') ?></p>
                        <p class="site-history__when"><?= $e($formatDateLabel(
                            !empty($ver['released_at']) ? ((string) $ver['released_at']) . ' 00:00:00' : null,
                            (string) ($ver['date_precision'] ?? 'unknown')
                        )) ?></p>
                        <dl class="site-history__statgrid site-history__statgrid--compact">
                            <div>
                                <dt><?= $e(__('site.hist_stats_operators')) ?></dt>
                                <dd><?= $e($fmtNum((int) ($vStats['operators_total'] ?? 0))) ?></dd>
                            </div>
                            <div>
                                <dt><?= $e(__('site.hist_stats_orgs')) ?></dt>
                                <dd><?= $e($fmtNum((int) ($vStats['organizations_total'] ?? 0))) ?></dd>
                            </div>
                            <div>
                                <dt><?= $e(__('site.hist_stats_sessions')) ?></dt>
                                <dd><?= $e($fmtNum((int) ($vStats['sessions_total'] ?? 0))) ?></dd>
                            </div>
                            <div>
                                <dt><?= $e(__('site.hist_stats_distance')) ?></dt>
                                <dd><?= $e($fmtNum((float) ($vStats['distance_km'] ?? 0), 0)) ?> km</dd>
                            </div>
                        </dl>
                        <?php if (!empty($ver['features']) && is_array($ver['features'])): ?>
                            <ul>
                                <?php foreach ($ver['features'] as $feat): ?>
                                    <?php
                                    // ChangelogCatalog : ['kind'=>…,'text'=>…] — pas une string.
                                    $featText = is_array($feat)
                                        ? (string) ($feat['text'] ?? $feat['label'] ?? '')
                                        : (string) $feat;
                                    if ($featText === '') {
                                        continue;
                                    }
                                    ?>
                                    <li><?= $e($featText) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($nextGoal !== null): ?>
        <section class="site-sse-block site-history__next site-history-rise" aria-labelledby="hist-next-title">
            <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_next_kicker')) ?></p>
            <h2 id="hist-next-title" class="site-sse-block__title"><?= $e(__('site.hist_next_title')) ?></h2>
            <p class="site-history__next-label"><?= $e($nextGoal['label'] ?? '') ?></p>
            <div class="site-history__progress" role="progressbar"
                 aria-valuemin="0" aria-valuemax="100"
                 aria-valuenow="<?= $e((string) (int) round((float) ($nextGoal['progress_pct'] ?? 0))) ?>"
                 aria-label="<?= $e($nextGoal['label'] ?? '') ?>">
                <span class="site-history__progress-bar" style="width: <?= $e((string) min(100, max(0, (float) ($nextGoal['progress_pct'] ?? 0)))) ?>%"></span>
            </div>
            <p class="site-history__next-ratio">
                <?= $e(__('site.hist_next_of', [
                    'current' => $fmtNum((float) ($nextGoal['current'] ?? 0), 0),
                    'target' => $fmtNum((float) ($nextGoal['target'] ?? 0), 0),
                ])) ?>
            </p>
        </section>
        <?php endif; ?>

        <p class="site-history__honesty"><?= $e($honesty) ?></p>

        <div class="site-page__cta">
            <a href="<?= $e($createHref) ?>" class="hi-cta hi-cta-solid"><?= $e(__('site.hist_cta_create')) ?></a>
            <a href="<?= $e(url('nouveautes')) ?>" class="hi-cta hi-cta-ghost"><?= $e(__('site.hist_cta_changelog')) ?></a>
            <a href="<?= $e(url('a-propos')) ?>" class="hi-cta hi-cta-ghost"><?= $e(__('site.hist_cta_about')) ?></a>
        </div>
    </div>
</article>
