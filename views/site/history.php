<?php
declare(strict_types=1);

/**
 * Page publique « Notre histoire » : kilomètres et chiffres réels, frise annuelle, historique des versions.
 * Données : PublicHistoryStatsService (agrégats), PublicReleaseHistoryService (versions), jalons du patrimoine.
 */

$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isEn = function_exists('locale') && locale() === 'en';
$loggedIn = (bool) \App\Core\Session::get('user_id');
$createHref = $loggedIn ? url('communities/create') : url('register');
$base = rtrim(url(''), '/');

$emptyStats = [
    'scope' => 'platform', 'community_name' => null, 'founded_at' => null, 'members' => 0, 'operations' => 0,
    'flight_hours' => 0, 'academy_validated' => 0, 'orbat_units' => 0, 'communities' => 0, 'distance_km' => 0,
    'distance_modes' => [], 'distance_sessions' => 0, 'play_hours' => 0, 'years' => [], 'highlights' => [],
];
$platformStats = is_array($platformStats ?? null) ? $platformStats + $emptyStats : $emptyStats;
$communityStats = is_array($communityStats ?? null) ? $communityStats + $emptyStats : null;
$releaseHistory = is_array($releaseHistory ?? null) ? $releaseHistory : [];
$releases = array_values(array_filter(is_array($releaseHistory['releases'] ?? null) ? $releaseHistory['releases'] : [], 'is_array'));
$currentVersions = array_values(array_filter(is_array($releaseHistory['current'] ?? null) ? $releaseHistory['current'] : [], 'is_array'));
$releaseCounts = is_array($releaseHistory['counts'] ?? null) ? $releaseHistory['counts'] : [];
$bulletins = (int) ($releaseHistory['bulletins'] ?? 0);
$firstRelease = is_string($releaseHistory['first_date'] ?? null) ? $releaseHistory['first_date'] : null;
$milestones = array_values(array_filter(is_array($milestones ?? null) ? $milestones : [], 'is_array'));

$fmt = static function (int|float $n) use ($isEn): string {
    return $isEn ? number_format((float) $n, 0, '.', ',') : number_format((float) $n, 0, ',', "\u{202F}");
};
$dateLabel = static function (?string $day, string $pattern = 'long') use ($isEn): string {
    if ($day === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($day);
    } catch (Throwable) {
        return $day;
    }
    if (class_exists(IntlDateFormatter::class)) {
        $f = new IntlDateFormatter($isEn ? 'en_GB' : 'fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, match ($pattern) {
            'month' => 'LLLL y',
            'short' => 'd MMM',
            default => 'd MMMM y',
        });
        $out = (string) $f->format($dt);
        if ($out !== '') {
            return $pattern === 'month' ? mb_strtoupper(mb_substr($out, 0, 1)) . mb_substr($out, 1) : $out;
        }
    }

    return match ($pattern) {
        'month' => $dt->format('m/Y'),
        'short' => $dt->format('d/m'),
        default => $dt->format('d/m/Y'),
    };
};

$products = [
    'atak' => 'ATAK natif',
    'link' => 'COMSPEC Link',
    'overwatch' => 'Overwatch',
    'sse' => 'SSE',
    'portal' => (string) __('site.hist_product_portal'),
];

// Répartition des kilomètres par mode de déplacement (modes non nuls seulement).
$modesOf = static function (array $stats): array {
    $modes = is_array($stats['distance_modes'] ?? null) ? $stats['distance_modes'] : [];
    $out = [];
    foreach (['foot', 'land', 'air', 'sea', 'halo'] as $k) {
        $v = max(0, (int) ($modes[$k] ?? 0));
        if ($v > 0) {
            $out[$k] = $v;
        }
    }

    return $out;
};
$platformKm = max(0, (int) $platformStats['distance_km']);

$facts = [
    ['n' => (int) $platformStats['communities'], 'k' => 'hist_fact_communities'],
    ['n' => (int) $platformStats['members'], 'k' => 'hist_fact_members'],
    ['n' => (int) $platformStats['operations'], 'k' => 'hist_fact_operations'],
    ['n' => (int) $platformStats['flight_hours'], 'k' => 'hist_fact_flight'],
    ['n' => (int) $platformStats['play_hours'], 'k' => 'hist_fact_play'],
];

$renderDistance = static function (array $stats) use ($e, $fmt, $modesOf): void {
    $modes = $modesOf($stats);
    $total = array_sum($modes);
    if ($total <= 0) {
        return;
    }
    echo '<div class="nh-distance">';
    echo '<h3 class="nh-h3">' . $e(__('site.hist_distance_title')) . '</h3>';
    echo '<div class="nh-distance__bar" aria-hidden="true">';
    foreach ($modes as $k => $v) {
        $grow = max(1.5, round($v / $total * 100, 2));
        echo '<span class="nh-mode--' . $e($k) . '" style="flex-grow:' . $e((string) $grow) . '"></span>';
    }
    echo '</div><ul class="nh-distance__legend" role="list">';
    foreach ($modes as $k => $v) {
        echo '<li><span class="nh-swatch nh-mode--' . $e($k) . '" aria-hidden="true"></span><span class="nh-distance__k">'
            . $e(__('site.hist_mode_' . $k)) . '</span><span class="nh-distance__v">' . $e($fmt($v)) . ' km</span></li>';
    }
    echo '</ul></div>';
};

$renderFigures = static function (array $stats, bool $community) use ($e, $fmt): void {
    $cards = [
        ['n' => (int) $stats['members'], 'k' => 'members'],
        ['n' => (int) $stats['operations'], 'k' => 'operations'],
        ['n' => (int) $stats['flight_hours'], 'k' => 'flight', 'u' => ' h'],
        ['n' => (int) $stats['play_hours'], 'k' => 'play', 'u' => ' h'],
        ['n' => (int) $stats['distance_sessions'], 'k' => 'sessions'],
        ['n' => (int) $stats['academy_validated'], 'k' => 'academy'],
        ['n' => (int) $stats['orbat_units'], 'k' => 'orbat'],
    ];
    if (!$community) {
        $cards[] = ['n' => (int) $stats['communities'], 'k' => 'communities'];
    }
    echo '<dl class="nh-figures">';
    foreach ($cards as $c) {
        $pending = $c['n'] <= 0;
        echo '<div class="nh-figure' . ($pending ? ' is-pending' : '') . '">';
        echo '<dt>' . $e(__('site.hist_fig_' . $c['k'])) . '</dt>';
        if ($pending) {
            echo '<dd class="nh-figure__v">' . $e(__('site.hist_pending')) . '</dd>';
            echo '<dd class="nh-figure__h">' . $e(__('site.hist_pending_h')) . '</dd>';
        } else {
            echo '<dd class="nh-figure__v"><span data-nh-count="' . $c['n'] . '">' . $e($fmt($c['n'])) . '</span>' . $e($c['u'] ?? '') . '</dd>';
            echo '<dd class="nh-figure__h">' . $e(__('site.hist_fig_' . $c['k'] . '_h')) . '</dd>';
        }
        echo '</div>';
    }
    echo '</dl>';
};

// Frise annuelle : chiffres de l'année + premières fois datées de cette année.
$renderYears = static function (array $years, array $events) use ($e, $fmt, $dateLabel): void {
    $byYear = [];
    foreach ($years as $y) {
        if (is_array($y) && preg_match('/^\d{4}$/', (string) ($y['year'] ?? '')) === 1) {
            $byYear[(string) $y['year']] = $y + ['events' => []];
        }
    }
    foreach ($events as $ev) {
        $year = substr((string) ($ev['date'] ?? ''), 0, 4);
        if (preg_match('/^\d{4}$/', $year) !== 1) {
            continue;
        }
        $byYear[$year] ??= ['year' => $year, 'operations' => 0, 'members' => 0, 'flight_hours' => 0, 'distance_km' => 0, 'events' => []];
        $byYear[$year]['events'][] = $ev;
    }
    if ($byYear === []) {
        echo '<p class="nh-empty">' . $e(__('site.hist_years_empty')) . '</p>';

        return;
    }
    ksort($byYear);
    $metrics = ['operations' => 'hist_years_ops', 'members' => 'hist_years_members', 'flight_hours' => 'hist_years_flight', 'distance_km' => 'hist_years_km'];
    $max = [];
    foreach (array_keys($metrics) as $m) {
        $max[$m] = max(1, ...array_map(static fn (array $y): int => (int) ($y[$m] ?? 0), array_values($byYear)));
    }
    echo '<ol class="nh-years">';
    foreach ($byYear as $year => $y) {
        echo '<li class="nh-year"><h3 class="nh-year__y">' . $e($year) . '</h3><div class="nh-year__body"><dl class="nh-year__metrics">';
        foreach ($metrics as $m => $label) {
            $v = (int) ($y[$m] ?? 0);
            $pct = $v > 0 ? max(3, (int) round($v / $max[$m] * 100)) : 0;
            echo '<div class="nh-metric nh-metric--' . $e($m) . '"><dt>' . $e(__('site.' . $label)) . '</dt><dd><strong>' . $e($fmt($v)) . '</strong>'
                . '<span class="nh-metric__bar" aria-hidden="true"><span style="width:' . $pct . '%"></span></span></dd></div>';
        }
        echo '</dl>';
        $evs = $y['events'];
        usort($evs, static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));
        if ($evs !== []) {
            echo '<ul class="nh-year__events" role="list">';
            foreach ($evs as $ev) {
                echo '<li><time datetime="' . $e($ev['date']) . '">' . $e($dateLabel((string) $ev['date'], 'short')) . '</time><span>' . $e($ev['title']) . '</span></li>';
            }
            echo '</ul>';
        }
        echo '</div></li>';
    }
    echo '</ol>';
};

// Premières fois : jalons du patrimoine + faits tirés des données (première communauté, première version).
$firsts = [];
if (is_string($platformStats['founded_at'] ?? null)) {
    $firsts[] = ['date' => $platformStats['founded_at'], 'title' => (string) __('site.hist_milestone_founded'), 'text' => ''];
}
if ($firstRelease !== null) {
    $firsts[] = ['date' => $firstRelease, 'title' => (string) __('site.hist_milestone_first_release'), 'text' => ''];
}
foreach ($milestones as $m) {
    $firsts[] = ['date' => (string) ($m['date'] ?? ''), 'title' => (string) ($m['title'] ?? ''), 'text' => (string) ($m['text'] ?? '')];
}
usort($firsts, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

// Versions : au plus 30 par produit (les plus récentes) pour garder une page légère ; le reste est au journal.
$perFilterCap = 30;
$taken = array_fill_keys(array_keys($products), 0);
$shown = [];
foreach ($releases as $rel) {
    $filters = array_values(array_filter(is_array($rel['filters'] ?? null) ? $rel['filters'] : [], static fn ($f): bool => isset($products[$f])));
    $keep = false;
    foreach ($filters as $f) {
        if ($taken[$f] < $perFilterCap) {
            $keep = true;
        }
    }
    if (!$keep) {
        continue;
    }
    foreach ($filters as $f) {
        $taken[$f]++;
    }
    $rel['filters'] = $filters;
    $shown[] = $rel;
}
$months = [];
foreach ($shown as $rel) {
    $months[substr((string) $rel['date'], 0, 7)][] = $rel;
}
$typeLabels = [
    'new' => __('site.hist_type_new'),
    'improvement' => __('site.hist_type_improvement'),
    'fix' => __('site.hist_type_fix'),
    'performance' => __('site.hist_type_performance'),
];
$tagLabel = static function (string $product) use ($products): string {
    return $product === 'athena' ? (string) __('site.hist_product_athena_game') : ($products[$product] ?? $product);
};
?>
<article class="nh" data-nh>
    <script>try{var t=localStorage.getItem('athena.history.theme');if(t==='light'||t==='dark'){document.currentScript.parentNode.setAttribute('data-theme',t);}}catch(e){}</script>

    <section class="nh-hero" aria-labelledby="nh-title">
        <div class="nh-hero__map" aria-hidden="true" style="--nh-topo:url('<?= $e($base) ?>/assets/images/history-topo.svg')"></div>
        <svg class="nh-hero__route" viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
            <path class="nh-route" pathLength="1" d="M-20 655 C 150 640 260 610 380 640 S 600 690 700 600 S 800 430 877 378"/>
            <circle class="nh-wp" cx="380" cy="640" r="7"/>
            <circle class="nh-wp" cx="700" cy="600" r="7"/>
        </svg>
        <div class="nh-wrap nh-hero__inner">
            <button type="button" class="nh-theme" data-nh-theme-toggle hidden
                    data-label-dark="<?= $e(__('site.hist_theme_to_dark')) ?>"
                    data-label-light="<?= $e(__('site.hist_theme_to_light')) ?>"
                    aria-label="<?= $e(__('site.hist_theme_to_dark')) ?>" title="<?= $e(__('site.hist_theme_to_dark')) ?>">
                <svg class="nh-theme__moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
                <svg class="nh-theme__sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>
            </button>
            <div class="nh-hero__copy">
                <h1 id="nh-title" class="nh-hero__title"><?= $e(__('site.hist_title')) ?></h1>
                <p class="nh-hero__lead"><?= $e(__('site.hist_lead')) ?></p>
                <div class="nh-actions">
                    <a href="#versions" class="nh-btn nh-btn--solid"><?= $e(__('site.hist_cta_versions')) ?></a>
                    <a href="<?= $e($createHref) ?>" class="nh-btn nh-btn--ghost"><?= $e(__('site.hist_cta_create')) ?></a>
                </div>
            </div>
            <div class="nh-km">
                <?php if ($platformKm > 0): ?>
                    <p class="nh-km__value"><span data-nh-count="<?= $platformKm ?>"><?= $e($fmt($platformKm)) ?></span><span class="nh-km__unit">km</span></p>
                    <p class="nh-km__label"><?= $e(__('site.hist_km_label')) ?></p>
                <?php else: ?>
                    <p class="nh-km__value is-pending">0<span class="nh-km__unit">km</span></p>
                    <p class="nh-km__label"><?= $e(__('site.hist_km_pending')) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="nh-wrap">
            <dl class="nh-facts">
                <?php foreach ($facts as $f): ?>
                    <div class="nh-fact">
                        <dt><?= $e(__('site.' . $f['k'])) ?></dt>
                        <dd><?php if ($f['n'] > 0): ?><span data-nh-count="<?= $f['n'] ?>"><?= $e($fmt($f['n'])) ?></span><?php else: ?><span class="nh-fact__pending"><?= $e(__('site.hist_pending')) ?></span><?php endif; ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    </section>

    <nav class="nh-nav" aria-label="<?= $e(__('site.hist_nav_label')) ?>">
        <div class="nh-wrap nh-nav__inner">
            <a href="#origine"><?= $e(__('site.hist_nav_origin')) ?></a>
            <a href="#chiffres"><?= $e(__('site.hist_nav_figures')) ?></a>
            <a href="#annees"><?= $e(__('site.hist_nav_years')) ?></a>
            <a href="#versions"><?= $e(__('site.hist_nav_versions')) ?></a>
            <a href="#communautes"><?= $e(__('site.hist_nav_communities')) ?></a>
        </div>
    </nav>

    <section id="origine" class="nh-section" aria-labelledby="nh-origin-title">
        <div class="nh-wrap nh-origin">
            <div class="nh-origin__story">
                <h2 id="nh-origin-title" class="nh-h2"><?= $e(__('site.hist_origin_title')) ?></h2>
                <p><?= $e(__('site.hist_origin_p1')) ?></p>
                <p><?= $e(__('site.hist_origin_p2')) ?></p>
                <?php if (is_string($platformStats['founded_at'])): ?>
                    <p><?= $e(__('site.hist_origin_founded', ['date' => $dateLabel($platformStats['founded_at'])])) ?></p>
                <?php endif; ?>
                <?php if ($bulletins > 0 && $releases !== []): ?>
                    <p><?= $e(__('site.hist_origin_bulletins', ['count' => $fmt($bulletins), 'versions' => $fmt(count($releases))])) ?></p>
                <?php endif; ?>
            </div>
            <div class="nh-origin__firsts">
                <h3 class="nh-h3"><?= $e(__('site.hist_milestones_title')) ?></h3>
                <?php if ($firsts === []): ?>
                    <p class="nh-empty"><?= $e(__('site.hist_milestones_empty')) ?></p>
                <?php else: ?>
                    <ol class="nh-firsts">
                        <?php foreach ($firsts as $f): ?>
                            <li>
                                <time datetime="<?= $e($f['date']) ?>"><?= $e($dateLabel($f['date'])) ?></time>
                                <strong><?= $e($f['title']) ?></strong>
                                <?php if ($f['text'] !== '' && $f['text'] !== $f['title']): ?><span><?= $e($f['text']) ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="chiffres" class="nh-section nh-section--band" aria-labelledby="nh-figures-title">
        <div class="nh-wrap">
            <h2 id="nh-figures-title" class="nh-h2"><?= $e(__('site.hist_figures_title')) ?></h2>
            <p class="nh-lead"><?= $e(__('site.hist_figures_lead')) ?></p>
            <?php $renderDistance($platformStats); ?>
            <?php $renderFigures($platformStats, false); ?>
        </div>
    </section>

    <section id="annees" class="nh-section" aria-labelledby="nh-years-title">
        <div class="nh-wrap">
            <h2 id="nh-years-title" class="nh-h2"><?= $e(__('site.hist_years_title')) ?></h2>
            <p class="nh-lead"><?= $e(__('site.hist_years_lead')) ?></p>
            <?php $renderYears(is_array($platformStats['years']) ? $platformStats['years'] : [], $milestones); ?>
        </div>
    </section>

    <section id="versions" class="nh-section nh-section--band" aria-labelledby="nh-versions-title" data-nh-versions data-status-label="<?= $e(__('site.hist_versions_status')) ?>">
        <div class="nh-wrap">
            <h2 id="nh-versions-title" class="nh-h2"><?= $e(__('site.hist_versions_title')) ?></h2>
            <p class="nh-lead"><?= $e(__('site.hist_versions_lead')) ?></p>

            <?php if ($shown === []): ?>
                <p class="nh-empty"><?= $e(__('site.hist_versions_none')) ?></p>
            <?php else: ?>
                <?php if ($currentVersions !== []): ?>
                    <div class="nh-current">
                        <h3 class="nh-h3"><?= $e(__('site.hist_versions_current')) ?></h3>
                        <ul class="nh-current__list" role="list">
                            <?php foreach ($currentVersions as $cv): ?>
                                <?php $p = (string) ($cv['product'] ?? '');
                                if (!isset($products[$p])) {
                                    continue;
                                } ?>
                                <li class="nh-p--<?= $e($p) ?>">
                                    <span class="nh-current__name"><?= $e($products[$p]) ?></span>
                                    <span class="nh-current__v"><?= $e($cv['version'] ?? '') ?></span>
                                    <time datetime="<?= $e($cv['date'] ?? '') ?>"><?= $e($dateLabel(is_string($cv['date'] ?? null) ? $cv['date'] : null)) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="nh-filters" role="group" aria-label="<?= $e(__('site.hist_versions_filter')) ?>">
                    <button type="button" class="nh-chip" data-nh-filter="all" aria-pressed="true"><?= $e(__('site.hist_versions_all')) ?> <span><?= $e($fmt(count($releases))) ?></span></button>
                    <?php foreach ($products as $p => $label): ?>
                        <?php if ((int) ($releaseCounts[$p] ?? 0) <= 0) {
                            continue;
                        } ?>
                        <button type="button" class="nh-chip nh-p--<?= $e($p) ?>" data-nh-filter="<?= $e($p) ?>" aria-pressed="false"><?= $e($label) ?> <span><?= $e($fmt((int) $releaseCounts[$p])) ?></span></button>
                    <?php endforeach; ?>
                </div>
                <p class="nh-filters__status" data-nh-status aria-live="polite"></p>

                <ol class="nh-months">
                    <?php foreach ($months as $ym => $items): ?>
                        <li class="nh-month" data-nh-month>
                            <h3 class="nh-month__title"><?= $e($dateLabel($ym . '-01', 'month')) ?></h3>
                            <ol class="nh-releases">
                                <?php foreach ($items as $rel): ?>
                                    <?php
                                    $tags = array_values(array_filter(is_array($rel['tags'] ?? null) ? $rel['tags'] : [], 'is_array'));
                                    $primary = (string) ($rel['product'] ?? '');
                                    $primaryFilter = $primary === 'athena' ? 'overwatch' : $primary;
                                    $hasEn = $isEn && !empty($rel['title_en']);
                                    $title = $hasEn ? (string) $rel['title_en'] : (string) ($rel['title'] ?? '');
                                    $points = $hasEn && !empty($rel['points_en']) && is_array($rel['points_en'])
                                        ? $rel['points_en']
                                        : (is_array($rel['points'] ?? null) ? $rel['points'] : []);
                                    $summary = $hasEn ? '' : (string) ($rel['summary'] ?? '');
                                    $approx = ($rel['date_precision'] ?? 'day') === 'month';
                                    $type = (string) ($rel['type'] ?? '');
                                    $bulletin = (int) ($rel['bulletin'] ?? 0);
                                    ?>
                                    <li class="nh-release nh-p--<?= $e($primaryFilter) ?>" data-nh-release data-products="<?= $e(implode(' ', $rel['filters'])) ?>">
                                        <time class="nh-release__date" datetime="<?= $e($rel['date'] ?? '') ?>"<?= $approx ? ' title="' . $e(__('site.hist_versions_month_approx')) . '"' : '' ?>><?= $approx ? $e(__('site.hist_versions_month_short')) : $e($dateLabel((string) ($rel['date'] ?? ''), 'short')) ?></time>
                                        <div class="nh-release__card">
                                            <p class="nh-release__tags">
                                                <?php foreach ($tags as $i => $tag): ?>
                                                    <?php $tp = (string) ($tag['product'] ?? ''); ?>
                                                    <span class="nh-tag nh-p--<?= $e($tp === 'athena' ? 'overwatch' : $tp) ?><?= $i === 0 ? ' nh-tag--main' : '' ?>"><?= $e($tagLabel($tp) . ' ' . ($tag['version'] ?? '')) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (!empty($rel['codename'])): ?><span class="nh-tag nh-tag--code"><?= $e($rel['codename']) ?></span><?php endif; ?>
                                                <?php if (isset($typeLabels[$type])): ?><span class="nh-type nh-type--<?= $e($type) ?>"><?= $e($typeLabels[$type]) ?></span><?php endif; ?>
                                            </p>
                                            <h4 class="nh-release__title"><?= $e($title) ?></h4>
                                            <?php if ($summary !== ''): ?><p class="nh-release__summary"><?= $e($summary) ?></p><?php endif; ?>
                                            <?php if ($points !== []): ?>
                                                <ul class="nh-release__points">
                                                    <?php foreach (array_slice($points, 0, 4) as $pt): ?><li><?= $e($pt) ?></li><?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                            <?php if ($bulletin > 0): ?>
                                                <a class="nh-release__link" href="<?= $e(url('nouveautes/update/' . str_pad((string) $bulletin, 5, '0', STR_PAD_LEFT))) ?>"><?= $e(__('site.hist_versions_bulletin')) ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <div class="nh-more">
                    <button type="button" class="nh-btn nh-btn--ghost" data-nh-more hidden><?= $e(__('site.hist_versions_more')) ?></button>
                    <p class="nh-more__note"><?= $e(__('site.hist_versions_full')) ?> <a href="<?= $e(url('nouveautes')) ?>"><?= $e(__('site.hist_cta_changelog')) ?></a></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="communautes" class="nh-section" aria-labelledby="nh-comm-title">
        <div class="nh-wrap">
            <?php if ($communityStats !== null): ?>
                <h2 id="nh-comm-title" class="nh-h2"><?= $e(
                    !empty($communityStats['community_name'])
                        ? __('site.hist_comm_title', ['name' => (string) $communityStats['community_name']])
                        : __('site.hist_comm_title_generic')
                ) ?></h2>
                <p class="nh-lead"><?= $e(__('site.hist_comm_lead')) ?></p>
                <?php $commKm = max(0, (int) $communityStats['distance_km']); ?>
                <div class="nh-comm-km">
                    <?php if ($commKm > 0): ?>
                        <p class="nh-comm-km__v"><span data-nh-count="<?= $commKm ?>"><?= $e($fmt($commKm)) ?></span> km</p>
                        <p class="nh-comm-km__k"><?= $e(__('site.hist_km_comm_label')) ?></p>
                    <?php else: ?>
                        <p class="nh-comm-km__k"><?= $e(__('site.hist_km_comm_pending')) ?></p>
                    <?php endif; ?>
                </div>
                <?php $renderDistance($communityStats); ?>
                <?php $renderFigures($communityStats, true); ?>

                <h3 class="nh-h3"><?= $e(__('site.hist_comm_highlights')) ?></h3>
                <?php $highlights = array_values(array_filter(is_array($communityStats['highlights']) ? $communityStats['highlights'] : [], 'is_array')); ?>
                <?php if ($highlights === []): ?>
                    <p class="nh-empty"><?= $e(__('site.hist_comm_empty')) ?></p>
                <?php else: ?>
                    <ol class="nh-moments">
                        <?php foreach ($highlights as $h): ?>
                            <?php $hKind = ($h['kind'] ?? '') === 'article' ? 'article' : 'operation'; ?>
                            <li class="nh-moment">
                                <span class="nh-moment__meta"><span class="nh-type"><?= $e(__('site.hist_comm_kind_' . $hKind)) ?></span>
                                <time datetime="<?= $e($h['date'] ?? '') ?>"><?= $e($dateLabel((string) ($h['date'] ?? ''))) ?></time></span>
                                <strong><?= $e($h['title'] ?? '') ?></strong>
                                <?php if (isset($h['value']) && (int) $h['value'] > 0): ?>
                                    <span class="nh-moment__value"><?= $e(__('site.hist_comm_participants', ['value' => $fmt((int) $h['value'])])) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <h3 class="nh-h3"><?= $e(__('site.hist_comm_years')) ?></h3>
                <?php $renderYears(is_array($communityStats['years']) ? $communityStats['years'] : [], []); ?>
            <?php else: ?>
                <h2 id="nh-comm-title" class="nh-h2"><?= $e(__('site.hist_comm_public_title')) ?></h2>
                <div class="nh-comm-public">
                    <p class="nh-lead"><?= $e((int) $platformStats['communities'] > 0
                        ? __('site.hist_comm_public_lead', ['count' => $fmt((int) $platformStats['communities'])])
                        : __('site.hist_comm_public_lead_none')) ?></p>
                    <p><?= $e(__('site.hist_comm_signin')) ?></p>
                    <div class="nh-actions">
                        <?php if (!$loggedIn): ?>
                            <a href="<?= $e(url('login')) ?>" class="nh-btn nh-btn--solid"><?= $e(__('site.hist_comm_signin_cta')) ?></a>
                        <?php endif; ?>
                        <a href="<?= $e($createHref) ?>" class="nh-btn nh-btn--ghost"><?= $e(__('site.hist_cta_create')) ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="nh-section nh-end">
        <div class="nh-wrap">
            <p class="nh-note"><?= $e(__('site.hist_note')) ?></p>
            <div class="nh-actions">
                <a href="<?= $e(url('nouveautes')) ?>" class="nh-btn nh-btn--ghost"><?= $e(__('site.hist_cta_changelog')) ?></a>
                <a href="<?= $e(url('a-propos')) ?>" class="nh-btn nh-btn--ghost"><?= $e(__('site.hist_cta_about')) ?></a>
            </div>
        </div>
    </footer>
</article>
