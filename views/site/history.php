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
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$fmtNum = static function (int|float|null $n, int $decimals = 0): string {
    if ($n === null) {
        return '—';
    }
    $locale = function_exists('locale') && locale() === 'en' ? 'en_GB' : 'fr_FR';
    if (class_exists(NumberFormatter::class)) {
        $fmt = new NumberFormatter($locale, NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);

        return (string) $fmt->format($n);
    }

    return number_format((float) $n, $decimals, ',', ' ');
};

$formatDateLabel = static function (?string $occurredAt, string $precision) use ($e): string {
    if ($occurredAt === null || $occurredAt === '') {
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
    if ($precision === 'unknown') {
        return (string) __('site.hist_date_unknown');
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
    <header class="site-history__hero">
        <p class="hi-kicker text-emerald-400/90"><?= $e(__('site.hist_kicker')) ?></p>
        <h1 class="site-history__brand">ATHENA</h1>
        <p class="site-history__title"><?= $e(__('site.hist_title')) ?></p>
        <p class="hi-body mt-5 max-w-2xl text-white/65"><?= $e(__('site.hist_lead')) ?></p>
        <dl class="site-history__live">
            <div>
                <dt><?= $e(__('site.hist_stats_operators')) ?></dt>
                <dd><?= $e($fmtNum((int) ($live['operators_total'] ?? 0))) ?></dd>
            </div>
            <div>
                <dt><?= $e(__('site.hist_stats_orgs')) ?></dt>
                <dd><?= $e($fmtNum((int) ($live['organizations_total'] ?? 0))) ?></dd>
            </div>
            <div>
                <dt><?= $e(__('site.hist_stats_sessions')) ?></dt>
                <dd><?= $e($fmtNum((int) ($live['sessions_total'] ?? 0))) ?></dd>
            </div>
            <div>
                <dt><?= $e(__('site.hist_stats_distance')) ?></dt>
                <dd><?= $e($fmtNum((float) ($live['distance_km'] ?? 0), 0)) ?> km</dd>
            </div>
        </dl>
    </header>

    <section class="site-history__section" aria-labelledby="hist-chart-title">
        <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_chart_kicker')) ?></p>
        <h2 id="hist-chart-title" class="site-history__h2"><?= $e(__('site.hist_chart_title')) ?></h2>
        <p class="site-history__lead"><?= $e(__('site.hist_chart_lead')) ?></p>
        <div class="site-history__period" role="group" aria-label="<?= $e(__('site.hist_chart_period')) ?>">
            <?php
            $periods = [
                'all' => __('site.hist_chart_period_all'),
                '365' => __('site.hist_chart_period_365'),
                '90' => __('site.hist_chart_period_90'),
            ];
            foreach ($periods as $key => $label):
                $href = url('notre-histoire') . ($key === 'all' ? '' : ('?periode=' . rawurlencode($key)));
                $active = $period === $key;
            ?>
                <a href="<?= $e($href) ?>" class="site-history__period-btn<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="site-history__chart-wrap">
            <canvas id="hist-chart" width="960" height="360" role="img" aria-label="<?= $e(__('site.hist_chart_title')) ?>"></canvas>
            <div class="site-history__chart-legend" aria-hidden="true">
                <span class="site-history__legend site-history__legend--ops"><?= $e(__('site.hist_chart_operators')) ?></span>
                <span class="site-history__legend site-history__legend--org"><?= $e(__('site.hist_chart_orgs')) ?></span>
            </div>
        </div>
        <script type="application/json" id="hist-chart-data"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    </section>

    <section class="site-history__section" aria-labelledby="hist-timeline-title">
        <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_timeline_kicker')) ?></p>
        <h2 id="hist-timeline-title" class="site-history__h2"><?= $e(__('site.hist_timeline_title')) ?></h2>
        <p class="site-history__lead"><?= $e(__('site.hist_timeline_lead')) ?></p>

        <?php if ($timeline === []): ?>
            <p class="site-history__empty"><?= $e(__('site.hist_empty_timeline')) ?></p>
        <?php else: ?>
            <ol class="site-history__timeline">
                <?php foreach ($timeline as $item): ?>
                    <?php
                    if (!is_array($item)) {
                        continue;
                    }
                    $kind = (string) ($item['kind'] ?? 'custom');
                    $precision = (string) ($item['date_precision'] ?? 'unknown');
                    $stats = is_array($item['stats'] ?? null) ? $item['stats'] : null;
                    $key = (string) ($item['key'] ?? '');
                    ?>
                    <li class="site-history__node site-history__node--<?= $e(preg_replace('/[^a-z0-9\-]/i', '', $kind) ?? 'custom') ?>" id="<?= $e($key) ?>">
                        <button type="button" class="site-history__node-toggle" data-hist-toggle aria-expanded="false">
                            <span class="site-history__node-meta">
                                <span class="site-history__chip"><?= $e($kindLabel($kind)) ?></span>
                                <time class="site-history__when"><?= $e($formatDateLabel(isset($item['occurred_at']) ? (string) $item['occurred_at'] : null, $precision)) ?></time>
                            </span>
                            <span class="site-history__node-title"><?= $e($item['title'] ?? '') ?></span>
                            <?php if (!empty($item['value_label'])): ?>
                                <span class="site-history__node-value"><?= $e($item['value_label']) ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="site-history__node-panel" hidden>
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
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <?php if ($versions !== []): ?>
    <section class="site-history__section" aria-labelledby="hist-versions-title">
        <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_kind_version')) ?></p>
        <h2 id="hist-versions-title" class="site-history__h2"><?= $e(__('site.hist_kind_version')) ?> x.0.0</h2>
        <div class="site-history__versions">
            <?php foreach ($versions as $ver): ?>
                <?php if (!is_array($ver)) {
                    continue;
                } ?>
                <article class="site-history__version" id="ver-<?= $e(str_replace('.', '-', (string) ($ver['version'] ?? ''))) ?>">
                    <p class="site-history__version-tag"><?= $e($ver['version'] ?? '') ?></p>
                    <h3><?= $e($ver['title'] ?? '') ?></h3>
                    <p><?= $e($ver['summary'] ?? '') ?></p>
                    <p class="site-history__when"><?= $e($formatDateLabel(
                        !empty($ver['released_at']) ? ((string) $ver['released_at']) . ' 00:00:00' : null,
                        (string) ($ver['date_precision'] ?? 'unknown')
                    )) ?></p>
                    <?php
                    $vStats = is_array($ver['stats'] ?? null) ? $ver['stats'] : [];
                    ?>
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
                        <ul class="site-history__features">
                            <?php foreach ($ver['features'] as $feat): ?>
                                <li><?= $e($feat) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($nextGoal !== null): ?>
    <section class="site-history__section site-history__next" aria-labelledby="hist-next-title">
        <p class="hi-kicker text-emerald-400/80"><?= $e(__('site.hist_next_kicker')) ?></p>
        <h2 id="hist-next-title" class="site-history__h2"><?= $e(__('site.hist_next_title')) ?></h2>
        <p class="site-history__next-label"><?= $e($nextGoal['label'] ?? '') ?></p>
        <div class="site-history__progress" role="progressbar"
             aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="<?= $e((string) (int) round((float) ($nextGoal['progress_pct'] ?? 0))) ?>"
             aria-label="<?= $e($nextGoal['label'] ?? '') ?>">
            <span class="site-history__progress-bar" style="width: <?= $e((string) min(100, max(0, (float) ($nextGoal['progress_pct'] ?? 0)))) ?>%"></span>
        </div>
        <p class="site-history__next-ratio">
            <?= $e(__('site.hist_next_of', [
                'current' => $fmtNum((float) ($nextGoal['current'] ?? 0), ($nextGoal['metric'] ?? '') === 'operators' ? 0 : 0),
                'target' => $fmtNum((float) ($nextGoal['target'] ?? 0), 0),
            ])) ?>
        </p>
    </section>
    <?php endif; ?>

    <p class="site-history__honesty"><?= $e($honesty) ?></p>
</article>
