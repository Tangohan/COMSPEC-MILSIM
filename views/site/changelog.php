<?php
declare(strict_types=1);
/** @var array<string, mixed> $catalog */
$catalog = is_array($catalog ?? null) ? $catalog : [];
$h = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$releases = is_array($catalog['releases'] ?? null) ? $catalog['releases'] : [];
$featured = is_array($catalog['featured'] ?? null) ? $catalog['featured'] : null;
$modules = is_array($catalog['modules'] ?? null) ? $catalog['modules'] : [];
$pipeline = is_array($catalog['pipeline'] ?? null) ? $catalog['pipeline'] : [];
$roadmap = is_array($catalog['roadmap'] ?? null) ? $catalog['roadmap'] : [];
$stats = is_array($catalog['stats'] ?? null) ? $catalog['stats'] : [];
$years = is_array($catalog['years'] ?? null) ? $catalog['years'] : [];
$filters = is_array($catalog['filters'] ?? null) ? $catalog['filters'] : [];
$kindLabels = is_array($catalog['kindLabels'] ?? null) ? $catalog['kindLabels'] : [];
$categoryLabels = is_array($catalog['categoryLabels'] ?? null) ? $catalog['categoryLabels'] : [];
$typeLabels = is_array($catalog['typeLabels'] ?? null) ? $catalog['typeLabels'] : [];
$statusLabels = is_array($catalog['statusLabels'] ?? null) ? $catalog['statusLabels'] : [];
$byYear = [];
foreach ($releases as $release) {
    if (!is_array($release) || !empty($release['featured'])) {
        continue;
    }
    $byYear[(int) ($release['year'] ?? 0)][] = $release;
}
$featuredGroups = $featured ? implode(' ', array_map('strval', $featured['filter_groups'] ?? [])) : '';
$featuredImg = ($featured && is_string($featured['image'] ?? null) && $featured['image'] !== '')
    ? asset_url((string) $featured['image'])
    : '';
$discoverHref = url('a-propos');
$dispatches = is_array($dispatches ?? null) ? $dispatches : [];
$featuredDispatch = is_array($featuredDispatch ?? null) ? $featuredDispatch : null;
?>
<div class="cl" data-cl-root>
    <header class="cl-hero">
        <div class="cl-hero__grid" aria-hidden="true"></div>
        <div class="cl-hero__halo" aria-hidden="true"></div>
        <div class="cl-wrap">
            <p class="cl-kicker"><?= $h(__('site.changelog_kicker')) ?></p>
            <h1><?= $h(__('site.changelog_title')) ?></h1>
            <p class="cl-hero__lead"><?= $h(__('site.changelog_lead')) ?></p>
            <div class="cl-hero__actions">
                <a href="#journal" class="hi-cta hi-cta-solid"><?= $h(__('site.cl_cta_latest')) ?></a>
                <a href="<?= $h($discoverHref) ?>" class="hi-cta hi-cta-ghost"><?= $h(__('site.cl_cta_discover')) ?></a>
            </div>
            <dl class="cl-status">
                <div>
                    <dt class="cl-status__k"><?= $h(__('site.cl_status_version')) ?></dt>
                    <dd class="cl-status__v"><?= $h(($featured['month_label'] ?? '') . ' ' . (string) ($featured['year'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt class="cl-status__k"><?= $h(__('site.cl_status_modules')) ?></dt>
                    <dd class="cl-status__v"><?= $h(__('site.cl_status_modules_v')) ?></dd>
                </div>
                <div>
                    <dt class="cl-status__k"><?= $h(__('site.cl_status_state')) ?></dt>
                    <dd class="cl-status__v"><?= $h(__('site.cl_status_state_v')) ?></dd>
                </div>
            </dl>
        </div>
    </header>

    <div class="cl-nav">
        <div class="cl-wrap cl-nav__inner">
            <nav class="cl-nav__links" aria-label="<?= $h(__('site.changelog')) ?>">
                <a href="#presentation"><?= $h(__('site.cl_nav_presentation')) ?></a>
                <a href="#journal"><?= $h(__('site.cl_nav_dispatch')) ?></a>
                <a href="#release"><?= $h(__('site.cl_nav_release')) ?></a>
                <a href="#historique"><?= $h(__('site.cl_nav_history')) ?></a>
                <a href="#modules"><?= $h(__('site.cl_nav_modules')) ?></a>
                <a href="#roadmap"><?= $h(__('site.cl_nav_roadmap')) ?></a>
            </nav>
            <div class="cl-filters" role="group" aria-label="<?= $h(__('site.cl_nav_history')) ?>">
                <?php foreach ($filters as $filter): ?>
                    <button type="button" class="cl-chip<?= ($filter['id'] ?? '') === 'all' ? ' is-active' : '' ?>" data-cl-domain="<?= $h($filter['id'] ?? '') ?>" aria-pressed="<?= ($filter['id'] ?? '') === 'all' ? 'true' : 'false' ?>"><?= $h($filter['label'] ?? '') ?></button>
                <?php endforeach; ?>
            </div>
            <div class="cl-filters cl-filters--years" role="group" aria-label="<?= $h(__('site.cl_filter_years_all')) ?>">
                <button type="button" class="cl-chip is-active" data-cl-year="all" aria-pressed="true"><?= $h(__('site.cl_filter_years_all')) ?></button>
                <?php foreach ($years as $year): ?>
                    <button type="button" class="cl-chip" data-cl-year="<?= $h((string) $year) ?>" aria-pressed="false"><?= $h((string) $year) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="cl-search">
                <label class="sr-only" for="cl-search"><?= $h(__('site.cl_search_label')) ?></label>
                <input id="cl-search" type="search" data-cl-search placeholder="<?= $h(__('site.cl_search_ph')) ?>" autocomplete="off">
            </div>
        </div>
    </div>

    <section class="cl-section" id="presentation">
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_plat_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(__('site.cl_plat_title')) ?></h2>
            <div class="cl-modules">
                <?php foreach ($modules as $module): ?>
                    <article class="cl-mod" data-cl-reveal>
                        <h3 class="cl-mod__name"><?= $h($module['name'] ?? '') ?></h3>
                        <p class="cl-mod__body"><?= $h($module['body'] ?? '') ?></p>
                        <p class="cl-mod__meta">
                            <span><?= $h($module['status'] ?? '') ?></span>
                            <span><?= $h(__('site.cl_mod_updated')) ?> · <?= $h($module['update'] ?? '') ?></span>
                        </p>
                        <a class="cl-mod__cta" href="<?= $h($module['href'] ?? '#') ?>"><?= $h(__('site.cl_mod_discover')) ?></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($featuredDispatch !== null || $dispatches !== []): ?>
    <section class="cl-section" id="journal">
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_dispatch_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(__('site.cl_dispatch_title')) ?></h2>
            <p class="cl-hero__lead" style="margin-top:0.85rem"><?= $h(__('site.cl_dispatch_lead')) ?></p>
            <?php if ($featuredDispatch !== null): ?>
                <div
                    class="tr-featured"
                    data-cl-reveal
                    data-cl-card
                    data-groups="<?= $h(implode(' ', array_map('strval', $featuredDispatch['filter_groups'] ?? []))) ?>"
                    data-year="<?= $h((string) ($featuredDispatch['year'] ?? '')) ?>"
                    data-search="<?= $h($featuredDispatch['search'] ?? '') ?>"
                >
                    <?php $dispatch = $featuredDispatch; $dispatchHeadingTag = 'h3'; require base_path('views/partials/dispatch_article.php'); ?>
                    <p class="tr-featured__more"><a href="<?= $h($featuredDispatch['href'] ?? '#') ?>"><?= $h(__('site.cl_dispatch_open')) ?></a></p>
                </div>
            <?php endif; ?>
            <?php if ($dispatches !== []): ?>
                <div class="tr-grid">
                    <?php foreach ($dispatches as $dispatch): ?>
                        <?php
                        if ($featuredDispatch && ($dispatch['id'] ?? '') === ($featuredDispatch['id'] ?? '')) {
                            continue;
                        }
                        require base_path('views/partials/dispatch_card.php');
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($featured !== null): ?>
    <section
        class="cl-section"
        id="release"
        data-cl-card
        data-groups="<?= $h($featuredGroups) ?>"
        data-year="<?= $h((string) ($featured['year'] ?? '')) ?>"
        data-search="<?= $h($featured['search'] ?? '') ?>"
    >
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_feat_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(($featured['version_label'] ?? 'Athena') . ' — ' . ($featured['month_label'] ?? '') . ' ' . (string) ($featured['year'] ?? '')) ?></h2>
            <article class="cl-featured" data-cl-reveal>
                <?php if ($featuredImg !== ''): ?>
                    <button type="button" class="cl-featured__media" data-cl-img="<?= $h($featuredImg) ?>" data-cl-alt="<?= $h($featured['title'] ?? '') ?>">
                        <img src="<?= $h($featuredImg) ?>" alt="<?= $h($featured['title'] ?? '') ?>" width="960" height="640">
                    </button>
                <?php else: ?>
                    <div class="cl-featured__media" aria-hidden="true"></div>
                <?php endif; ?>
                <div class="cl-featured__body">
                    <p class="cl-featured__ver"><?= $h(($featured['version_label'] ?? '') . ' ' . ($featured['version'] ?? '')) ?></p>
                    <div class="cl-badges">
                        <?php if (($featured['type'] ?? '') === 'major'): ?>
                            <span class="cl-badge cl-badge--new"><?= $h($typeLabels['major'] ?? '') ?></span>
                        <?php endif; ?>
                        <?php foreach ($featured['kinds'] ?? [] as $kind): ?>
                            <span class="cl-badge cl-badge--<?= $h($kind) ?>"><?= $h($kindLabels[$kind] ?? $kind) ?></span>
                        <?php endforeach; ?>
                        <?php foreach ($featured['categories'] ?? [] as $cat): ?>
                            <span class="cl-badge"><?= $h($categoryLabels[$cat] ?? $cat) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <h3><?= $h($featured['title'] ?? '') ?></h3>
                    <p class="cl-featured__sum"><?= $h($featured['summary'] ?? '') ?></p>
                    <?php if (!empty($featured['features'])): ?>
                        <p class="cl-section__kicker" style="margin-top:1.1rem"><?= $h(__('site.cl_feat_new')) ?></p>
                        <ul class="cl-featured__list">
                            <?php foreach ($featured['features'] as $feature): ?>
                                <li><?= $h(is_array($feature) ? ($feature['text'] ?? '') : $feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (($featured['why'] ?? '') !== ''): ?>
                        <div class="cl-why">
                            <h4><?= $h(__('site.cl_why')) ?></h4>
                            <p><?= $h($featured['why']) ?></p>
                            <div class="cl-audiences">
                                <?php if (!empty($featured['audiences']['ops'])): ?>
                                    <div class="cl-aud"><strong><?= $h(__('site.cl_aud_ops')) ?></strong><span><?= $h($featured['audiences']['ops']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($featured['audiences']['cmd'])): ?>
                                    <div class="cl-aud"><strong><?= $h(__('site.cl_aud_cmd')) ?></strong><span><?= $h($featured['audiences']['cmd']) ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($featured['audiences']['admin'])): ?>
                                    <div class="cl-aud"><strong><?= $h(__('site.cl_aud_admin')) ?></strong><span><?= $h($featured['audiences']['admin']) ?></span></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($featured['gallery'])): ?>
                        <div class="cl-gallery">
                            <?php foreach ($featured['gallery'] as $shot): ?>
                                <?php $gSrc = asset_url((string) ($shot['src'] ?? '')); ?>
                                <button type="button" data-cl-img="<?= $h($gSrc) ?>" data-cl-alt="<?= $h($shot['alt'] ?? '') ?>">
                                    <img src="<?= $h($gSrc) ?>" alt="<?= $h($shot['alt'] ?? '') ?>" width="176" height="115" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="cl-card__foot">
                        <?php foreach ($featured['links'] ?? [] as $link): ?>
                            <a href="<?= $h($link['href'] ?? '#') ?>"><?= $h($link['label'] ?? '') ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (($featured['availability'] ?? '') !== ''): ?>
                        <div class="cl-avail" style="margin-top:0.75rem">
                            <p><?= $h($featured['availability']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </section>
    <?php endif; ?>

    <section class="cl-section" id="historique">
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_hist_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(__('site.cl_hist_title')) ?></h2>
            <p class="cl-empty" data-cl-empty><?= $h(__('site.cl_empty')) ?></p>
            <?php foreach ($byYear as $year => $yearReleases): ?>
                <div class="cl-year-block" data-cl-year-block="<?= $h((string) $year) ?>">
                    <p class="cl-year"><?= $h((string) $year) ?></p>
                    <div class="cl-tl">
                        <?php foreach ($yearReleases as $release): ?>
                            <?php require base_path('views/partials/changelog_release.php'); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cl-section" id="modules">
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_eco_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(__('site.cl_eco_title')) ?></h2>
            <p class="cl-hero__lead" style="margin-top:0.85rem"><?= $h(__('site.cl_eco_lead')) ?></p>
            <div class="cl-eco" data-cl-reveal>
                <svg viewBox="0 0 720 430" role="img" aria-labelledby="cl-eco-title cl-eco-desc">
                    <title id="cl-eco-title"><?= $h(__('site.cl_eco_title')) ?></title>
                    <desc id="cl-eco-desc"><?= $h(__('site.cl_eco_lead')) ?></desc>
                    <g fill="none" stroke="rgba(255,255,255,0.16)" stroke-width="1.2">
                        <path d="M360 62 V118"/>
                        <path d="M160 118 H560"/>
                        <path d="M160 118 V168"/>
                        <path d="M360 118 V168"/>
                        <path d="M560 118 V168"/>
                        <path d="M160 208 V258"/>
                        <path d="M360 208 V258"/>
                        <path d="M560 208 V258"/>
                        <path d="M160 298 V348"/>
                        <path d="M160 378 V398"/>
                    </g>
                    <g fill="#0b0f12" stroke="rgba(52,211,153,0.45)" stroke-width="1.2">
                        <rect x="292" y="22" width="136" height="40" rx="8"/>
                    </g>
                    <g fill="#10151a" stroke="rgba(255,255,255,0.12)" stroke-width="1">
                        <rect x="92" y="168" width="136" height="40" rx="8"/>
                        <rect x="292" y="168" width="136" height="40" rx="8"/>
                        <rect x="492" y="168" width="136" height="40" rx="8"/>
                        <rect x="92" y="258" width="136" height="40" rx="8"/>
                        <rect x="292" y="258" width="136" height="40" rx="8"/>
                        <rect x="492" y="258" width="136" height="40" rx="8"/>
                        <rect x="92" y="348" width="136" height="40" rx="8"/>
                        <rect x="92" y="398" width="136" height="32" rx="8"/>
                    </g>
                    <text x="360" y="47" text-anchor="middle" font-size="12" font-weight="800" letter-spacing="3">ATHENA</text>
                    <text x="160" y="193" text-anchor="middle" font-size="11" font-weight="700" letter-spacing="2">C2</text>
                    <text x="360" y="193" text-anchor="middle" font-size="11" font-weight="700" letter-spacing="2">SSE</text>
                    <text x="560" y="193" text-anchor="middle" font-size="11" font-weight="700" letter-spacing="2"><?= $h(__('site.cl_eco_hr')) ?></text>
                    <text x="160" y="283" text-anchor="middle" font-size="11" font-weight="700" letter-spacing="2">ATAK</text>
                    <text x="360" y="283" text-anchor="middle" font-size="11" font-weight="700" letter-spacing="1.5"><?= $h(__('site.cl_eco_intel')) ?></text>
                    <text x="560" y="283" text-anchor="middle" font-size="11" font-weight="700" letter-spacing="2">LMS</text>
                    <text x="160" y="373" text-anchor="middle" font-size="10" font-weight="700" letter-spacing="1.5">OVERWATCH</text>
                    <text x="160" y="418" text-anchor="middle" font-size="10" font-weight="700" letter-spacing="2">ARMA 3</text>
                </svg>
            </div>
        </div>
    </section>

    <section class="cl-section" id="chiffres">
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_stat_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(__('site.cl_stat_title')) ?></h2>
            <div class="cl-stats">
                <?php foreach ($stats as $stat): ?>
                    <div class="cl-stat" data-cl-reveal>
                        <strong><?= $h($stat['value'] ?? '') ?></strong>
                        <span><?= $h($stat['label'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="cl-section" id="pipeline">
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_pipe_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(__('site.cl_pipe_title')) ?></h2>
            <p class="cl-hero__lead" style="margin-top:0.85rem"><?= $h(__('site.cl_pipe_lead')) ?></p>
            <div class="cl-pipe">
                <?php foreach ($pipeline as $item): ?>
                    <div class="cl-pipe__item" data-cl-reveal>
                        <p><?= $h($item['title'] ?? '') ?></p>
                        <span class="cl-st cl-st--<?= $h($item['status'] ?? '') ?>"><?= $h($statusLabels[$item['status'] ?? ''] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="cl-section" id="roadmap">
        <div class="cl-wrap">
            <p class="cl-section__kicker"><?= $h(__('site.cl_road_kicker')) ?></p>
            <h2 class="cl-section__title"><?= $h(__('site.cl_road_title')) ?></h2>
            <div class="cl-road">
                <?php foreach ($roadmap as $item): ?>
                    <div class="cl-road__item" data-cl-reveal>
                        <strong><?= $h($item['when'] ?? '') ?></strong>
                        <p><?= $h($item['body'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div id="cl-lightbox" class="cl-lb" hidden role="dialog" aria-modal="true" aria-label="<?= $h(__('site.cl_lb_close')) ?>">
        <button type="button" class="cl-lb__close" data-cl-lb-close><?= $h(__('site.cl_lb_close')) ?></button>
        <img src="" alt="">
    </div>
</div>
